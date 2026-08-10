<?php

namespace App\Domain\Legal;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocumentDraft;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Yasal metinlerin taslak/yayın döngüsü.
 *
 * İki dünya var ve aralarındaki tek geçiş `yayinla()`:
 *
 *   TASLAK                              SÜRÜM
 *   üzerinde çalışılan hâl              yayınlanmış, değişmez hâl
 *   serbest, yarım kalabilir            tam; siparişler buna bağlanır
 *   dışarı ÇIKMAZ            ──yayinla──▶ vitrin ve müşteri bunu görür
 *
 * ⚠️ `app/Domain/` kiracıdan habersizdir (M-2.7) — bu sınıf hangi markada
 * olduğunu bilmiyor, `search_path` ve kiracı etiketli cache hallediyor.
 */
class LegalDocumentService
{
    public function __construct(private readonly LegalPlaceholders $yerTutucular) {}

    /** Üzerinde çalışılan metin. Yayınlanmamış olabilir, yarım olabilir. */
    public function taslak(LegalDocumentType $tur): ?string
    {
        return LegalDocumentDraft::where('type', $tur)->value('content');
    }

    /**
     * Taslağa yazar. DENETİM YOK — kasıtlı.
     *
     * Marka metni birkaç oturumda yazıyor; yarım kalabilmesi taslağın
     * varlık sebebidir. Zorunluluk denetimi yayın anında koşuyor.
     */
    public function taslagaYaz(LegalDocumentType $tur, ?string $icerik): void
    {
        LegalDocumentDraft::updateOrCreate(
            ['type' => $tur],
            ['content' => $icerik],
        );
    }

    /**
     * Şu an geçerli olan sürüm. Hiç yayınlanmadıysa null.
     *
     * Vitrin ve ödeme adımı bunu okuyor, o yüzden önbellekli.
     */
    public function guncelSurum(LegalDocumentType $tur): ?LegalDocumentVersion
    {
        return Cache::remember(
            $this->onbellekAnahtari($tur),
            now()->addHour(),
            fn () => LegalDocumentVersion::where('type', $tur)
                ->orderByDesc('version_no')
                ->first(),
        );
    }

    /**
     * Belirli bir sürüm — sipariş kendi sürümünü buradan okur.
     *
     * ⚠️ "En son sürüm" DEĞİL. 15 Mart'ta verilen sipariş, 20 Mart'ta
     * yayınlanan metni değil kendi bağlandığı sürümü göstermeli.
     */
    public function surum(int $id): ?LegalDocumentVersion
    {
        return LegalDocumentVersion::find($id);
    }

    /**
     * Taslakta yayınlanmamış değişiklik var mı? Panelde "yayınlanmamış
     * değişiklikleriniz var" uyarısı için.
     */
    public function yayinlanmamisDegisiklikVar(LegalDocumentType $tur): bool
    {
        $taslak = $this->taslak($tur);

        if ($taslak === null || trim($taslak) === '') {
            return false;
        }

        return $taslak !== $this->guncelSurum($tur)?->content;
    }

    /**
     * Taslağı yayınlar: YENİ SÜRÜM SATIRI doğurur.
     *
     * Eski sürüme dokunulmaz — ona bağlı siparişler var. Taslak da yerinde
     * kalır, bir sonraki düzenlemenin başlangıcıdır.
     *
     * ⚠️ Boş taslak yayınlanamaz. Sürüm satırının varlığı "bu metin
     * yürürlükte" demek; boş bir sürüm, sözleşmesi olmayan bir sipariş
     * üretirdi.
     *
     * ⚠️ Yer tutucular BURADA doldurulur, okuma anında değil. Doldurulamayan
     * biri kalırsa metin yayınlanmaz — müşteri hiçbir koşulda `{{unvan}}`
     * göremez, çünkü öyle bir sürüm oluşamıyor.
     *
     * @throws EmptyLegalDocumentException
     * @throws UnfilledPlaceholderException
     */
    public function yayinla(LegalDocumentType $tur, ?User $yayinlayan = null): LegalDocumentVersion
    {
        $surum = DB::transaction(function () use ($tur, $yayinlayan) {
            /*
            | Taslak satırı KİLİTLENİYOR.
            |
            | İki personel aynı anda "yayınla"ya basarsa ikisi de "en büyük
            | numara 7" görür ve ikisi de 8 yazmaya çalışır; biri unique
            | kısıtına takılıp hata alır. Kilit sayesinde ikinci istek
            | birincinin bitmesini bekliyor ve 9'u alıyor — sıraya
            | giriyorlar, ikisi de başarılı oluyor.
            */
            $taslak = LegalDocumentDraft::where('type', $tur)->lockForUpdate()->first();
            $icerik = $taslak?->content;

            if ($icerik === null || trim($icerik) === '') {
                throw new EmptyLegalDocumentException($tur);
            }

            // Yer tutucular mağaza bilgileriyle doldurulur; eksik kalırsa
            // istisna fırlar ve transaction geri sarılır — sürüm oluşmaz.
            $icerik = $this->yerTutucular->doldur($icerik);

            $sonNumara = LegalDocumentVersion::where('type', $tur)->max('version_no') ?? 0;

            return LegalDocumentVersion::create([
                'type' => $tur,
                'version_no' => $sonNumara + 1,
                'content' => $icerik,
                'published_at' => now(),
                // ⚠️ FK değil, KOPYA — gerekçe migration'da yazılı
                // (personel çıkarılınca satır güncellenmek zorunda kalırdı).
                'published_by_uuid' => $yayinlayan?->uuid,
                'published_by_name' => $yayinlayan?->name,
            ]);
        });

        Cache::forget($this->onbellekAnahtari($tur));

        return $surum;
    }

    private function onbellekAnahtari(LegalDocumentType $tur): string
    {
        return "legal:current:{$tur->value}";
    }
}
