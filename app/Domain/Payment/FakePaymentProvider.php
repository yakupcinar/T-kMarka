<?php

namespace App\Domain\Payment;

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use Illuminate\Support\Str;

/**
 * Faz 1'in sağlayıcısı — gerçek para yok, GERÇEK AKIŞ var. (1E-K6)
 *
 * ⚠️ "Başarılı" diyen üç satırlık bir sınıf yazmak kolaydı ama 1E'nin
 * hiçbir zorluğunu sınamazdı: yönlendirme yok, gecikme yok, tekrar yok,
 * imza yok. Öyle bir sahteyle yeşil olan testler, iyzico takıldığı gün
 * hiçbir şey söylemez.
 *
 * Bu yüzden taklit edilen şeyler:
 *   · yönlendirme adresi üretir (müşteri bizden çıkar)
 *   · sonucu HMAC-SHA256 ile İMZALAR — imzasız yük reddedilir
 *   · aynı bildirimi defalarca üretebilir (`bildirim()` her çağrıda
 *     AYNI referansı verir; tekrar teslim böyle sınanır)
 *
 * İmza düzeni iyzico'nunkiyle aynı biçimde kuruldu (gizli anahtar +
 * alanlar SIRAYLA birleştirilip HMAC): gerçek sağlayıcı takıldığında
 * değişen yalnızca alan adları olsun.
 */
class FakePaymentProvider implements RefundablePaymentProvider
{
    /** `settings` içindeki imza anahtarının adı — şifreli saklanıyor. */
    public const GIZLI_ANAHTAR = 'fake_secret';

    public function __construct(private readonly SettingsService $ayarlar) {}

    public function ad(): string
    {
        return 'fake';
    }

    public function baslat(PaymentRequest $istek): PaymentInitiation
    {
        /*
        | Gerçek sağlayıcı burada işlem numarasını KENDİ üretir; biz de
        | öyle yapıyoruz. Sipariş numarasından TÜRETİLMİYOR — türetilseydi
        | testler numarayı tahmin edebilir ve idempotanslık sınavı
        | gerçekte olmayan bir kolaylıkla geçerdi.
        */
        $referans = 'FAKE-'.Str::upper(Str::random(16));

        return new PaymentInitiation(
            yonlendirmeAdresi: $istek->donusAdresi.'?ref='.$referans,
            saglayiciReferansi: $referans,
        );
    }

    /** @return list<string> */
    public function gerekliAnahtarlar(): array
    {
        return [self::GIZLI_ANAHTAR];
    }

    /** @return list<string> */
    public function imzaBasliklari(): array
    {
        return ['X-Fake-Signature'];
    }

    /**
     * Sahte sağlayıcı referansı yönlendirme adresinde taşıyor.
     *
     * @param  array<string, mixed>  $veri
     */
    public function donusReferansi(array $veri): ?string
    {
        $ref = $veri['ref'] ?? null;

        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    public function webhookuDogrula(array $yuk, ?string $imza): bool
    {
        if ($imza === null) {
            return false;
        }

        /*
        | ⚠️ `hash_equals` — düz `===` DEĞİL.
        |
        | Düz karşılaştırma ilk farklı karakterde duruyor; saldırgan
        | cevap süresini ölçerek imzayı karakter karakter bulabilir
        | (zamanlama saldırısı). `hash_equals` sabit sürede karşılaştırır.
        */
        return hash_equals($this->imzala($yuk), $imza);
    }

    public function webhookuCoz(array $yuk): PaymentOutcome
    {
        $basarili = ($yuk['status'] ?? null) === 'success';

        return new PaymentOutcome(
            siparisNumarasi: (string) ($yuk['order_number'] ?? ''),
            saglayiciReferansi: (string) ($yuk['reference'] ?? ''),
            basarili: $basarili,
            tutar: $this->sayisal($yuk['amount'] ?? null),
            hamCevap: $yuk,
            hataKodu: $basarili ? null : (string) ($yuk['error_code'] ?? 'declined'),
        );
    }

    /**
     * Sahte iade — gerçek para yok, ama akış gerçek (2B-K7).
     *
     * ⚠️ Aynı anahtarla ikinci istek AYNI referansı döndürüyor: gerçek
     * sağlayıcı da öyle davranıyor ve idempotanslık böyle sınanabiliyor.
     */
    public function iadeEt(string $referans, string $tutar, string $idempotanslikAnahtari): PaymentOutcome
    {
        return new PaymentOutcome(
            siparisNumarasi: '',
            saglayiciReferansi: 'FAKE-IADE-'.substr(hash('sha256', $idempotanslikAnahtari), 0, 16),
            basarili: true,
            tutar: $this->sayisal($tutar),
            hamCevap: ['refunded' => $tutar, 'source_ref' => $referans],
        );
    }

    /**
     * ★ SAĞLAYICI TARAFI — yalnızca test ve geliştirme için.
     *
     * Gerçek hayatta bu yükü iyzico'nun sunucusu üretir. Burada üretmemizin
     * sebebi tekrar teslimi ve imzayı SINAYABİLMEK: aynı çağrı aynı
     * referansla tekrar tekrar çağrılabiliyor.
     *
     * @return array{yuk: array<string, mixed>, imza: string}
     */
    public function bildirim(string $siparisNumarasi, string $referans, string $tutar, bool $basarili = true): array
    {
        $yuk = [
            'order_number' => $siparisNumarasi,
            'reference' => $referans,
            'status' => $basarili ? 'success' : 'failure',
            'amount' => $tutar,
        ];

        if (! $basarili) {
            $yuk['error_code'] = 'declined';
        }

        return ['yuk' => $yuk, 'imza' => $this->imzala($yuk)];
    }

    /**
     * İmza = HMAC(gizli anahtar, alanlar SIRAYLA).
     *
     * ⚠️ Sıra sabit ve alanlar tek tek sayılı; `json_encode($yuk)`
     * kullanılsaydı anahtar sırası değişince imza da değişir, doğrulama
     * rastgele başarısız olurdu.
     *
     * @param  array<string, mixed>  $yuk
     */
    private function imzala(array $yuk): string
    {
        $metin = implode('|', [
            (string) ($yuk['order_number'] ?? ''),
            (string) ($yuk['reference'] ?? ''),
            (string) ($yuk['status'] ?? ''),
            $this->sayisal($yuk['amount'] ?? null),
        ]);

        return hash_hmac('sha256', $metin, $this->gizliAnahtar());
    }

    /** @return numeric-string */
    private function sayisal(mixed $deger): string
    {
        return is_numeric($deger) ? (string) $deger : '0';
    }

    /**
     * ⚠️ Boş anahtar KABUL EDİLMİYOR.
     *
     * `hash_hmac(..., '')` hata vermez, geçerli görünen bir imza üretir.
     * Anahtarı hiç kurulmamış bir markada doğrulama "çalışır" ama aslında
     * hiçbir şey korumaz: algoritmayı bilen herkes geçerli bildirim
     * üretir. 1E.1'de test bunu yakaladı.
     *
     * @throws MissingPaymentCredentialsException
     */
    private function gizliAnahtar(): string
    {
        $anahtar = $this->ayarlar->al(SettingGroup::Payment, self::GIZLI_ANAHTAR);

        if (! is_string($anahtar) || $anahtar === '') {
            throw new MissingPaymentCredentialsException($this->ad(), self::GIZLI_ANAHTAR);
        }

        return $anahtar;
    }
}
