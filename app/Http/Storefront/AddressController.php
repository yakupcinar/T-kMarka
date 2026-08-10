<?php

namespace App\Http\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Storefront\Requests\AddressRequest;
use App\Models\Address;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Müşterinin adres defteri. `auth:customer` arkasında.
 *
 * ★ BU SINIFTAKİ TEK KURAL: müşteri yalnızca KENDİ adreslerine erişir.
 *
 * Uygulama biçimi bilinçli seçildi:
 *
 *   YÜKLE-SONRA-KONTROL ET (kullanılmıyor)
 *     $adres = Address::find($id);
 *     if ($adres->customer_id !== $musteri->id) abort(403);
 *     → satır belleğe geldi. Kontrolü yazmayı unutan bir uç, başkasının
 *       adresini döndürür ve hata vermez.
 *
 *   HİÇ YÜKLEME (kullanılan)
 *     $musteri->addresses()->findOrFail($id)
 *     → sorgu zaten `WHERE customer_id = <ben>` içeriyor. Başkasının adresi
 *       sonuç kümesine GİRMİYOR; kontrolü unutmak mümkün değil, çünkü
 *       kontrol sorgunun kendisi.
 *
 * `search_path` ile kiracılıkta kullandığımız ilkenin aynısı: yanlış veriyi
 * "sonra ayıklamak" yerine ERİŞİLEMEZ kılmak.
 *
 * ⚠️ Bulunamayan adres 404 dönüyor, 403 değil. 403 "böyle bir adres VAR ama
 * senin değil" demek olurdu ve saldırgana varlık bilgisi verirdi. 404 hiçbir
 * şey söylemiyor — ayrıca yukarıdaki yöntemin doğal sonucu.
 */
class AddressController extends Controller
{
    /** Müşterinin kendi adresleri, yenisi üstte. */
    public function index(Request $istek): JsonResponse
    {
        return response()->json([
            'addresses' => $this->musteri($istek)->addresses()->latest('id')->get(),
        ]);
    }

    public function store(AddressRequest $istek): JsonResponse
    {
        /*
        | ⚠️ `Address::create([...'customer_id' => ...])` DEĞİL.
        |
        | İlişki üzerinden oluşturulunca `customer_id` Eloquent tarafından
        | konuyor; istekten gelen bir değerle başkasının defterine yazmak
        | mümkün olmuyor. `customer_id` zaten `$fillable` dışında — bu ikinci
        | savunma hattı.
        */
        $adres = $this->musteri($istek)->addresses()->create($istek->validated());

        return response()->json(['address' => $adres], 201);
    }

    public function update(AddressRequest $istek, string $adres): JsonResponse
    {
        $kayit = $this->sahipOl($istek, $adres);
        $kayit->update($istek->validated());

        return response()->json(['address' => $kayit]);
    }

    public function destroy(Request $istek, string $adres): JsonResponse
    {
        /*
        | Yumuşak silme (`SoftDeletes`): satır kalıyor, `deleted_at`
        | doluyor. Sert silinseydi bu adresi kullanan geçmiş bir kaydın
        | izini sürmek imkânsızlaşırdı. Sipariş zaten adresi KOPYALIYOR
        | (domain-model §7), yani sipariş bundan etkilenmiyor — ama defteri
        | geri getirebilmek müşteri desteği için değerli.
        */
        $this->sahipOl($istek, $adres)->delete();

        return response()->json(['message' => 'Adres silindi.']);
    }

    /**
     * Giriş yapmış müşteri.
     *
     * Tip kontrolü ikinci savunma hattı: rota zaten `auth:customer`
     * arkasında, ama bir gün biri onu düşürürse burada duruyoruz.
     * Personelin `addresses()` ilişkisi bile yok.
     */
    private function musteri(Request $istek): Customer
    {
        $kullanici = $istek->user();

        abort_unless($kullanici instanceof Customer, 401);

        return $kullanici;
    }

    /**
     * Uuid'yi MÜŞTERİYE DARALTILMIŞ sorgudan çözer. Bulunamazsa 404.
     *
     * ⚠️ Laravel'in örtük rota bağlaması (`Address $adres`) BİLEREK
     * kullanılmıyor. O, uuid'yi TÜM tabloda arar; başkasının satırı bulunur
     * ve belleğe gelir. Sonradan kontrol etsek bile "hiç yükleme" ilkesini
     * çiğnemiş olurduk — ve o satır bir gün bir hata mesajına, bir loga ya
     * da bir `dd()`'ye düşerdi.
     *
     * Rotada `->scopeBindings()` da kullanılabilirdi; tercih edilmedi çünkü
     * rota dosyasına yazılan emniyet yeni uç eklenirken gözden kaçabiliyor.
     * Burada her yol aynı kapıdan geçiyor.
     */
    private function sahipOl(Request $istek, string $uuid): Address
    {
        return $this->musteri($istek)->addresses()->where('uuid', $uuid)->firstOrFail();
    }
}
