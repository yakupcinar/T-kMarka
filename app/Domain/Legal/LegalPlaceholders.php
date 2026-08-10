<?php

namespace App\Domain\Legal;

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;

/**
 * Yasal metinlerdeki `{{yer_tutucu}}` ifadelerini mağaza bilgileriyle
 * doldurur.
 *
 * ⚠️ Doldurma YAYIN ANINDA yapılıyor, okuma anında değil. İki sonucu var:
 *
 *   1. Sürüm satırındaki metin TAM — içinde yer tutucu kalmıyor. Müşteri
 *      hiçbir koşulda `{{unvan}}` göremez, çünkü öyle bir sürüm oluşamıyor.
 *
 *   2. Metin o günkü şirket bilgileriyle DONUYOR. Marka yarın adres
 *      değiştirse bile eski sözleşmede eski adres kalır — `docs/domain-model.md`
 *      §7'deki "sipariş bir fotoğraftır" ilkesinin metin tarafı.
 */
class LegalPlaceholders
{
    /**
     * Yer tutucu → `store` grubundaki ayar anahtarı.
     *
     * Liste kodda sabit: marka kendi yer tutucusunu icat edemez. Edebilseydi
     * `{{iban}}` yazan bir metin sessizce doldurulmadan geçerdi.
     *
     * @var array<string, string>
     */
    public const ESLEME = [
        'marka_adi' => 'name',
        'unvan' => 'legal_name',
        'vergi_no' => 'tax_number',
        'vergi_dairesi' => 'tax_office',
        'adres' => 'address',
        'telefon' => 'phone',
        'eposta' => 'contact_email',
    ];

    public function __construct(private readonly SettingsService $ayarlar) {}

    /**
     * Metindeki yer tutucuları doldurur.
     *
     * @throws UnfilledPlaceholderException doldurulamayan yer tutucu kalırsa
     */
    public function doldur(string $metin): string
    {
        $magaza = $this->ayarlar->grup(SettingGroup::Store);

        foreach (self::ESLEME as $yerTutucu => $anahtar) {
            $deger = $magaza[$anahtar] ?? null;

            if (! is_string($deger) || trim($deger) === '') {
                continue;   // boş kalanlar aşağıdaki denetime takılacak
            }

            $metin = str_replace('{{'.$yerTutucu.'}}', $deger, $metin);
        }

        /*
        | ⚠️ SON DENETİM — geriye tek bir `{{...}}` bile kalırsa metin
        | yayınlanmıyor.
        |
        | İki durumu birden yakalıyor: mağaza bilgisi girilmemiş olabilir,
        | ya da marka tanımadığımız bir yer tutucu yazmış olabilir. İkisinde
        | de sonuç aynı: müşteriye süslü parantez gitmesindense hata iyidir.
        */
        $kalanlar = $this->kalanYerTutucular($metin);

        if ($kalanlar !== []) {
            throw new UnfilledPlaceholderException($kalanlar);
        }

        return $metin;
    }

    /** @return list<string> */
    public function kalanYerTutucular(string $metin): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/u', $metin, $eslesmeler);

        /** @var list<string> $bulunanlar */
        $bulunanlar = array_values(array_unique($eslesmeler[1]));

        return $bulunanlar;
    }
}
