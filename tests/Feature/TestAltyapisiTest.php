<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('veritabanina kayit yazabiliyor', function () {
    User::factory()->create(['email' => 'deneme@tikmarka.test']);

    expect(User::count())->toBe(1);
});

it('bir onceki testin verisi geri alinmis oluyor', function () {
    expect(User::count())->toBe(0);
});

it('testler postgresql uzerinde kosuyor', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('tikmarka_test');
});
