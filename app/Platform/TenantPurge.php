<?php

namespace App\Platform;

use App\Enums\TenantStatus;
use App\Platform\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Kapatılmış markaların kalıcı silinmesi. (3G)
 *
 * ★ 7 NUMARALI KARAR: kapatılan marka 1 YIL dokunulmadan saklanıyor, sonra
 * şeması siliniyor. Süre sözleşmede yazılı olmak ZORUNDA (KVKK: veri
 * işleyen, sözleşme bitince veriyi iade edip siler).
 *
 * ⚠️ BU SINIFTAKİ HER İŞLEM GERİ ALINAMAZ. Projedeki diğer bütün
 * "tehlikeli" işlemler geri alınabilirdi; bu değil. Bu yüzden burada
 * varsayılan **hiçbir şey yapmamak**: silmek için açık onay gerekiyor.
 */
class TenantPurge
{
    /**
     * Kapatmadan sonra verinin saklandığı süre.
     *
     * ⚠️ Sözleşmede yazılı olmalı. KVKK'ya göre veri işleyen, hizmet
     * sözleşmesi bitince veriyi iade edip siler; belirsiz süreli saklama
     * savunulamaz. Kurul kararında bu gerekçeyle ceza verilmiş.
     */
    public const SAKLAMA_GUN = 365;

    /**
     * Silinme zamanı gelmiş markalar.
     *
     * @return list<Tenant>
     */
    public function silinecekler(): array
    {
        /*
        | ⚠️ ÜÇ ŞART DA ZORUNLU ve üçü de ayrı bir felaketi engelliyor:
        |
        |   status = closed      → askıdaki ya da ödeyen marka SİLİNMESİN
        |   closed_at NOT NULL   → ⚠️ EN KRİTİĞİ (aşağıda)
        |   closed_at <= sınır   → süresi dolmamış marka silinmesin
        |
        | ★ `whereNotNull` BUGÜN ÖLÜ — ve bu ÖLÇÜLDÜ, kaldırıldığında
        | hiçbir test düşmedi. Sebep PostgreSQL'in NULL semantiği:
        |
        |     SELECT (NULL::timestamptz <= now())  →  NULL
        |
        | `NULL` "doğru" sayılmadığı için satır zaten `WHERE`'den düşüyor.
        |
        | ⚠️ YİNE DE DURUYOR ve bu 2F/3E'deki "ölü savunmayı kaldır"
        | kararından BİLİNÇLİ bir sapma. Fark şu:
        |   2F  kolon `NOT NULL`'dı → senaryo İMKÂNSIZDI
        |   3E  başka bir yer zaten koruyordu → gerçek koruma oradaydı
        |   3G  senaryo MÜMKÜN (kolon nullable), koruma DOLAYLI
        |
        | Yani burada koruma "SQL'in NULL davranışını bilmene" bağlı. Açık
        | yazmak hem okunabilirlik hem de geri ALINAMAZ bir işlemde ikinci
        | kapı. `closed_at` 3B'de sonradan eklendi ve mevcut markalarda
        | boş — durumu elle `closed` yapılan bir marka bu şart olmadan
        | "beklemeye alınan" değil İLK KOŞUDA SİLİNEN olurdu.
        |
        | (2C ve 2F'deki "sonradan eklenen kolon" dersinin üçüncüsü —
        | orada sessiz eksiklik ve sessiz saldırıydı, burada sessiz YIKIM.)
        */
        $sinir = now()->subDays(self::SAKLAMA_GUN);

        /** @var list<Tenant> $markalar */
        $markalar = Tenant::query()
            ->where('status', TenantStatus::Closed)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', $sinir)
            ->orderBy('id')
            ->get()
            ->all();

        return $markalar;
    }

