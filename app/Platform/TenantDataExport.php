<?php

namespace App\Platform;

use App\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Markanın BÜTÜN verisinin dışa aktarımı. (4F)
 *
 * ★ FAZ 3'TEN DEVREDİLEN BORÇ. 7 numaralı kararın "kapanışta verini
 * indir" parçası Faz 3'te yapılamamıştı ve kapanış özetine açıkça
 * "yapılmadı" diye yazılmıştı. Burada kapanıyor.
 *
 * ⚠️ KVKK açısından: veri işleyen, sözleşme bitince veriyi İADE EDİP
 * siler. Silme 3G'de vardı, İADE yoktu — yani yükümlülüğün yarısı
 * eksikti.
 *
 * ⚠️ Bu sınıf `app/Domain/` altında DEĞİL: markanın şemasını dışarıdan
 * açıp okuyor, yani "hangi kiracıdayım" sorusunu soruyor. M-2.7 gereği
 * Domain katmanı bunu bilemez.
 */
class TenantDataExport
{
    /**
     * Dışa aktarılan tablolar ve okuma sırası.
     *
     * ⚠️ LİSTE AÇIK YAZILI, "bütün tabloları tara" DEĞİL. Otomatik
     * tarama yeni bir tablo eklendiğinde onu da dökerdi — dahili
     * sayaçlar, kuyruk kayıtları, oturum verisi dâhil. Marka kendi
     * verisini istiyor, bizim iç kayıtlarımızı değil.
     *
     * ⚠️ `personal_access_tokens` BİLEREK YOK: aktif oturum jetonları
     * dosyaya yazılsaydı, dosyayı gören herkes markanın API'sine
     * girebilirdi.
     *
     * @var list<string>
     */
    public const TABLOLAR = [
        'settings',
        'legal_document_versions',
        'categories',
        'products',
        'product_variants',
        'product_images',
        'product_options',
        'collections',
        'customers',
        'addresses',
        'orders',
        'order_items',
        'fulfillments',
        'fulfillment_items',
        'payments',
        'returns',
        'return_items',
        'refunds',
        'coupons',
        'reviews',
        'events',
    ];

    /**
     * DÖKÜMDEN ÇIKARILAN kolonlar — tablo fark etmeksizin.
     *
     * ★ GERÇEK KOŞUDA BULUNDU (4F). Döküm alındıktan sonra dosyanın
     * içine bakınca `customers.password` kolonunda **bcrypt hash'leri**
     * göründü: müşteriler hesap açabiliyor (M-1) ve parolaları bu
     * tabloda duruyor.
     *
     * ⚠️ Tablo listesini daraltmak yetmiyordu — sorun tablonun kendisi
     * değil, İÇİNDEKİ KOLONDU. Marka müşteri listesini almalı; müşteri
     * parolalarını almamalı.
     *
     * ⚠️ Kimlik bilgisi İŞ VERİSİ DEĞİLDİR: markanın taşıyacağı şey
     * "kim müşterim" bilgisi, "müşterim hangi parolayı kullanıyor"
     * bilgisi değil.
     *
     * @var list<string>
     */
    public const HASSAS_KOLONLAR = [
        'password',
        'remember_token',
        'token',
        'secret',
        'api_key',
    ];

    /**
     * Markanın verisini dizi olarak döker.
     *
     * ⚠️ Kiracı bağlamı ÇAĞIRAN tarafından açılmış olmalı.
     *
     * @return array<string, mixed>
     */
    public function dokum(Tenant $marka): array
    {
        $veri = [
            'tenant' => [
                'id' => (string) $marka->id,
                'name' => $marka->name,
                'status' => $marka->status?->value,
                'created_at' => $marka->created_at->toIso8601String(),
            ],

            /*
            | ⚠️ Dökümün NE ZAMAN alındığı yazılıyor. Yazılmasaydı marka
            | elindeki dosyanın hangi ana ait olduğunu bilemez, eski bir
            | dökümü güncel sanabilirdi.
            */
            'exported_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        foreach (self::TABLOLAR as $tablo) {
            /*
            | ⚠️ Olmayan tablo ATLANIYOR, hata verilmiyor. Marka şemaları
            | farklı migration seviyelerinde olabilir; tek eksik tablo
            | yüzünden bütün dökümün başarısız olması, markayı verisiz
            | bırakırdı.
            */
            if (! DB::getSchemaBuilder()->hasTable($tablo)) {
                continue;
            }

            $veri['tables'][$tablo] = DB::table($tablo)->get()->map(
                fn (object $satir) => $this->temizle((array) $satir),
            )->all();
        }

        return $veri;
    }

    /**
     * Satırdan hassas kolonları çıkarır.
     *
     * ⚠️ Kolon SİLİNİYOR, boşaltılmıyor. Boş bırakılsaydı dosyayı okuyan
     * "parola yok" mu "parola boş" mu ayıramazdı; ayrıca içe aktarma
     * yapan bir sistem boş parolayı geçerli sanabilirdi.
     *
     * ⚠️ ŞİFRELİ AYAR DEĞERLERİ de çıkarılıyor: ödeme sağlayıcısının
     * gizli anahtarı `settings` tablosunda şifreli duruyor. Şifreli
     * olması dosyaya konabileceği anlamına gelmiyor — dosya uygulama
     * anahtarıyla birlikte sızarsa çözülebilir.
     *
     * @param  array<string, mixed>  $satir
     * @return array<string, mixed>
     */
    private function temizle(array $satir): array
    {
        foreach (self::HASSAS_KOLONLAR as $kolon) {
            unset($satir[$kolon]);
        }

        if (($satir['is_encrypted'] ?? false) === true || ($satir['is_encrypted'] ?? 0) === 1) {
            unset($satir['value']);
        }

        return $satir;
    }

    /**
     * İndirilecek dosyanın adı.
     *
     * ⚠️ Marka ADI dosya adına KONMUYOR: ad Türkçe karakter, boşluk ya da
     * `/` içerebilir ve indirme başlığını bozar. Kimlik + tarih yeterli
     * ve tartışmasız.
     */
    public function dosyaAdi(Tenant $marka): string
    {
        return sprintf('tikmarka-%s-%s.json', $marka->id, now()->format('Ymd-His'));
    }
}
