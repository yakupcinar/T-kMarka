<?php

namespace App\Http\Middleware;

use App\Platform\Models\PlatformUser;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Kontrol düzleminin her sayfasına giden ortak veriler. (4F)
 *
 * ★ NEDEN AYRI MIDDLEWARE: [HandleInertiaRequests] marka panelinin
 * middleware'i — kök görünümü `panel.app`, paylaştığı kullanıcı
 * `staff-web` guard'ından ve marka adını kiracıdan okuyor.
 *
 * ⚠️ Tek middleware'e koşullu mantık yazmak en kolay yoldu ve iki
 * yüzeyin verisini tek yerde karıştırmak demekti: bir gün marka
 * paneline merkez verisi, kontrol düzlemine marka verisi sızardı.
 * Ayrı sınıf, ayrı kök görünüm, ayrı paket.
 */
class HandlePlatformInertia extends Middleware
{
    protected $rootView = 'platform.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $istek): array
    {
        $kullanici = $istek->user('platform-web');

        return array_merge(parent::share($istek), [
            'auth' => [
                /*
                | ⚠️ Alan alan yazılıyor — model olduğu gibi paylaşılmıyor.
                | 4C'deki gerekçenin aynısı: yeni bir sütun eklendiğinde
                | kendiliğinden dışarı sızmasın.
                */
                'user' => $kullanici instanceof PlatformUser ? [
                    'id' => $kullanici->id,
                    'name' => $kullanici->name,
                    'email' => $kullanici->email,
                ] : null,
            ],

            'bildirim' => [
                'mesaj' => $istek->session()->get('mesaj'),
                'hata' => $istek->session()->get('hata'),
            ],
        ]);
    }
}
