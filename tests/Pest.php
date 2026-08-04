<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature klasöründeki her test:
|
|   - Tests\TestCase'i kullanır  → Laravel uygulaması ayağa kalkar,
|                                  $this->get('/') gibi istekler atılabilir
|   - RefreshDatabase kullanır   → her test transaction içinde koşar,
|                                  bitince ROLLBACK. Testler birbirinin
|                                  verisine bulaşmaz.
|
| Unit klasöründe uygulama ve veritabanı YOK — orası saf PHP mantığı
| içindir, hızlı çalışır.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
