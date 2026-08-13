<?php

namespace App\Domain\Privacy;

use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kişisel veriyi TANINMAZ HALE GETİRİR. (2G-K1)
 *
 * ★ SİLMEZ. Sipariş yasal saklama süresi boyunca duruyor — tutarıyla,
 * satırlarıyla, vergisiyle. Silinen şey yalnızca kime ait olduğu.
 *
 * ⚠️ `DELETE` yazılsaydı iki şeyden biri olurdu: ya yasal kayıt kaybolur
 * ya yabancı anahtarlar kırılırdı.
 *
 * ⚠️ ★ ASIL İŞ `orders`'TA — `customers`'ta değil.
 *
 * Sipariş bir FOTOĞRAF: adres müşteri defterinden okunmuyor, siparişin
 * kendi kopyasından okunuyor (1D). Yalnızca `customers` temizlenseydi ad,
 * telefon ve adres siparişlerde olduğu gibi kalırdı — ve kimse fark
 * etmezdi. Bu, projenin en sevdiği hata türü: sessiz ve yanlış.
 *
 * ⚠️ Magento ve WooCommerce de aynı yolu tutuyor: sipariş muhasebe için
 * saklanıyor, kişisel alanlar anonimleştiriliyor.
 */
class Anonymizer
{
    /** Anonimleştirilmiş alanlara yazılan işaret. */
    public const SILINDI = '[silindi]';

    /**
     * Bir müşterinin TÜM kişisel verisini tanınmaz hale getirir.
     *
     * ⚠️ Tek transaction: yarım kalırsa kişisel verinin bir kısmı silinmiş,
     * bir kısmı durur hâlde kalırdı — hem yasal hem teknik olarak en kötü
     * durum.
     */
    public function musteriyiAnonimlestir(Customer $musteri): void
    {
        DB::transaction(function () use ($musteri) {
            /*
            | ⚠️ Sorgu DARALTILMIŞ (1A.5 deseni): başka müşterinin siparişi
            | sonuç kümesine hiç girmiyor. Yanlış siparişi anonimleştirmek
            | geri alınamaz bir hata olurdu.
            */
            Order::where('customer_id', $musteri->id)->get()
                ->each(fn (Order $siparis) => $this->siparisiAnonimlestir($siparis));

            Address::where('customer_id', $musteri->id)->get()
                ->each(fn (Address $adres) => $this->adresiAnonimlestir($adres));

            /*
            | ⚠️ E-posta BENZERSİZ olmak zorunda (`customers.email` unique).
            | Hepsine aynı işaret yazılsaydı ikinci anonimleştirme
            | veritabanı hatasıyla düşerdi.
            */
            $musteri->name = self::SILINDI;
            $musteri->email = 'silinmis-'.Str::uuid()->toString().'@anonim.invalid';
            $musteri->phone = null;
            $musteri->password = null;
            $musteri->accepts_marketing = false;
            $musteri->email_verified_at = null;
            $musteri->save();

            /*
            | ⚠️ Oturumu kapatmak için token'lar siliniyor. Kalsaydı
            | anonimleştirilmiş hesapla giriş yapılmaya devam edilirdi.
            */
            $musteri->tokens()->delete();
        });
    }

    /**
     * Misafir siparişini anonimleştirir — müşteri kaydı olmadan.
     *
     * ⚠️ Misafir siparişinde `customer_id` zaten boş (M-1); kişisel veri
     * yalnızca siparişin kendi kopyalarında.
     */
    public function siparisiAnonimlestir(Order $siparis): void
    {
        /*
        | ★ 2G-K2 — MİSAFİR SİPARİŞİNE DÖNÜŞÜYOR.
        |
        | Magento da böyle yapıyor. Yapı zaten hazır: misafir siparişi
        | Faz 1'den beri var ve `orders.customer_id` nullable.
        */
        $siparis->customer_id = null;

        $siparis->email = 'silinmis@anonim.invalid';

        /*
        | ⚠️ Alanlar TEK TEK yazılıyor, döngüyle değil — 1D'deki dersin
        | aynısı: döngüyle üretilen kolonları statik analiz göremiyor.
        */
        $siparis->shipping_full_name = self::SILINDI;
        $siparis->shipping_phone = self::SILINDI;
        $siparis->shipping_line1 = self::SILINDI;
        $siparis->shipping_line2 = null;
        $siparis->shipping_neighborhood = null;
        $siparis->shipping_postal_code = null;

        $siparis->billing_full_name = self::SILINDI;
        $siparis->billing_phone = self::SILINDI;
        $siparis->billing_line1 = self::SILINDI;
        $siparis->billing_line2 = null;
        $siparis->billing_neighborhood = null;
        $siparis->billing_postal_code = null;

        $siparis->billing_tax_number = null;
        $siparis->billing_tax_office = null;

        /*
        | ⚠️ ŞEHİR ve İLÇE KALIYOR — bilerek.
        |
        | Kişiyi tanımlamıyorlar ama markanın satış coğrafyası raporu
        | onlara dayanıyor. Silinseydi geçmiş satış dağılımı bozulurdu ve
        | bunun KVKK açısından bir kazancı olmazdı.
        */

        $siparis->save();
    }

    private function adresiAnonimlestir(Address $adres): void
    {
        $adres->title = self::SILINDI;
        $adres->full_name = self::SILINDI;
        $adres->phone = self::SILINDI;
        $adres->line1 = self::SILINDI;
        $adres->line2 = null;
        $adres->neighborhood = null;
        $adres->postal_code = null;
        $adres->save();
    }
}
