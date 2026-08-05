<?php

use Illuminate\Support\Facades\Redis;

/*
| Kuyruk tuzağı (M-2.4 / 1). Beşinin en sinsisi: iş, isteği yaratan
| süreçten SAATLER sonra, BAŞKA bir konteynerde çalışıyor. O konteynerin
| hangi markada olduğuna dair hiçbir bilgisi yok.
|
| Çözüm: kiracı kimliği işin GÖVDESİNDE taşınıyor. Bu test tam olarak
| onu doğruluyor.
*/

it('kuyruga atilan isin govdesinde kiraci kimligi tasiniyor', function () {
    config(['queue.default' => 'redis']);

    $a = kiraciOlustur('marka-a.test');

    tenancy()->initialize($a);
    dispatch(function () {
        // içeriği önemli değil — bakacağımız şey işin gövdesi
    });
    tenancy()->end();

    $ham = Redis::connection()->lpop('queues:default');
    $govde = json_decode((string) $ham, true);

    expect($govde)->toHaveKey('tenant_id')
        ->and($govde['tenant_id'])->toBe($a->id);
});

it('kiraci baglami olmadan atilan iste kimlik bulunmuyor', function () {
    config(['queue.default' => 'redis']);

    // Merkez bağlamda atılan iş — kiracı kimliği taşımamalı.
    dispatch(function () {
        //
    });

    $ham = Redis::connection()->lpop('queues:default');
    $govde = json_decode((string) $ham, true);

    expect($govde['tenant_id'] ?? null)->toBeNull();
});
