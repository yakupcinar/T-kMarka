<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Panelin her sayfasına giden ortak veriler. (4C)
 *
 * ★ 4C-K4 · DÜĞMEYİ GİZLEMEK YETKİ DEĞİLDİR.
 *
 * Aşağıda paylaşılan izin listesi YALNIZCA arayüzü şekillendirmek içindir:
 * kullanıcının göremeyeceği menüyü çizmemek, kullanamayacağı düğmeyi
 * göstermemek. Gerçek koruma sunucuda `izin:` middleware'inde duruyor ve
 * orada kalmalı.
 *
 * ⚠️ Bir gün "izni arayüzde kontrol ettik, uçta gerek yok" denirse sistem
 * tamamen açılır: tarayıcıdaki her şey kullanıcının elindedir. Bu liste
 * bir KOLAYLIK, bir KAPI DEĞİL.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Panelin kök Blade görünümü.
     *
     * ⚠️ Vitrinden AYRI: vitrin sunucuda render edilen Blade (4-K1),
     * panel Inertia. Aynı düzeni paylaşsalardı biri diğerinin
     * ihtiyacına göre bozulurdu.
     */
    protected $rootView = 'panel.app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $istek): array
    {
        $kullanici = $istek->user('staff-web');

        return array_merge(parent::share($istek), [
            'auth' => [
                'user' => $kullanici instanceof User ? [
                    'id' => $kullanici->id,
                    'name' => $kullanici->name,
                    'email' => $kullanici->email,
                    'is_owner' => (bool) $kullanici->is_owner,

                    /*
                    | ⚠️ PAROLA HASH'İ ve token'lar BURAYA GİRMEZ. Modeli
                    | olduğu gibi paylaşmak (`$kullanici->toArray()`) en
                    | kolay yol olurdu ve `$hidden` listesine güvenirdi;
                    | alan alan yazmak, yeni bir sütun eklendiğinde onun
                    | kendiliğinden dışarı sızmamasını garantiliyor.
                    */
                ] : null,

                'permissions' => $kullanici instanceof User
                    ? $this->izinler($kullanici)
                    : [],
            ],

            /*
            | Markanın adı — panelin üst barında görünüyor. Vitrinle aynı
            | ayardan okunuyor, ikinci bir kaynak yok.
            */
            'marka' => [
                'ad' => (string) (tenant('name') ?? 'Panel'),
            ],

            /*
            | Bir önceki isteğin bıraktığı bildirim (PRG deseni).
            | Vitrindeki `session('mesaj')` ile aynı fikir.
            */
            'bildirim' => [
                'mesaj' => $istek->session()->get('mesaj'),
                'hata' => $istek->session()->get('hata'),
            ],
        ]);
    }

    /**
     * Kullanıcının izinleri — arayüzü şekillendirmek için.
     *
     * @return list<string>
     */
    private function izinler(User $kullanici): array
    {
        /*
        | ⚠️ SAHİP her izne sahiptir (`hasPermission` bunu `is_owner` ile
        | kısa devre yapıyor) ama `izinler()` yalnızca ROLLERDEN geleni
        | döndürüyor — sahibin rolü boşsa liste de boş gelir.
        |
        | Bu yüzden enum'un tamamı veriliyor. Aksi hâlde sahip, kendi
        | panelinde yetkisi olan menüleri GÖREMEZDİ: sunucu "yapabilirsin"
        | derken arayüz düğmeyi hiç çizmezdi.
        */
        if ($kullanici->is_owner) {
            return array_map(fn (Permission $izin) => $izin->value, Permission::cases());
        }

        /** @var list<string> $izinler */
        $izinler = $kullanici->izinler()->values()->all();

        return $izinler;
    }
}
