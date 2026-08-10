<?php

namespace App\Domain\Identity;

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Müşteri kimlik akışı — kayıt, giriş, çıkış.
 *
 * ⚠️ İş mantığı burada, controller'da değil (M-3'ün şartı). Controller
 * yalnızca isteği alıp doğrulanmış veriyi buraya veriyor ve cevabı döndürüyor.
 * Faz 4'te arayüz geldiğinde bu sınıf hiç değişmeyecek — yalnızca ikinci bir
 * çağıran eklenecek.
 *
 * Bu sınıf hangi markada olduğunu BİLMİYOR: `search_path` zaten kapsamı
 * belirlemiş oluyor (M-2.7 — `app/Domain/` kiracıdan habersizdir).
 */
class CustomerAuthService
{
    /**
     * Yeni müşteri kaydı. Kayıt sonrası doğrudan giriş yapmış sayılıyor.
     *
     * Beklenen anahtarlar: name · email · password · phone? · accepts_marketing?
     * (doğrulaması `RegisterRequest`'te — buraya yalnızca doğrulanmış veri gelir)
     *
     * @param  array<string, mixed>  $veri
     * @return array{customer: Customer, token: string}
     */
    public function kaydet(array $veri): array
    {
        $musteri = Customer::create($veri);

        // ⚠️ Gönderilmeyen alanlar (accepts_marketing gibi) modele hiç
        // atanmadığı için bellekte NULL kalıyor; veritabanında ise varsayılan
        // değer var. refresh() olmadan API "null" döner ve tüketici bunu
        // "bilinmiyor" sanır.
        $musteri->refresh();

        return [
            'customer' => $musteri,
            'token' => $this->tokenUret($musteri),
        ];
    }

    /**
     * Giriş. Başarısızsa `ValidationException` fırlatır (422).
     *
     * @return array{customer: Customer, token: string}
     *
     * @throws ValidationException
     */
    public function girisYap(string $email, string $parola): array
    {
        $musteri = Customer::where('email', EmailNormalizer::normallestir($email))->first();

        /*
        | ⚠️ "Kullanıcı bulunamadı" ile "parola yanlış" AYRI mesaj vermiyor.
        |
        | Ayrı verseydi saldırgan hangi e-postaların kayıtlı olduğunu tek tek
        | öğrenebilirdi (hesap sayımı / user enumeration). Tek mesaj bunu
        | imkânsız kılıyor.
        |
        | Ayrıca misafir müşterilerin parolası NULL — onlar da buradan
        | giremiyor, çünkü Hash::check null parolayla eşleşmiyor.
        */
        if ($musteri === null || $musteri->password === null || ! Hash::check($parola, $musteri->password)) {
            throw ValidationException::withMessages([
                'email' => ['Girilen bilgilerle eşleşen bir hesap bulunamadı.'],
            ]);
        }

        return [
            'customer' => $musteri,
            'token' => $this->tokenUret($musteri),
        ];
    }

    /**
     * Çıkış — yalnızca BU oturumun token'ı iptal edilir.
     *
     * Müşterinin telefonundaki oturum, bilgisayarından çıkış yapınca
     * kapanmamalı. "Tüm cihazlardan çık" ayrı bir özellik olur.
     */
    public function cikisYap(Customer $musteri): void
    {
        /*
        | `currentAccessToken()` Sanctum'da jenerik (`@return TToken`).
        | Bizde çerez tabanlı SPA modu kullanılmadığı için (K-12, token
        | tabanlı) her zaman `PersonalAccessToken`'a çözülüyor — Larastan da
        | bunu doğruladı. Tip kontrolü koyduğumuzda "her zaman doğru" uyarısı
        | verdi, yani ölü koddu; kaldırıldı.
        |
        | Bu uç `auth:customer` arkasında, dolayısıyla token her zaman var.
        */
        $musteri->currentAccessToken()->delete();
    }

    private function tokenUret(Customer $musteri): string
    {
        return $musteri->createToken('vitrin')->plainTextToken;
    }
}
