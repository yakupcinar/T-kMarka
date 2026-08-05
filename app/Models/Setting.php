<?php

namespace App\Models;

use App\Enums\SettingGroup;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Markaya özel her ayar. (docs/domain-model.md §4)
 *
 * White-label iskeletin kalbi: iki markayı ayıran şey kod değil, bu tablodaki
 * satırlar (M-1). Okuma/yazma için kullanılacak servis 1A.4'te yazılacak;
 * burada model seviyesinde şifreleme ve tip dönüşümü var.
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'is_encrypted',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',

            // Grup adı serbest metin değil. 'payment' yerine 'payments'
            // yazılan tek satır, ödeme ayarlarının panelde görünmemesine
            // yol açardı — hata da vermeden, boş liste dönerek.
            'group' => SettingGroup::class,
        ];
    }

    /**
     * Değeri okur/yazar; `is_encrypted` işaretliyse şifreler ve çözer.
     *
     * ⚠️ Laravel'in hazır `encrypted` cast'i KOLON bazlıdır: "bu kolon hep
     * şifreli" denebilir, "bu satır şifreli" denemez. Bizde satır bazlı
     * olduğu için dönüşümü elle yazıyoruz.
     *
     * ⚠️ **Sıra tuzağı.** `Setting::create()` alanları dizideki SIRAYLA
     * işler. `value` önce gelirse `is_encrypted` henüz bilinmiyor olur ve
     * ödeme anahtarı sessizce DÜZ METİN kaydedilirdi. Bu yüzden bilinmediği
     * durumda istisna fırlatıyoruz: sessiz yanlış yerine gürültülü durma.
     *
     * @return Attribute<mixed, mixed>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (?string $ham): mixed {
                if ($ham === null) {
                    return null;
                }

                $cozulmus = json_decode($ham, true);

                return $this->is_encrypted && is_string($cozulmus)
                    ? json_decode(Crypt::decryptString($cozulmus), true)
                    : $cozulmus;
            },

            set: function (mixed $deger): array {
                if (! array_key_exists('is_encrypted', $this->attributes)) {
                    throw new RuntimeException(
                        'Setting::$value yazılmadan önce is_encrypted belirlenmeli. '
                        .'Dizide is_encrypted anahtarını value\'dan ÖNCE verin — '
                        .'aksi hâlde şifrelenmesi gereken değer düz metin kaydedilir.'
                    );
                }

                $json = json_encode($deger);

                return [
                    'value' => $this->is_encrypted
                        ? json_encode(Crypt::encryptString((string) $json))
                        : $json,
                ];
            },
        );
    }
}
