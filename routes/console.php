<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Zamanlanmış görevler
|--------------------------------------------------------------------------
|
| ⚠️ KURAL — MARKA VERİSİNE DOKUNAN HER GÖREV `tenants:run` İLE ÇALIŞIR.
|
| Zamanlayıcı bir istekten doğmaz: ortada alan adı yoktur, dolayısıyla kiracı
| çözümleyen middleware hiç devreye girmez. Doğrudan yazılan bir görev MERKEZ
| bağlamda koşar ve hiçbir markanın verisine ulaşamaz — hata da vermez,
| sessizce hiçbir şey yapmaz. (docs/pre-setup.md M-2.4)
|
|   YANLIŞ   Schedule::command('stok:rezervasyon-temizle')
|                    ->everyFiveMinutes();
|            → merkez şemada koşar, hiçbir markanın rezervasyonunu temizlemez
|
|   DOĞRU    Schedule::command('tenants:run stok:rezervasyon-temizle')
|                    ->everyFiveMinutes();
|            → kiracı listesi üzerinde döner, her markanın şemasında ayrı koşar
|
| Görevleri tetikleyen süreç: docker-compose.yml → `scheduler` servisi.
| Laravel'in zamanlayıcısı kendi kendine çalışmaz.
|
*/

// Merkez bakım — marka verisine dokunmuyor, bu yüzden tenants:run gerekmiyor.
Schedule::command('queue:prune-failed --hours=168')->weekly();

/*
| STOK REZERVASYONU — süresi dolanları düşür. (1D-K3)
|
| 15 dakikalık rezervasyonlar için 5 dakikada bir yeterli: en kötü ihtimalle
| stok 20 dakika bağlı kalıyor. Daha sık koşmak veritabanını boşuna yorar.
|
| ⚠️ `withoutOverlapping()` — DAĞITIK KİLİT.
|
| Birden çok `scheduler` konteyneri varsa (ölçeklenince olacak) görev İKİ
| KEZ koşar. Burada veritabanı satır kilidi yetmiyor çünkü korunan şey bir
| satır değil, GÖREVİN KENDİSİ. Laravel bunu cache kilidiyle çözüyor;
| bizde cache Redis ve kiracı ETİKETLİ (0.5).
|
| ★ Kilit sorusunun cevabı burada ikiye ayrılıyor:
|     stok sayacı  → veritabanı satır kilidi (kaynak orada)
|     görev tekilliği → dağıtık kilit (korunan şey veritabanında değil)
*/
Schedule::command('tenants:run stok:rezervasyon-temizle')
    ->everyFiveMinutes()
    ->withoutOverlapping();

/*
| STOK SAYACI DENETİMİ — gecelik. (1D-K1)
|
| `committed` kolonu materyalleştirilmiş bir sayaç; aktif rezervasyonların
| toplamına eşit olmak ZORUNDA. Denetim onarmıyor, yalnızca haber veriyor —
| kendiliğinden düzeltilseydi sayacı bozan kod yolu hiç görünmezdi.
*/
Schedule::command('tenants:run stok:sayac-denetle')
    ->dailyAt('03:30')
    ->withoutOverlapping();
