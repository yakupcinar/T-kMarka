<?php

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,

    // Kiracılık katmanını devreye alır: olayları bağlar ve
    // routes/tenant.php dosyasını yükler. vendor:publish dosyayı
    // kopyalar ama bu listeye EKLEMEZ — elle eklenmesi gerekiyor.
    TenancyServiceProvider::class,
];
