<?php

namespace App\Domain\Order;

use App\Domain\Analytics\EventRecorder;
use App\Domain\Cart\CartService;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Notification\Notifier;
use App\Domain\Promotion\CouponService;
use App\Domain\Settings\SettingsService;
use App\Domain\Stock\StockService;
use App\Enums\CartStatus;
use App\Enums\EventType;
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
        private readonly EventRecorder $olaylar,
        private readonly Notifier $bildirimler,
        private readonly CouponService $kuponlar,
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

            /*
            | ★ 1F-K5'in ASIL SEBEBİ BURASI.
            |
            | ⚠️ Bu satır bir TRANSACTION'IN İÇİNDE. Olay doğrudan kuyruğa
            | atılsaydı ve aşağıdaki COMMIT'e gelinemeseydi, sipariş HİÇ
            | VAR OLMAZ ama olay Redis'e girmiş olurdu — worker onu alır
            | ve olmayan bir siparişin `order_placed` olayını yazardı.
            |
            | `EventRecorder` işi `afterCommit()` ile atıyor: geri
            | sarılırsa iş hiç kuyruğa girmiyor.
            |
            | ⚠️ Payload'da KİŞİSEL VERİ YOK (1F-K4): e-posta, ad, adres
            | girmiyor — yalnızca kimlik, tutar ve satır sayısı.
            */
            $this->olaylar->kaydet(EventType::OrderPlaced, [
                'order_id' => $siparis->id,
                'order_number' => $siparis->order_number,
                'grand_total' => $siparis->grand_total,
                'item_count' => $siparis->items->count(),
            ], $sepet->customer);

            return $siparis;
        });
    }

    /**
     * ★ Ödeme BAŞLADI: rezervasyonların ömrü 15 dk → 60 dk. (1E.2)
     *
     * Sağlayıcıya yönlendirmeden HEMEN ÖNCE çağrılıyor — o andan sonra
     * müşteri bizde değil, süreyi uzatma şansımız kalmıyor.
     *
     * ⚠️ Sipariş durumu DEĞİŞMİYOR. "Ödemeye başladı" bir ödeme durumu
     * değil; para hâlâ çekilmedi ve hiç çekilmeyebilir. `payment_status`
     * burada değiştirilseydi, ödeme sayfasını açıp vazgeçen her müşteri
     * için sipariş yanlış durumda kalırdı.
     */
    public function odemeBaslatildi(Order $siparis): Order
    {
        foreach ($this->rezervasyonlari($siparis) as $rezervasyon) {
            $this->stok->odemeyeAl($rezervasyon);
        }

        return $siparis;
    }

    /**
     * Ödeme başarılı: rezervasyonlar kesinleşir, STOK GERÇEKTEN DÜŞER.
     *
     * ⚠️ 1E'de gerçek sağlayıcı bunu çağıracak. Şimdilik dikiş yeri.
     */
    public function odemeBasarili(Order $siparis): Order
    {
        $rezervasyonlar = $this->rezervasyonlari($siparis);

        /*
        | ★ 1E-K5: "PARA GELDİ, MAL YOK" TESPİTİ.
        |
        | ⚠️ Ölçüm KESİNLEŞTİRMEDEN ÖNCE yapılmak zorunda: `kesinlestir()`
        | rezervasyonu `committed` yapıyor, o andan sonra "aktif" sayılmıyor
        | ve elimizde ölçecek bir şey kalmıyor.
        */
        $acikVar = $this->stokAcigiVarMi($siparis, $rezervasyonlar);

        foreach ($rezervasyonlar as $rezervasyon) {
            $this->stok->kesinlestir($rezervasyon);
        }

        $siparis->payment_status = PaymentStatus::Paid;

        /*
        | ⚠️ Sipariş yine de KABUL EDİLİYOR (1E-K5). Reddedip iade etmek
        | müşteriyi 3-5 gün parasız bırakırdı; tedarik edebilen marka da
        | satışı kaybederdi. Karar ticari, o yüzden markaya bırakılıyor —
        | ama SESSİZ KALMIYOR, panelde uyarı olarak görünüyor.
        */
        $siparis->stock_shortfall = $acikVar;
        $siparis->save();

        /*
        | ⚠️ SİPARİŞ ONAYI BURADA, `baslat()`'ta DEĞİL (2H).
        |
        | Sipariş `pending` doğuyor ve ödemesi hiç tamamlanmayabiliyor.
        | Oluşma anında gönderilseydi, ödeme sayfasını açıp vazgeçen her
        | müşteri "siparişiniz alındı" maili alır ve gelmeyecek bir
        | kargoyu beklerdi.
        */
        $this->bildirimler->siparisOnayi($siparis);

        return $siparis;
    }

    /**
     * Rezerve edilen adet, sipariş edilen adedi karşılıyor mu?
     *
     * ⚠️ Açık genelde 60 dakikayı aşan bildirimden doğuyor: rezervasyon
     * süresi dolup düşmüş, stok başkasına gitmiş, sonra ödeme onaylanmış.
     * 1E.2 bunu NADİRLEŞTİRDİ ama imkânsız kılmadı.
     *
     * @param  \Illuminate\Support\Collection<int, StockReservation>  $rezervasyonlar
     */
    private function stokAcigiVarMi(Order $siparis, $rezervasyonlar): bool
    {
        $siparis->load('items');

        // varyant → rezerve edilmiş toplam adet
        $rezerve = [];

        foreach ($rezervasyonlar as $rezervasyon) {
            $varyantId = (int) $rezervasyon->variant_id;
            $rezerve[$varyantId] = ($rezerve[$varyantId] ?? 0) + $rezervasyon->quantity;
        }

        foreach ($siparis->items as $satir) {
            /*
            | ⚠️ Varyantı silinmiş satır (variant_id null) ölçüme girmiyor:
            | sipariş satırı bir FOTOĞRAF, varyant silinse de yaşıyor
            | (1D). Onu "açık" saymak her eski siparişi işaretlerdi.
            */
            if ($satir->variant_id === null) {
                continue;
            }

            if (($rezerve[(int) $satir->variant_id] ?? 0) < $satir->quantity) {
                return true;
            }
        }

        return false;
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

        /*
        | ⚠️ 1E.7.3'te ölçülen boşluğu kapatıyor: yetersiz bakiyede müşteri
        | neden reddedildiğini hiç öğrenemiyordu.
        */
        $this->bildirimler->odemeBasarisiz($siparis);

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

        /*
        | ★ 2A — KUPON. Etki iki parçalı: indirim ve ücretsiz kargo.
        */
        $kuponEtkisi = $this->kuponlar->etki($sepet->coupon_code, $araToplam);
        $indirim = $kuponEtkisi['discount'];

        /*
        | ★ 2A-K1 — KARGO EŞİĞİ HANGİ TUTARA BAKIYOR?
        |
        | ```
        | A  indirim → eşik   480 −%20 = 384 → eşiğin altında → kargo VAR
        | B  eşik → indirim   480 eşiği geçti → kargo YOK → sonra indirim
        | ```
        |
        | ⚠️ Bu KURUŞ değil YÜZDE kaybettirir: B'de müşteri indirimle
        | birlikte bedava kargo da kazanıyor.
        |
        | Varsayılan A — müşterinin gerçekten ödediği tutar eşiği
        | belirlemeli. WooCommerce'in varsayılanı da bu, ama onlar da
        | AYAR yapmış ve konuyla ilgili en az iki hata kaydı açılmış;
        | yani satıcılar anlaşamıyor. Biz de ayar bırakıyoruz.
        */
        $esigeIndirimliBak = (bool) ($kargoAyarlari['threshold_after_discount'] ?? true);

        $esikTutari = $esigeIndirimliBak
            ? bcsub($araToplam, $indirim, 2)
            : $araToplam;

        $kargo = $kuponEtkisi['free_shipping']
            ? '0.00'
            : $this->hesap->kargo(
                $esikTutari,
                (string) ($kargoAyarlari['flat_fee'] ?? 0),
                (string) ($kargoAyarlari['free_threshold'] ?? 0),
            );

        $kdvOrani = (string) $this->ayarlar->al(SettingGroup::Tax, 'default_rate', 20);
        $toplamlar = $this->hesap->siparis($urunToplami, $kargo, $kdvOrani, $indirim);

        $siparis = new Order;
        $siparis->order_number = $this->siparisNumarasi();
        $siparis->customer()->associate($sepet->customer);
        $siparis->email = (string) $veri['email'];
        $siparis->payment_status = PaymentStatus::Pending;

        $siparis->items_total = $toplamlar['items_total'];
        $siparis->discount_total = $indirim;

        /*
        | ★ 2A-K4 — KUPON KODU SİPARİŞE KOPYALANIYOR, bağlanmıyor.
        | "Sipariş bir fotoğraftır" (1D): kupon sonradan silinse bile
        | sipariş neyle indirildiğini söyleyebilmeli.
        */
        $siparis->coupon_code = $sepet->coupon_code;

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
        | ★ 2A-K3 — KUPON KOTASI BURADA HARCANIYOR, sepette değil.
        |
        | ⚠️ Sepette harcansaydı kuponu deneyip vazgeçen her müşteri
        | kotadan bir kullanım yer ve kampanya hiç satış olmadan tükenirdi.
        |
        | ⚠️ Bu çağrı sipariş transaction'ının İÇİNDE: sipariş geri
        | sarılırsa kota da geri sarılıyor. Satır kilidi kotanın yarışta
        | aşılmasını engelliyor (1D-K5'in tekrarı).
        */
        if ($siparis->coupon_code !== null) {
            $this->kuponlar->tuket($siparis, $siparis->coupon_code, (string) $siparis->discount_total);
        }

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
        /*
        | ⚠️ AKTİF durumların TAMAMI — yalnızca `Held` DEĞİL.
        |
        | 1E.2'de `Paying` eklendi. Burada `Held` kalsaydı ödeme başarılı
        | webhook'u geldiğinde bu sorgu BOŞ dönerdi: hiçbir rezervasyon
        | kesinleşmez, stok hiç düşmez, sipariş yine de `paid` olurdu.
        | Sessiz ve kalıcı envanter hatası.
        */
        return StockReservation::where('order_id', $siparis->id)
            ->whereIn('status', ReservationStatus::aktifDegerler())
            ->get();
    }
}
