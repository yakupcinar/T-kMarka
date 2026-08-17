<?php

use App\Enums\TenantStatus;
use App\Platform\Models\Tenant;
use App\Platform\TenantPurge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
| Marka yaşam döngüsünün sonu (3G).
|
| ★ DÖRT İDDİA:
|   1  saklama süresi DOLMADAN hiçbir marka silinmiyor
|   2  `closed_at` BOŞ olan marka ASLA silinmiyor
|   3  silme onaysız ÇALIŞMIYOR — geri alınamaz işlem
|   4  öksüz dosya klasörleri temizleniyor, gerçek olanlar KALIYOR
|
| ⚠️ Bu dosyadaki her şey GERİ ALINAMAZ bir işlemi ölçüyor. Projedeki
| diğer tehlikeli işlemler geri alınabilirdi.
*/

/** Kapatılmış bir marka üretir; `gunOnce` kapatılma tarihini geriye çeker. */
function kapaliMarka(string $ad, ?int $gunOnce): Tenant
{
    tenancy()->end();

    $marka = Tenant::create(['name' => $ad, 'status' => TenantStatus::Closed]);

    /*
    | ⚠️ `closed_at` DOĞRUDAN yazılıyor: `null` verilebilsin diye. Servis
    | üzerinden gidilseydi bu senaryo hiç kurulamaz ve en tehlikeli iddia
    | (boş tarihli marka silinmiyor) ölçülemezdi.
    */
    DB::connection('pgsql')->table('tenants')->where('id', $marka->id)->update([
        'closed_at' => $gunOnce === null ? null : now()->subDays($gunOnce),
    ]);

    return $marka->refresh();
}

it('★ SÜRESİ DOLMAMIŞ marka silinmiyor', function () {
    $yeni = kapaliMarka('Yeni Kapanmış', 30);
    $eski = kapaliMarka('Süresi Dolmuş', TenantPurge::SAKLAMA_GUN + 1);

    $silinecekler = collect(app(TenantPurge::class)->silinecekler())->pluck('id')->all();

    /*
    | ⚠️ 1 yıl dolmadan silinseydi "fikrimi değiştirdim" diyen marka
    | verisini geri alamazdı — ve bu geri ALINAMAZ.
    */
    expect($silinecekler)->toContain($eski->id)
        ->and($silinecekler)->not->toContain($yeni->id);

    $yeni->delete();
    $eski->delete();
});

it('★ closed_at BOŞ olan marka ASLA silinmiyor — en tehlikeli senaryo', function () {
    $tarihsiz = kapaliMarka('Tarihsiz Kapalı', null);

    /*
    | ★ EN TEHLİKELİ SENARYO.
    |
    | `closed_at` kolonu 3B'de SONRADAN eklendi, yani mevcut bütün
    | markalarda `null`. PostgreSQL'de `NULL <= tarih` sonucu `NULL` ve
    | satır düşüyor — yani bugün ŞANS ESERİ güvendeyiz.
    |
    | ⚠️ KIRMA DENEMESİ ÖLÇTÜ: `whereNotNull` kaldırıldığında bu test
    | YEŞİL kalıyor — koruma bugün SQL'in NULL semantiğinden geliyor
    | (`NULL <= tarih` → `NULL`, satır düşüyor). Şart yine de duruyor:
    | gerekçesi [TenantPurge]'de yazılı ve geri ALINAMAZ bir işlemde
    | ikinci kapı.
    |
    | Bu test korumanın KAYNAĞINI değil DAVRANIŞINI kilitliyor: biri
    | sorguyu değiştirip tarih karşılaştırmasını kaldırırsa yakalar.
    |
    | (2C: sessiz eksiklik · 2F: sessiz saldırı · burada sessiz YIKIM.)
    */
    $silinecekler = collect(app(TenantPurge::class)->silinecekler())->pluck('id')->all();

    expect($silinecekler)->not->toContain($tarihsiz->id);

    $tarihsiz->delete();
});

