<?php

namespace App\Http\Panel;

use App\Domain\Settings\StorePublication;
use App\Domain\Settings\StoreReadiness;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Mağazanın yayın durumu — panel ucu. `izin:settings.write` arkasında.
 */
class StoreController extends Controller
{
    public function __construct(
        private readonly StorePublication $yayin,
        private readonly StoreReadiness $hazirlik,
    ) {}

    /**
     * "Yayına hazır mıyım?" — yayınlamadan önce görülebilsin diye ayrı uç.
     *
     * Panel bunu düzenleme ekranında gösterip markaya neyin eksik olduğunu
     * baştan söyleyebilir; "yayınla → hata" turuna gerek kalmaz.
     */
    public function readiness(): JsonResponse
    {
        return response()->json([
            'is_published' => $this->yayin->yayindaMi(),
            'ready' => $this->hazirlik->hazirMi(),
            'missing' => $this->hazirlik->eksikler(),
        ]);
    }

    /**
     * Mağazayı yayına alır.
     *
     * Eksik varsa istisna fırlar (bootstrap/app.php → 422 + eksik listesi)
     * ve bayrak DEĞİŞMEZ: ya hepsi ya hiçbiri.
     */
    public function publish(): JsonResponse
    {
        $this->yayin->yayinla();

        return response()->json(['message' => 'Mağaza yayına alındı.', 'is_published' => true]);
    }

    /**
     * Mağazayı kapatır — denetimsiz, her zaman serbest.
     *
     * Kapanmayı şarta bağlamak, hatalı bir mağazayı açık kalmaya zorlardı.
     * Ayrıca kilitli alanları düzenlemenin tek yolu bu.
     */
    public function close(): JsonResponse
    {
        $this->yayin->kapat();

        return response()->json(['message' => 'Mağaza kapatıldı.', 'is_published' => false]);
    }
}
