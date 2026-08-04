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