it('★ KAPALI OLMAYAN marka silinmiyor — durumu ne olursa olsun', function () {
    tenancy()->end();

    $durumlar = [TenantStatus::Active, TenantStatus::Trial, TenantStatus::Suspended, TenantStatus::PastDue];
    $idler = [];

    foreach ($durumlar as $durum) {
        $marka = Tenant::create(['name' => 'Durum '.$durum->value, 'status' => $durum]);

        /*
        | ⚠️ `closed_at` DOLU ama durum kapalı DEĞİL. Sorgu yalnızca
        | tarihe baksaydı ödeyen bir marka silinirdi — kapatılıp geri
        | açılan her marka bu duruma düşüyor.
        */
        DB::connection('pgsql')->table('tenants')->where('id', $marka->id)
            ->update(['closed_at' => now()->subDays(TenantPurge::SAKLAMA_GUN + 10)]);

        $idler[] = $marka->id;
    }

    $silinecekler = collect(app(TenantPurge::class)->silinecekler())->pluck('id')->all();

    foreach ($idler as $id) {
        expect($silinecekler)->not->toContain($id);
    }

    Tenant::whereIn('id', $idler)->get()->each->delete();
});

it('★ ONAYSIZ komut HİÇBİR ŞEY silmiyor', function () {
    $marka = kapaliMarka('Onaysız Deneme', TenantPurge::SAKLAMA_GUN + 5);

    /*
    | ★ Diğer komutlarımızda kuru çalışma AYRI bir bayraktı (3A) çünkü
    | yaptıkları iş geri alınabilirdi. Burada tersi: silme geri
    | ALINAMAZ, bu yüzden güvenli taraf VARSAYILAN.
    */
    $this->artisan('marka:silinecekleri-temizle')->assertExitCode(0);

    expect(Tenant::find($marka->id))->not->toBeNull();

    $marka->delete();
});

it('★ ONAYLI komut GERÇEKTEN siliyor — şema ve kayıt birlikte', function () {
    $marka = kapaliMarka('Silinecek', TenantPurge::SAKLAMA_GUN + 5);
    $kimlik = (string) $marka->id;

    $semaVar = fn (): bool => DB::connection('pgsql')->select(
        'SELECT 1 FROM information_schema.schemata WHERE schema_name = ?',
        ['tenant'.$kimlik],
    ) !== [];

    $this->artisan('marka:silinecekleri-temizle --onayla')->assertExitCode(0);

    /*
    | ⚠️ ŞEMA da gitmeli. Yalnızca kayıt silinseydi şema ÖKSÜZ kalırdı:
    | hiçbir yerden erişilemeyen ama diskte yer kaplayan veri yığını.
    */
    expect(Tenant::find($kimlik))->toBeNull()
        ->and($semaVar())->toBeFalse();
});

it('★ ÖKSÜZ klasör bulunuyor, GERÇEK marka klasörüne dokunulmuyor', function () {
    $marka = markaKur('yasam-a.test');
    tenancy()->end();

    /*
    | ★ GEÇİCİ KÖK — ve bu bir HASARDAN sonra eklendi.
    |
    | ⚠️ İlk yazımda test gerçek `storage/` üzerinde çalışıyordu ve
    | `--onayla` ile komutu koşturunca GELİŞTİRME ORTAMINDAKİ gerçek
    | marka klasörlerini SİLDİ (3 ürün görseli gitti, veritabanı kaydı
    | kaldı). Test ile uygulama aynı klasörü paylaşıyor.
    |
    | Artık test kendi geçici kökünde çalışıyor.
    */
    $kok = storage_path('test-oksuz-'.uniqid());
    File::ensureDirectoryExists($kok);

    $gercekYol = $kok.'/tenant'.$marka['tenant']->id;
    $oksuzYol = $kok.'/tenant00000000-0000-0000-0000-00000000dead';

    File::ensureDirectoryExists($gercekYol);
    File::ensureDirectoryExists($oksuzYol);

    $oksuzler = app(TenantPurge::class)->oksuzKlasorler($kok);

    /*
    | ★ 1A'DAN DEVREDİLEN BORÇ. Ölçüldü: 40 klasör, 2 gerçek marka.
    |
    | ⚠️ Gerçek markanın klasörü listeye girseydi bu komut çalışan bir
    | markanın ürün görsellerini SİLERDİ.
    */
    expect($oksuzler)->toContain($oksuzYol)
        ->and($oksuzler)->not->toContain($gercekYol);

    File::deleteDirectory($kok);
});

