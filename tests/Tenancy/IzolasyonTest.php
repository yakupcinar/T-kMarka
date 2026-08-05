<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| Bu dosya 0.5'in ASIL ÇIKTISI: iki markanın verisinin karışmadığının
| kanıtı. Şimdiye kadar elle (curl, psql) doğruladık — buradan sonra
| bilgisayar her değişiklikte kendisi kontrol edecek.
*/

it('her kiraci icin ayri sema olusuyor', function () {
    $a = kiraciOlustur('marka-a.test');
    $b = kiraciOlustur('marka-b.test');

    $semalar = DB::table('pg_namespace')
        ->whereIn('nspname', [$a->database()->getName(), $b->database()->getName()])
        ->count();

    expect($semalar)->toBe(2)
        ->and($a->database()->getName())->not->toBe($b->database()->getName());
});

it('marka tablolari her semada ayri ayri kuruluyor', function () {
    $a = kiraciOlustur('marka-a.test');

    tenancy()->initialize($a);

    // tenant/ klasöründeki migration çalışmış olmalı
    expect(DB::getSchemaBuilder()->hasTable('customers'))->toBeTrue();
});

it('bir kiracida yazilan kayit digerinden GORUNMUYOR', function () {
    $a = kiraciOlustur('marka-a.test');
    $b = kiraciOlustur('marka-b.test');

    tenancy()->initialize($a);
    DB::table('customers')->insert([
        'uuid' => (string) Str::uuid(),
        'name' => 'A markasinin musterisi',
        'email' => 'ayni@adres.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('customers')->count())->toBe(1);

    tenancy()->initialize($b);

    expect(DB::table('customers')->count())->toBe(0);
});

it('ayni e-posta iki markada ayri ayri kullanilabiliyor', function () {
    $a = kiraciOlustur('marka-a.test');
    $b = kiraciOlustur('marka-b.test');

    // customers.email UNIQUE — ama kısıt ŞEMA İÇİNDE geçerli.
    // Aynı e-posta iki farklı markaya kayıt olabilmeli.
    foreach ([$a, $b] as $kiraci) {
        tenancy()->initialize($kiraci);
        DB::table('customers')->insert([
            'uuid' => (string) Str::uuid(),
            'name' => 'Ayni Kisi',
            'email' => 'ayni@adres.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    tenancy()->initialize($a);
    expect(DB::table('customers')->count())->toBe(1);

    tenancy()->initialize($b);
    expect(DB::table('customers')->count())->toBe(1);
});

it('ayni cache anahtari iki kiracida FARKLI deger donuyor', function () {
    $a = kiraciOlustur('marka-a.test');
    $b = kiraciOlustur('marka-b.test');

    tenancy()->initialize($a);
    Cache::put('ayni-anahtar', 'A-degeri', 60);

    tenancy()->initialize($b);
    Cache::put('ayni-anahtar', 'B-degeri', 60);

    expect(Cache::get('ayni-anahtar'))->toBe('B-degeri');

    tenancy()->initialize($a);
    expect(Cache::get('ayni-anahtar'))->toBe('A-degeri');

    tenancy()->end();
    expect(Cache::get('ayni-anahtar'))->toBeNull();
});

it('ayni dosya adi iki kiracida ayri klasore yaziliyor', function () {
    $a = kiraciOlustur('marka-a.test');
    $b = kiraciOlustur('marka-b.test');

    tenancy()->initialize($a);
    Storage::disk('local')->put('ayni-dosya.txt', 'A icerigi');

    tenancy()->initialize($b);
    Storage::disk('local')->put('ayni-dosya.txt', 'B icerigi');

    expect(Storage::disk('local')->get('ayni-dosya.txt'))->toBe('B icerigi');

    tenancy()->initialize($a);
    expect(Storage::disk('local')->get('ayni-dosya.txt'))->toBe('A icerigi');

    tenancy()->end();
    expect(Storage::disk('local')->exists('ayni-dosya.txt'))->toBeFalse();
});

it('kiracidan cikinca merkez baglama donuluyor', function () {
    $a = kiraciOlustur('marka-a.test');

    $merkezSema = DB::connection()->getConfig('search_path');

    tenancy()->initialize($a);
    expect(tenancy()->initialized)->toBeTrue();

    tenancy()->end();

    expect(tenancy()->initialized)->toBeFalse()
        ->and(DB::connection()->getConfig('search_path'))->toBe($merkezSema);
});
