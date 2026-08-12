<?php

namespace App\Domain\Payment;

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use Illuminate\Contracts\Container\Container;

/**
 * Markanın seçtiği sağlayıcıyı çözer.
 *
 * ⚠️ Sağlayıcı `.env`'den DEĞİL `settings`'ten okunuyor: her markanın
 * kendi ödeme hesabı var (M-1). `.env`'e yazılsaydı bütün markalar aynı
 * hesaba tahsilat yapardı — ve bu hata vermezdi, para yanlış yere giderdi.
 *
 * ⚠️ Bu sınıf konteynere `singleton` olarak BAĞLANMIYOR. Bağlansaydı ilk
 * markanın sağlayıcısı bellekte kalır, ikinci markanın isteğinde de o
 * kullanılırdı — kuyruk işçisinde (kalıcı süreç) sessiz ve kalıcı bir
 * kiracı sızıntısı olurdu.
 */
class PaymentProviderFactory
{
    /**
     * Tanınan sağlayıcılar. Gerçek sağlayıcı Faz 5'te buraya eklenecek;
     * üst kod değişmeyecek.
     *
     * @var array<string, class-string<PaymentProvider>>
     */
    private const SAGLAYICILAR = [
        'fake' => FakePaymentProvider::class,
    ];

    public const VARSAYILAN = 'fake';

    /**
     * Seçilebilir sağlayıcı adları — panel bu listeyi gösteriyor.
     *
     * ⚠️ Liste BURADAN türetiliyor, panelde ayrıca yazılmıyor. İki yerde
     * yazılsaydı yeni sağlayıcı eklendiği gün panele eklemek unutulur,
     * marka onu hiç seçemezdi.
     *
     * @return list<string>
     */
    public static function tanimliAdlar(): array
    {
        return array_keys(self::SAGLAYICILAR);
    }

    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly Container $konteyner,
    ) {}

    /**
     * @throws UnknownPaymentProviderException
     */
    public function coz(): PaymentProvider
    {
        $ad = $this->ayarlar->al(SettingGroup::Payment, 'provider', self::VARSAYILAN);
        $ad = is_string($ad) ? $ad : self::VARSAYILAN;

        /*
        | ⚠️ Tanınmayan ad → GÜRÜLTÜLÜ hata.
        |
        | Sessizce sahteye düşseydi, canlıda `iyzico` yerine `iyziko`
        | yazılan tek harf yüzünden bütün siparişler "ödendi" görünür ve
        | hiç para tahsil edilmezdi.
        */
        if (! isset(self::SAGLAYICILAR[$ad])) {
            throw new UnknownPaymentProviderException($ad);
        }

        return $this->konteyner->make(self::SAGLAYICILAR[$ad]);
    }
}
