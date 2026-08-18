<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Personel kimlik akışı — giriş ve çıkış.
 *
 * ⚠️ KAYIT METODU YOK ve olmayacak. Personel kendi kendine hesap açamaz;
 * markanın sahibi/yöneticisi tarafından davet edilir (1A.3). Aksi hâlde
 * markanın alan adını bilen herkes panele hesap açardı.
 *
 * 📌 `CustomerAuthService` ile giriş/çıkış mantığı neredeyse aynı. Ortak bir
 * soyut sınıfa çıkarmak mümkün — ama önce bu uçların otomatik testi yazılacak.
 * Testsiz ortaklaştırma, çalışan kodu sessizce bozmanın en hızlı yolu.
 */
class StaffAuthService
{
    /**
     * Giriş. Başarısızsa `ValidationException` fırlatır (422).
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function girisYap(string $email, string $parola): array
    {
        $personel = $this->dogrula($email, $parola);

        return [
            'user' => $personel,
            'token' => $personel->createToken('panel')->plainTextToken,
        ];
    }

    /**
     * Kimlik doğrular — TOKEN ÜRETMEZ.
     *
     * ★ 4C'de ayrıldı: panel sayfaları OTURUM tabanlı (4C-K3) ve token'a
     * ihtiyaçları yok. Üretilseydi her sayfa girişi kullanılmayan bir token
     * bırakır, `personal_access_tokens` tablosu çöple dolardı.
     *
     * ⚠️ AYRI BİR KOPYA DEĞİL, ORTAK KAYNAK. Kontrolü panel sayfası için
     * yeniden yazmak, aşağıdaki gizlilik kuralını iki yerde tutmak olurdu —
     * ve biri bir gün güncellenmezdi.
     *
     * @throws ValidationException
     */
    public function dogrula(string $email, string $parola): User
    {
        $personel = User::where('email', EmailNormalizer::normallestir($email))->first();

        /*
        | Müşteri tarafındaki gerekçenin aynısı: "kullanıcı yok" ile "parola
        | yanlış" ayrı mesaj vermiyor, yoksa hangi e-postaların panele erişimi
        | olduğu tek tek öğrenilebilirdi.
        |
        | Soft delete edilmiş personel de buraya düşmüyor: Eloquent'in
        | varsayılan sorgusu `deleted_at IS NULL` koşulunu zaten ekliyor.
        | Yani "işten ayrılan personel" giriş yapamıyor.
        */
        if ($personel === null || ! Hash::check($parola, $personel->password)) {
            throw ValidationException::withMessages([
                'email' => ['Girilen bilgilerle eşleşen bir hesap bulunamadı.'],
            ]);
        }

        return $personel;
    }

    /** Çıkış — yalnızca bu oturumun token'ı iptal edilir. */
    public function cikisYap(User $personel): void
    {
        $personel->currentAccessToken()->delete();
    }
}
