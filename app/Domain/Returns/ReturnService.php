<?php

namespace App\Domain\Returns;

use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\DB;

/**
 * İade TALEBİ — ürünün geri yolculuğu. (2B-K1)
 *
 * ★ Bu servis PARA'YA HİÇ DOKUNMUYOR. Para iadesi `RefundService`'in işi
 * ve ancak ürün elde olduğunda açılabiliyor.
 *
 * ⚠️ Ayrılmasaydı "onaylandı" ne demek olurdu — ürün geldi mi, para gitti
 * mi? Magento da ayırmış: kredi notunda "stoğa geri" ayrı bir onay kutusu.
 *
 * AKIŞ:
 *
 *   requested → approved → received → (para iadesi açılabilir)
 *            ↘ rejected
 */
class ReturnService
{
    public function __construct(private readonly WithdrawalWindow $pencere) {}

    /**
     * Müşteri iade talebi açar.
     *
     * @param  array<int, int>  $satirlar  order_item_id → adet
     *
     * @throws ReturnWindowClosedException
     * @throws OverReturnException
     */
    public function talepAc(Order $siparis, array $satirlar, bool $cayma = true, ?string $sebep = null): OrderReturn
    {
        /*
        | ⚠️ ÖDENMEMİŞ siparişin iadesi olmaz — geri verilecek para yok.
        | Kontrol edilmeseydi tutar hesaplanır, para iadesi açılır ve
        | sağlayıcıya hiç var olmayan bir tahsilatın iadesi gönderilirdi.
        */
        /*
        | ⚠️ `PartiallyRefunded` DE KABUL. Testin bulduğu hata: ilk kısmi
        | iadeden sonra sipariş `partially_refunded` oluyordu ve ikinci
        | talep açılamıyordu — oysa kalan satırların iade hakkı duruyor.
        */
        if (! in_array($siparis->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], strict: true)) {
            throw new ReturnNotRefundableException(ReturnStatus::Requested);
        }

        $satirlar = array_filter($satirlar, fn (int $adet) => $adet > 0);

        if ($satirlar === []) {
            throw new OverReturnException('—', 0, 0);
        }

        return DB::transaction(function () use ($siparis, $satirlar, $cayma, $sebep) {
            $talep = new OrderReturn;
            $talep->order()->associate($siparis);
            $talep->is_withdrawal = $cayma;
            $talep->reason = $sebep;
            $talep->status = ReturnStatus::Requested;
            $talep->save();

            foreach ($satirlar as $satirId => $adet) {
                /*
                | ⚠️ Satır SİPARİŞE DARALTILMIŞ sorgudan çözülüyor (1A.5):
                | başka siparişin satırı sonuç kümesine hiç girmiyor.
                */
                $kalem = $siparis->items()->where('id', $satirId)->firstOrFail();

                /*
                | ★ 2B-K2 — CAYMA SÜRESİ SATIR BAZINDA.
                |
                | ⚠️ Kısmi sevkiyatta her paketin kendi teslim tarihi var
                | (1D.4): ilk paket 1 Mart'ta, ikincisi 20 Mart'ta teslim
                | edilmişse ikincinin hakkı hâlâ açık.
                |
                | ⚠️ Kusurlu ürün iadesi bu kontrole GİRMİYOR — cayma
                | değil, süresi yok.
                */
                if ($cayma && ! $this->pencere->acikMi($kalem)) {
                    throw new ReturnWindowClosedException;
                }

                $this->asimiDogrula($kalem->id, $kalem->sku, $adet, $kalem->quantity);

                $satir = new ReturnItem;
                $satir->orderReturn()->associate($talep);
                $satir->orderItem()->associate($kalem);
                $satir->quantity = $adet;
                $satir->save();
            }

            return $talep->load('items');
        });
    }

    public function onayla(OrderReturn $talep): OrderReturn
    {
        $talep->status = ReturnStatus::Approved;
        $talep->decided_at = now();
        $talep->save();

        return $talep;
    }

    public function reddet(OrderReturn $talep, ?string $not = null): OrderReturn
    {
        $talep->status = ReturnStatus::Rejected;
        $talep->decision_note = $not;
        $talep->decided_at = now();
        $talep->save();

        return $talep;
    }

    /**
     * ★ ÜRÜN ELDE. Para iadesi ancak bundan sonra açılabiliyor (2B-K1).
     *
     * ⚠️ 2B-K6 — STOK OTOMATİK GERİ GİRMİYOR.
     *
     * `stogaGeriKoy` ayrı bir karar: ürün gerçekten satılabilir durumda
     * mı? Otomatik olsaydı hasarlı gelen ürün satışa açılır, bir sonraki
     * müşteriye o gönderilirdi. Magento'da da bu ayrı bir onay kutusu.
     */
    public function teslimAlindi(OrderReturn $talep, bool $stogaGeriKoy = false): OrderReturn
    {
        DB::transaction(function () use ($talep, $stogaGeriKoy) {
            $talep->status = ReturnStatus::Received;
            $talep->received_at = now();
            $talep->save();

            if (! $stogaGeriKoy) {
                return;
            }

            $talep->load('items.orderItem.variant');

            foreach ($talep->items as $satir) {
                $varyant = $satir->orderItem?->variant;

                /*
                | ⚠️ Varyant silinmiş olabilir — sipariş satırı bir
                | fotoğraf, varyant katalogdan kaldırılmış olsa da yaşıyor
                | (1D). Stok geri konacak bir yer yoksa sessizce atlanıyor.
                */
                if ($varyant === null) {
                    continue;
                }

                $varyant->stock += $satir->quantity;
                $varyant->save();
            }
        });

        return $talep;
    }

    /**
     * @throws OverReturnException
     */
    /**
     * Bu satırdan daha kaç adet iade edilebilir. (4.5K)
     *
     * ★ NEDEN PUBLIC: vitrindeki iade ekranı "kaç adet seçebilirim"i
     * göstermek zorunda. Ekran kendi hesabını yapsaydı iki formül olurdu
     * ve biri güncellenmeden kalırdı — `asimiDogrula` ile AYNI sorgu.
     *
     * ⚠️ Reddedilen talepler sayılmıyor: o satırlar yeniden iade
     * edilebilir olmalı.
     */
    public function iadeEdilebilirAdet(OrderItem $satir): int
    {
        $mevcut = (int) ReturnItem::where('order_item_id', $satir->id)
            ->whereHas('orderReturn', fn ($sorgu) => $sorgu->where('status', '!=', ReturnStatus::Rejected->value))
            ->sum('quantity');

        return max(0, $satir->quantity - $mevcut);
    }

    private function asimiDogrula(int $satirId, string $sku, int $eklenecek, int $siparisAdedi): void
    {
        /*
        | ★ `OverShipmentException`'ın (1D.4) aynası — orada fazla
        | GÖNDERİM, burada fazla İADE.
        |
        | ⚠️ Reddedilen talepler sayılmıyor: o satırlar yeniden iade
        | edilebilir olmalı. Sevkiyattaki "iptal edilen paket sayılmaz"
        | kuralının aynısı.
        */
        $mevcut = (int) ReturnItem::where('order_item_id', $satirId)
            ->whereHas('orderReturn', fn ($sorgu) => $sorgu->where('status', '!=', ReturnStatus::Rejected->value))
            ->sum('quantity');

        if ($mevcut + $eklenecek > $siparisAdedi) {
            throw new OverReturnException($sku, $siparisAdedi, $mevcut + $eklenecek);
        }
    }
}
