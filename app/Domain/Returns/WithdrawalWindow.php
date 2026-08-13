<?php

namespace App\Domain\Returns;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonInterface;

/**
 * Cayma hakkı penceresi. (2B-K2)
 *
 * ★ 14 GÜN TESLİM GÜNÜNDEN BAŞLAR.
 *
 * ⚠️ Mevzuat açık: süre tüketicinin **malı teslim aldığı** gün başlıyor;
 * malın **taşıyıcıya teslimi süreyi BAŞLATMIYOR**.
 *
 * ⚠️ Sipariş tarihinden sayılsaydı kargoda geçen her gün müşterinin
 * hakkından yenirdi — üç gün süren teslimatta hak 14 değil 11 gün olurdu.
 *
 * ⚠️ ★ KISMİ SEVKİYATTA HER PAKETİN KENDİ TARİHİ VAR (1D.4). Süre
 * sipariş bazında değil SATIR bazında işliyor: ilk paket 1 Mart'ta,
 * ikincisi 20 Mart'ta teslim edilmişse ikincinin hakkı hâlâ açıktır.
 */
class WithdrawalWindow
{
    /** Mesafeli sözleşmelerde cayma süresi. */
    public const GUN = 14;

    /**
     * Bu satır için cayma hakkı hâlâ açık mı?
     *
     * ⚠️ Hiç teslim edilmemiş satırda süre HENÜZ BAŞLAMADI — yani açık.
     * "Teslim tarihi yok, demek ki hak yok" denseydi, kargoda olan ürün
     * için cayma imkânsız olurdu.
     */
    public function acikMi(OrderItem $satir, ?CarbonInterface $simdi = null): bool
    {
        $teslim = $this->teslimTarihi($satir);

        if ($teslim === null) {
            return true;
        }

        return $teslim->copy()->addDays(self::GUN)->isAfter($simdi ?? now());
    }

    /**
     * Satırın teslim tarihi — hangi pakette gittiyse ONUN tarihi.
     *
     * ⚠️ İptal edilmiş paketler sayılmıyor (1D.4): o satırlar yeniden
     * sevk edilebilir durumda, teslim edilmiş sayılamaz.
     */
    public function teslimTarihi(OrderItem $satir): ?CarbonInterface
    {
        $tarih = null;

        foreach ($satir->fulfillmentItems as $kalem) {
            $paket = $kalem->fulfillment;

            if ($paket === null || $paket->status !== ShipmentStatus::Delivered) {
                continue;
            }

            /*
            | ⚠️ Bir satır birden çok pakette gitmiş olabilir (3 adetin
            | 2'si bir pakette, 1'i başka pakette). EN SON teslim alınıyor:
            | müşterinin son parçayı aldığı gün, hakkının başladığı gün.
            */
            if ($paket->delivered_at !== null && ($tarih === null || $paket->delivered_at->isAfter($tarih))) {
                $tarih = $paket->delivered_at;
            }
        }

        return $tarih;
    }

    /**
     * Siparişin TAMAMI için cayma açık mı? Tek satır bile açıksa evet.
     */
    public function siparisAcikMi(Order $siparis): bool
    {
        $siparis->load('items.fulfillmentItems.fulfillment');

        foreach ($siparis->items as $satir) {
            if ($this->acikMi($satir)) {
                return true;
            }
        }

        return false;
    }
}
