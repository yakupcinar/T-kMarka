<?php

namespace App\Http\Storefront;

use App\Domain\Privacy\DataRequestService;
use App\Enums\DataRequestType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KVKK veri talepleri — vitrin ucu. (2G)
 *
 * ⚠️ Kimlik doğrulaması İSTEĞE BAĞLI: misafir de talep edebilmeli
 * (M-1). Giriş yapmışsa e-postası kendi hesabından alınıyor.
 */
class PrivacyController extends Controller
{
    public const DONUS_YOLU = '/gizlilik/onay';

    public function __construct(private readonly DataRequestService $talepler) {}

    public function store(Request $istek): JsonResponse
    {
        $veri = $istek->validate([
            'type' => ['required', 'string', 'in:anonymize,export'],
            'email' => ['required_without:order_number', 'nullable', 'email', 'max:190'],

            // ⚠️ Misafirin kimlik kanıtı: e-posta + sipariş numarası.
            'order_number' => ['nullable', 'string', 'max:20'],
        ]);

        $kullanici = $istek->user();

        /*
        | ⚠️ Giriş yapmışsa e-posta HESAPTAN alınıyor, istekten değil.
        | İstekten alınsaydı giriş yapmış biri başkasının adresini yazıp
        | ona doğrulama postası gönderttirebilirdi.
        */
        $eposta = $kullanici instanceof Customer
            ? (string) $kullanici->email
            : (string) ($veri['email'] ?? '');

        $talep = $this->talepler->talepAc(
            DataRequestType::from((string) $veri['type']),
            $eposta,
            isset($veri['order_number']) ? (string) $veri['order_number'] : null,
            $istek->getSchemeAndHttpHost().self::DONUS_YOLU,
        );

        /*
        | ⚠️ Cevapta jeton YOK. Dönseydi doğrulama postasının anlamı
        | kalmazdı — talebi açan onu doğrudan kullanırdı.
        */
        return response()->json([
            'message' => 'Doğrulama bağlantısı e-posta adresinize gönderildi.',
            'expires_at' => $talep->expires_at->toIso8601String(),
        ], 202);
    }

    /**
     * Doğrulama bağlantısı — talebi UYGULAYAN yer.
     *
     * ⚠️ Silme GERİ ALINAMAZ (2G-K4); bağlantıya tıklamak onaydır.
     */
    public function confirm(string $token): JsonResponse
    {
        $dokum = $this->talepler->dogrulaVeUygula($token);

        return $dokum === null
            ? response()->json(['message' => 'Kişisel verileriniz silindi.'])
            : response()->json(['data' => $dokum]);
    }
}
