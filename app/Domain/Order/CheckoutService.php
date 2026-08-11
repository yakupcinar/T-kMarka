<?php

namespace App\Domain\Order;

use App\Domain\Cart\CartService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Settings\SettingsService;
use App\Domain\Stock\StockService;
use App\Enums\CartStatus;
use App\Enums\LegalDocumentType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\SettingGroup;
use App\Models\Cart;
use App\Models\LegalDocumentVersion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ÖDEME ADIMI — orkestratör. (PLAN.md 1D)
 *
 * ★ Kendi iş yapmıyor, SIRAYI yönetiyor:
 *
 *   1  sepeti doğrula .......... ölü satır? stok yetiyor mu?
 *   2  ┌─ BEGIN
 *   3  │   kilitle + rezerve et  (StockService)
 *   4  │   siparişi oluştur      satırlar DONAR
 *   5  │   sözleşme onayını yaz  GÖSTERİLEN sürüme bağlanır
 *   6  └─ COMMIT
 *   7  → ödeme                   ⚠️ TRANSACTION'IN DIŞINDA (1E)
 *   8  başarılı → rezervasyon kesinleşir, stok düşer, paid
 *      başarısız → rezervasyon serbest, sipariş cancelled
 *
 * ⚠️ PLAN TASLAĞINDAN SAPMA: taslakta rezervasyon COMMIT edilip sipariş
 * SONRA oluşturuluyordu. Tek transaction'a alındı — arada sipariş
 * oluşturma patlarsa rezervasyon ortada kalır ve stok 15 dakika boşuna
 * bağlı dururdu. Süre yine kısa; içeride dış çağrı yok.
 *
 * ⚠️ ÖDEME NEDEN DIŞARIDA: dış servis yavaşlarsa satırlar dakikalarca
 * kilitli kalır ve tüm mağaza donar.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartService $sepetler,
        private readonly StockService $stok,
        private readonly OrderTotals $hesap,
        private readonly SettingsService $ayarlar,
        private readonly LegalDocumentService $belgeler,
    ) {}

    /**
     * Siparişi oluşturur ve stoğu bağlar. Ödeme HENÜZ yapılmadı.
     *
     * @param  array{email: string, shipping: array<string, string|null>, billing?: array<string, string|null>, billing_tax_number?: string|null, billing_tax_office?: string|null, legal_version_id: int}  $veri
     *
     * @throws CartNotOrderableException
     * @throws StaleContractException
     */
    public function baslat(Cart $sepet, array $veri): Order
    {
        $sepet->load('items.variant.product');

        /*
        | 1 — SEPET DOĞRULAMASI. Bağlayıcı kontrol burada; sepetteki
        | kontrol yumuşaktı (1C-K3). Ölü satır varsa ya da stok yetmiyorsa
        | sipariş HİÇ başlamıyor.
        */
        $engeller = $this->sepetler->engeller($sepet);

        if ($engeller !== [] || $sepet->items->isEmpty()) {
            throw new CartNotOrderableException($engeller);
        }

        $sozlesme = $this->sozlesmeyiDogrula((int) $veri['legal_version_id']);

        return DB::transaction(function () use ($sepet, $veri, $sozlesme) {
            /*
            | 3 — KİLİTLE + REZERVE ET.
            |
            | `StockService` satırları id sırasına göre kilitliyor (deadlock
            | engeli) ve `lock_timeout` uyguluyor (1D-K6). Stok yetmezse
            | istisna fırlıyor ve transaction geri sarılıyor — sipariş de
            | oluşmuyor.
            */
            $rezervasyonlar = $this->stok->sepetiRezerveEt($sepet);

            $siparis = $this->siparisiOlustur($sepet, $veri, $sozlesme);

            // Rezervasyonlar artık siparişin: ödemenin sonucuna göre
            // kesinleşecek ya da serbest bırakılacak.
            foreach ($rezervasyonlar as $rezervasyon) {
                $rezervasyon->order()->associate($siparis);
                $rezervasyon->save();
            }

            // Sepet tüketildi. Silmiyoruz — denetim izi.
            $sepet->status = CartStatus::Converted;
            $sepet->save();

            return $siparis;
        });
    }

    /**
     * Ödeme başarılı: rezervasyonlar kesinleşir, STOK GERÇEKTEN DÜŞER.
     *
     * ⚠️ 1E'de gerçek sağlayıcı bunu çağıracak. Şimdilik dikiş yeri.
     */
    public function odemeBasarili(Order $siparis): Order
    {
        foreach ($this->rezervasyonlari($siparis) as $rezervasyon) {
            $this->stok->kesinlestir($rezervasyon);
        }

        $siparis->payment_status = PaymentStatus::Paid;
        $siparis->save();

        return $siparis;
    }

    /**
     * Ödeme başarısız: rezervasyonlar serbest bırakılır, sipariş iptal.
     *
     * ⚠️ Sipariş SİLİNMİYOR. "Neden ödeme alınamadı" sorusunun cevabı
     * kayıtta kalmalı; ayrıca müşteri aynı numarayla tekrar deneyebilir.
     */
    public function odemeBasarisiz(Order $siparis): Order
    {
        foreach ($this->rezervasyonlari($siparis) as $rezervasyon) {
            $this->stok->serbestBirak($rezervasyon);
        }

        $siparis->payment_status = PaymentStatus::Failed;
        $siparis->save();

        return $siparis;
    }

    /**
     * Onaylanan sözleşme sürümünü doğrular. (1A.4 · 1D-K2)
     *
     * ⚠️ Müşterinin GÖRDÜĞÜ sürüm gönderiliyor, "en son sürüm" değil.
     * Sunucu kendi bildiği güncel sürümü yazsaydı, 10:00:00'da sürüm 7'yi
     * onaylayan müşteri 10:00:03'te yayınlanan sürüm 8'e bağlanırdı —
     * görmediği bir metne imza attırmak olurdu.
     *
     * Ama gönderilen sürüm gerçekten MESAFELİ SATIŞ sözleşmesi olmalı:
     * istemci KVKK metninin sürümünü göndererek sözleşmeyi atlayamaz.
     *
     * @throws StaleContractException
     */
    private function sozlesmeyiDogrula(int $surumId): LegalDocumentVersion
    {
        $surum = $this->belgeler->surum($surumId);

        if ($surum === null || $surum->type !== LegalDocumentType::DistanceSales) {
            throw new StaleContractException($surumId);
        }

        return $surum;
    }

    /**
     * @param  array<string, mixed>  $veri
     */
    private function siparisiOlustur(Cart $sepet, array $veri, LegalDocumentVersion $sozlesme): Order
    {
        /** @var array<string, string|null> $teslimat */
        $teslimat = $veri['shipping'];

        /** @var array<string, string|null> $fatura */
        $fatura = $veri['billing'] ?? $teslimat;

        // Satır tutarları — hesap TEK YERDE (§8.2).
        $satirVerileri = [];

        foreach ($sepet->items as $satir) {
            $varyant = $satir->variant;
            $urun = $varyant?->product;

            if ($varyant === null || $urun === null) {
                continue;
            }

            $tutarlar = $this->hesap->satir((string) $varyant->price, $satir->quantity, (string) $urun->tax_rate);

            $satirVerileri[] = [
                'variant' => $varyant,
                'product' => $urun,
                'quantity' => $satir->quantity,
                'line_total' => $tutarlar['line_total'],
                'tax_amount' => $tutarlar['tax_amount'],
            ];
        }

        $urunToplami = array_map(
            fn (array $s) => ['line_total' => $s['line_total'], 'tax_amount' => $s['tax_amount']],
            $satirVerileri,
        );

        $kargoAyarlari = $this->ayarlar->grup(SettingGroup::Shipping);
        $araToplam = array_reduce(
            $urunToplami,
            fn (string $t, array $s) => bcadd($t, $s['line_total'], 2),
            '0.00',
        );

        $kargo = $this->hesap->kargo(
            $araToplam,
            (string) ($kargoAyarlari['flat_fee'] ?? 0),
            (string) ($kargoAyarlari['free_threshold'] ?? 0),
        );

        $kdvOrani = (string) $this->ayarlar->al(SettingGroup::Tax, 'default_rate', 20);
        $toplamlar = $this->hesap->siparis($urunToplami, $kargo, $kdvOrani);

        $siparis = new Order;
        $siparis->order_number = $this->siparisNumarasi();
        $siparis->customer()->associate($sepet->customer);
        $siparis->email = (string) $veri['email'];
        $siparis->payment_status = PaymentStatus::Pending;

        $siparis->items_total = $toplamlar['items_total'];
        $siparis->shipping_total = $kargo;
        $siparis->tax_total = $toplamlar['tax_total'];
        $siparis->grand_total = $toplamlar['grand_total'];

        /*
        | ⚠️ ADRES KOPYALARI — döngüyle DEĞİL, tek tek.
        |
        | İlk yazımda `$siparis->{"{$tur}_{$alan}"}` ile üretiliyordu. İki
        | sorun çıktı:
        |   1. statik analiz dinamik özellik adını çözemedi
        |   2. ZORUNLU ve İSTEĞE BAĞLI alanlar aynı muameleyi görüyordu —
        |      oysa `city` boş geçemez, `line2` geçebilir
        | Açık yazım ikisini de çözüyor ve farkı görünür kılıyor.
        */
        $siparis->shipping_full_name = (string) $teslimat['full_name'];
        $siparis->shipping_phone = (string) $teslimat['phone'];
        $siparis->shipping_city = (string) $teslimat['city'];
        $siparis->shipping_district = (string) $teslimat['district'];
        $siparis->shipping_line1 = (string) $teslimat['line1'];
        $siparis->shipping_neighborhood = $teslimat['neighborhood'] ?? null;
        $siparis->shipping_line2 = $teslimat['line2'] ?? null;
        $siparis->shipping_postal_code = $teslimat['postal_code'] ?? null;

        $siparis->billing_full_name = (string) $fatura['full_name'];
        $siparis->billing_phone = (string) $fatura['phone'];
        $siparis->billing_city = (string) $fatura['city'];
        $siparis->billing_district = (string) $fatura['district'];
        $siparis->billing_line1 = (string) $fatura['line1'];
        $siparis->billing_neighborhood = $fatura['neighborhood'] ?? null;
        $siparis->billing_line2 = $fatura['line2'] ?? null;
        $siparis->billing_postal_code = $fatura['postal_code'] ?? null;

        $siparis->billing_tax_number = $veri['billing_tax_number'] ?? null;
        $siparis->billing_tax_office = $veri['billing_tax_office'] ?? null;

        $siparis->terms_accepted_at = now();
        $siparis->legalVersion()->associate($sozlesme);
        $siparis->placed_at = now();
        $siparis->save();

        /*
        | ★ SATIRLAR DONUYOR.
        |
        | Başlık, sku, seçenekler, fiyat ve KDV oranı KOPYALANIYOR. Ürüne
        | bağlanıp okunsaydı marka yarın fiyatı değiştirdiğinde geçmiş
        | siparişlerin tutarı da değişirdi.
        */
        foreach ($satirVerileri as $veriSatiri) {
            $satir = new OrderItem;
            $satir->order()->associate($siparis);
            $satir->variant()->associate($veriSatiri['variant']);
            $satir->product_title = $veriSatiri['product']->title;
            $satir->variant_options = $veriSatiri['variant']->options;
            $satir->sku = $veriSatiri['variant']->sku;
            $satir->unit_price = $veriSatiri['variant']->price;
            $satir->quantity = $veriSatiri['quantity'];
            $satir->line_total = $veriSatiri['line_total'];
            $satir->tax_rate = $veriSatiri['product']->tax_rate;
            $satir->tax_amount = $veriSatiri['tax_amount'];
            $satir->save();
        }

        return $siparis->load('items');
    }

    /**
     * `TM-2026-000123` (1D-K4).
     *
     * ⚠️ `MAX(order_number) + 1` DEĞİL: iki eşzamanlı sipariş aynı numarayı
     * okur ve ikisi de yazmaya çalışır. PostgreSQL dizisi eşzamanlılıkta
     * güvenli; transaction geri sarılsa bile numara tekrar KULLANILMIYOR —
     * muhasebede numara atlaması, numara tekrarından iyidir.
     */
    private function siparisNumarasi(): string
    {
        $sira = (int) DB::selectOne("SELECT nextval('order_number_seq') AS n")->n;

        return sprintf('TM-%s-%06d', now()->format('Y'), $sira);
    }

    /**
     * @return Collection<int, StockReservation>
     */
    private function rezervasyonlari(Order $siparis)
    {
        return StockReservation::where('order_id', $siparis->id)
            ->where('status', ReservationStatus::Held)
            ->get();
    }
}
