<?php

namespace App\Http\Panel;

use App\Domain\Legal\EmptyLegalDocumentException;
use App\Domain\Legal\LegalDocumentService;
use App\Domain\Legal\UnfilledPlaceholderException;
use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Yasal metinlerin panelden düzenlenmesi. (4.5B)
 *
 * ★ Faz 4'ün boşluklarından biriydi: marka sözleşmesini düzenleyemiyordu.
 *
 * ⚠️ TASLAK ve YAYIN AYRI (1A.4): düzenlemek yayınlamak değil. Yasal
 * metinlerin geçmişi olmak ZORUNDA ve `legal_document_versions` tablosu
 * salt-ekleme — yayınlamak yeni satır demek.
 */
class LegalPageController extends Controller
{
    public function __construct(private readonly LegalDocumentService $belgeler) {}

    public function index(): Response
    {
        $belgeler = [];

        foreach (LegalDocumentType::cases() as $tur) {
            $surum = $this->belgeler->guncelSurum($tur);

            $belgeler[] = [
                'tur' => $tur->value,
                'ad' => $tur->etiket(),
                'taslak' => $this->belgeler->taslak($tur),
                'yayin_surumu' => $surum?->version_no,
                'yayin_tarihi' => $surum?->published_at?->format('d.m.Y H:i'),

                /*
                | ⚠️ "Yayınlanmamış değişiklik var mı" AYRI bir soru:
                | taslak yayındakinden farklıysa marka bunu görmeli,
                | yoksa değişikliğini yayınladığını sanır.
                */
                'yayinlanmamis_degisiklik' => $this->belgeler->yayinlanmamisDegisiklikVar($tur),
            ];
        }

        return Inertia::render('Yasal', ['belgeler' => $belgeler]);
    }

    public function kaydet(Request $istek, string $tur): RedirectResponse
    {
        $tip = $this->turuCoz($tur);

        $veri = $istek->validate(['icerik' => ['nullable', 'string', 'max:100000']]);

        $this->belgeler->taslagaYaz($tip, $veri['icerik'] ?? null);

        return back()->with('mesaj', 'Taslak kaydedildi.');
    }

    public function yayinla(Request $istek, string $tur): RedirectResponse
    {
        $tip = $this->turuCoz($tur);

        $kullanici = $istek->user('staff-web');

        try {
            $this->belgeler->yayinla($tip, $kullanici instanceof User ? $kullanici : null);
        } catch (EmptyLegalDocumentException|UnfilledPlaceholderException $hata) {
            /*
            | ⚠️ Boş metin ya da doldurulmamış yer tutucu ({{sirket_adi}}
            | gibi) SAYFAYA taşınıyor, 500 olarak sızmıyor: marka bir
            | şeyi eksik bırakmış, bu sıradan bir sonuç.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Metin yayınlandı.');
    }

    private function turuCoz(string $tur): LegalDocumentType
    {
        $tip = LegalDocumentType::tryFrom($tur);

        abort_if($tip === null, 404);

        return $tip;
    }
}
