<?php

namespace App\Http\Storefront;

use App\Domain\Identity\CustomerAuthService;
use App\Http\Storefront\Requests\LoginRequest;
use App\Http\Storefront\Requests\RegisterRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vitrin tarafı kimlik uçları — markanın MÜŞTERİSİ.
 *
 * ⚠️ M-3'ün şartı: burada iş kuralı YOK. Controller yalnızca isteği alır,
 * doğrulanmış veriyi servise verir, cevabı biçimlendirir. Parola kontrolü,
 * token üretimi ve hata durumları `CustomerAuthService`'te.
 */
class AuthController
{
    public function __construct(private readonly CustomerAuthService $servis) {}

    public function register(RegisterRequest $istek): JsonResponse
    {
        $sonuc = $this->servis->kaydet($istek->validated());

        return response()->json([
            'customer' => $this->musteriCiktisi($sonuc['customer']),
            'token' => $sonuc['token'],
        ], 201);
    }

    public function login(LoginRequest $istek): JsonResponse
    {
        $sonuc = $this->servis->girisYap(
            (string) $istek->input('email'),
            (string) $istek->input('password'),
        );

        return response()->json([
            'customer' => $this->musteriCiktisi($sonuc['customer']),
            'token' => $sonuc['token'],
        ]);
    }

    public function logout(Request $istek): JsonResponse
    {
        /** @var Customer $musteri */
        $musteri = $istek->user();

        $this->servis->cikisYap($musteri);

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    public function me(Request $istek): JsonResponse
    {
        /** @var Customer $musteri */
        $musteri = $istek->user();

        return response()->json(['customer' => $this->musteriCiktisi($musteri)]);
    }

    /**
     * Dışarıya dönen müşteri gösterimi.
     *
     * ⚠️ `id` DEĞİL `uuid` dönüyor. Sıra numarası verilseydi müşteri sayısı ve
     * kayıt hızı dışarıdan tahmin edilebilirdi (domain-model §0).
     * Parola zaten modelin `$hidden` listesinde, ama burada da alan alan
     * seçerek "ne dönüyorsa odur" garantisi veriyoruz.
     *
     * @return array<string, mixed>
     */
    private function musteriCiktisi(Customer $musteri): array
    {
        return $musteri->only(['uuid', 'name', 'email', 'phone', 'accepts_marketing']);
    }
}
