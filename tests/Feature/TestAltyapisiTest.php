<?php

use Illuminate\Support\Facades\DB;

/*
| Test ALTYAPISININ çalıştığını doğrular — kiracılığı değil.
| Kiracı izolasyon testleri 0.5/8'de, kendi düzeniyle yazılacak.
*/

it('merkez veritabanina kayit yazabiliyor', function () {
    DB::table('cache')->insert([
        'key' => 'deneme',
        'value' => 'x',
        'expiration' => time() + 60,
    ]);

    expect(DB::table('cache')->count())->toBe(1);
});

it('bir onceki testin verisi geri alinmis oluyor', function () {
    expect(DB::table('cache')->count())->toBe(0);
});

it('testler postgresql uzerinde kosuyor', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('tikmarka_test');
});
