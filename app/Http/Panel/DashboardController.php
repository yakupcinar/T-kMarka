<?php

namespace App\Http\Panel;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel panosu — 4C'de yalnızca İSKELET. (4C)
 *
 * ⚠️ Sayaç ve özet YOK ve bu bilinçli: 4C'nin iddiası "Inertia ayakta,
 * oturum çalışıyor, yetkiler arayüze taşınıyor". Gerçek veriler kendi
 * bloklarında gelecek (4D katalog, 4E sipariş).
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Panosu');
    }
}