it('★ SİSTEM klasörleri öksüz sayılmıyor', function () {
    tenancy()->end();

    /*
    | ⚠️ GEÇİCİ KÖKTE, gerçek `storage/` üzerinde DEĞİL — gerçek üzerinde
    | çalışan ilk sürüm `storage/framework`'ü de silmişti.
    */
    $kok = storage_path('test-sistem-'.uniqid());

    foreach (['app', 'logs', 'framework'] as $sistem) {
        File::ensureDirectoryExists($kok.'/'.$sistem);
    }

    $oksuzler = app(TenantPurge::class)->oksuzKlasorler($kok);

    /*
    | ⚠️ `tenant` ön ek kontrolü olmasaydı `storage/app`, `storage/logs` ve
    | `storage/framework` de "öksüz" sayılır ve SİLİNİRDİ — uygulamanın
    | kendi çalışma klasörleri.
    */
    foreach (['app', 'logs', 'framework'] as $sistem) {
        expect($oksuzler)->not->toContain($kok.'/'.$sistem);
    }

    expect($oksuzler)->toBe([]);

    File::deleteDirectory($kok);
});

it('★ ÖKSÜZ TEMİZLİK onaysız hiçbir şey silmiyor', function () {
    tenancy()->end();

    /*
    | ⚠️ `--kok` ile GEÇİCİ klasör veriliyor. Verilmeseydi bu test gerçek
    | `storage/` üzerinde çalışır ve geliştirme ortamındaki marka
    | dosyalarını silerdi — ilk yazımda tam bu oldu.
    */
    $kok = storage_path('test-temizlik-'.uniqid());
    $oksuzYol = $kok.'/tenant00000000-0000-0000-0000-0000000000aa';

    File::ensureDirectoryExists($oksuzYol);

    $this->artisan('marka:oksuz-dosyalari-temizle', ['--kok' => $kok])->assertExitCode(0);

    expect(File::isDirectory($oksuzYol))->toBeTrue();

    $this->artisan('marka:oksuz-dosyalari-temizle', ['--kok' => $kok, '--onayla' => true])->assertExitCode(0);

    expect(File::isDirectory($oksuzYol))->toBeFalse();

    File::deleteDirectory($kok);
});

it('★ MARKA SİLİNİNCE dosyaları da gidiyor', function () {
    $marka = kapaliMarka('Dosyalı Marka', TenantPurge::SAKLAMA_GUN + 5);

    $yol = storage_path('tenant'.$marka->id);
    File::ensureDirectoryExists($yol);
    File::put($yol.'/ornek.txt', 'veri');

    app(TenantPurge::class)->sil($marka);

    /*
    | ⚠️ Dosyalar kalsaydı silinen markanın ürün görselleri ve
    | müşterilerine ait yüklenmiş dosyalar diskte kalmaya devam ederdi —
    | KVKK açısından "sildik" demek yalan olurdu.
    */
    expect(File::isDirectory($yol))->toBeFalse();
});

it('★ tenant:delete ONAYSIZ silmiyor, ONAYLI siliyor', function () {
    $marka = markaKur('yasam-b.test');
    tenancy()->end();

    $kimlik = (string) $marka['tenant']->id;

    $this->artisan('tenant:delete', ['alan-adi' => 'yasam-b.test'])->assertExitCode(0);

    expect(Tenant::find($kimlik))->not->toBeNull();

    $this->artisan('tenant:delete', ['alan-adi' => 'yasam-b.test', '--onayla' => true])->assertExitCode(0);

    expect(Tenant::find($kimlik))->toBeNull();
});

it('★ tenant:delete ve zamanlanmış temizlik AYNI yolu kullanıyor', function () {
    /*
    | ⚠️ İki ayrı silme yolu yazılsaydı biri dosyaları unuturdu ve öksüz
    | klasör bırakırdı — bugün diskte tam bundan 38 tane var (1A'dan
    | devredilen borç, ölçüldü).
    |
    | Bu test YAPI ölçüyor: komut kendi silme mantığını yazmıyor.
    */
    $kaynak = (string) file_get_contents(base_path('app/Tenancy/Commands/DeleteTenant.php'));

    expect($kaynak)->toContain('TenantPurge')
        ->and($kaynak)->not->toContain('deleteDirectory');
});

it('★ SAKLAMA SÜRESİ 1 yıl — sözleşmeye yazılacak değer', function () {
    /*
    | ⚠️ Süre KODDA SABİT ve sözleşmede yazılı olmak zorunda. KVKK'ya göre
    | veri işleyen, hizmet sözleşmesi bitince veriyi iade edip siler;
    | belirsiz süreli saklama savunulamaz.
    */
    expect(TenantPurge::SAKLAMA_GUN)->toBe(365);
});
