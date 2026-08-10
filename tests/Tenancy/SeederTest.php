<?php

use App\Models\Address;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\TenantDemoSeeder;

it('gösterim verisini kuruyor ve TEKRAR çalıştırılabiliyor', function () {
    markaKur('tohum-a.test');

    (new TenantDemoSeeder)->run();

    // sahip + katalog + destek
    expect(User::count())->toBe(3)
        ->and(Customer::count())->toBe(2)
        ->and(Address::count())->toBe(2);

    // ⚠️ İkinci çalıştırma kopya üretmemeli. `firstOrCreate` olmasaydı ya
    // e-posta benzersizliğinde patlardı ya da kopya müşteri açardı.
    (new TenantDemoSeeder)->run();

    expect(User::count())->toBe(3)
        ->and(Customer::count())->toBe(2)
        ->and(Address::count())->toBe(2);
});

it('personeli gerçekten farklı rollere bağlıyor', function () {
    markaKur('tohum-b.test');
    (new TenantDemoSeeder)->run();

    $katalogcu = User::where('email', 'katalog@ornek.test')->firstOrFail();
    $destekci = User::where('email', 'destek@ornek.test')->firstOrFail();

    // 1B'de ürün eklerken yetkili personel hazır bulunsun diye.
    expect($katalogcu->hasPermission('product.write'))->toBeTrue()
        ->and($katalogcu->hasPermission('order.view'))->toBeFalse()
        ->and($destekci->hasPermission('order.view'))->toBeTrue()
        // Depocu örneği: siparişi görür ama para iadesi yapamaz (1A.3).
        ->and($destekci->hasPermission('order.refund'))->toBeFalse();
});

it('rol yoksa sessizce geçmiyor', function () {
    // `tenant:create` çalışmamış bir markayı taklit ediyoruz.
    $tenant = kiraciOlustur('tohum-c.test');
    tenancy()->initialize($tenant);

    expect(Role::count())->toBe(0)
        ->and(fn () => (new TenantDemoSeeder)->run())->toThrow(RuntimeException::class);
});

it('kiracı bağlamı yokken çalışmıyor', function () {
    markaKur('tohum-d.test');
    tenancy()->end();

    // Merkez bağlamda koşsaydı kayıtlar yanlış şemaya gitmeye çalışırdı.
    expect(fn () => (new TenantDemoSeeder)->run())->toThrow(RuntimeException::class);
});