    /**
     * Bir markayı KALICI olarak siler: şema + dosyalar + merkez kayıt.
     *
     * ⚠️ GERİ ALINAMAZ.
     */
    public function sil(Tenant $marka): void
    {
        $kimlik = (string) $marka->id;
        $ad = (string) ($marka->name ?? '?');

        /*
        | ★ ÖNCE GÜNLÜĞE, sonra silme.
        |
        | ⚠️ Sonra yazılsaydı silme sırasında bir hata olduğunda hangi
        | markanın silinmeye çalışıldığı hiçbir yerde kalmazdı. Silinen
        | verinin tek izi bu satır.
        */
        Log::warning('Marka KALICI olarak siliniyor', [
            'tenant' => $kimlik,
            'name' => $ad,
            'closed_at' => $marka->closed_at?->toIso8601String(),
        ]);

        /*
        | ⚠️ Dosyalar ÖNCE siliniyor. Kayıt önce silinseydi ve dosya silme
        | patlasaydı klasör ÖKSÜZ kalırdı — hangi markaya ait olduğu
        | artık bilinemezdi. Bugün diskte tam bu durumdan 38 öksüz klasör
        | var (ölçüldü).
        */
        $this->dosyalariSil($kimlik);

        /*
        | `delete()` paketin olay zincirini tetikliyor ve ŞEMAYI da
        | düşürüyor. Merkez kayıt da bu çağrıyla gidiyor.
        |
        | ⚠️ Şema düşmeden kayıt silinseydi şema ÖKSÜZ kalırdı: hiçbir
        | yerden erişilemeyen ama diskte yer kaplayan bir veri yığını.
        */
        $marka->delete();
    }

    /**
     * Veritabanında karşılığı olmayan `storage/tenant<uuid>` klasörleri.
     *
     * ★ 1A'dan devredilen borç. `tenant:create` yarıda kaldığında ya da
     * marka elle silindiğinde klasör diskte kalıyor.
     *
     * ⚠️ ÖLÇÜLDÜ: 40 klasör, 2 gerçek marka — yani 38 öksüz.
     *
     * ⚠️ `$kok` PARAMETRESİ TESTTEN DOĞDU ve gerçek bir hasardan sonra
     * eklendi: test `--onayla` ile komutu çalıştırdı ve GELİŞTİRME
     * ortamındaki gerçek marka klasörlerini SİLDİ (3 ürün görseli). Test
     * ile uygulama aynı `storage/` klasörünü paylaşıyor.
     *
     * Artık test kendi geçici klasörünü veriyor; komut varsayılanı
     * kullanıyor.
     *
     * @return list<string> tam yollar
     */
    public function oksuzKlasorler(?string $kok = null): array
    {
        $kok ??= storage_path();
        $klasorler = File::directories($kok);

        /*
        | ⚠️ Marka kimlikleri BİR KEZ okunuyor. Her klasör için ayrı sorgu
        | yazılsaydı 40 klasörde 40 sorgu olurdu; binlerce markada bu
        | komut kullanılamaz hâle gelirdi.
        */
        $mevcut = Tenant::query()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $oksuz = [];

        foreach ($klasorler as $yol) {
            $ad = basename($yol);

            /*
            | ⚠️ Yalnızca `tenant` ile başlayanlar. Bu kontrol olmadan
            | `storage/app`, `storage/logs` ve `storage/framework` de
            | "öksüz" sayılır ve SİLİNİRDİ.
            */
            if (! str_starts_with($ad, 'tenant')) {
                continue;
            }

            $kimlik = substr($ad, strlen('tenant'));

            if (! in_array($kimlik, $mevcut, true)) {
                $oksuz[] = $yol;
            }
        }

        return $oksuz;
    }

    /** Bir markanın dosya klasörünü siler. */
    private function dosyalariSil(string $kimlik): void
    {
        /*
        | ⚠️ Kimlik BOŞ olamaz — boş olsaydı yol `storage/tenant` olur ve
        | yanlış bir klasör silinebilirdi.
        */
        if (trim($kimlik) === '') {
            return;
        }

        $yol = storage_path('tenant'.$kimlik);

        if (File::isDirectory($yol)) {
            File::deleteDirectory($yol);
        }
    }
}
