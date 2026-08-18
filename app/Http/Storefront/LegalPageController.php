<?php

namespace App\Http\Storefront;

use App\Domain\Legal\LegalDocumentService;
use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Yasal metinlerin OKUNABİLİR sayfaları. (4.5A)
 *
 * ★ BUGÜNKÜ BİR HATAYI KAPATIYOR. Ödeme sayfasındaki "Mesafeli satış
 * sözleşmesini okudum" bağlantısı `/api/legal/...` uçuna gidiyordu ve
 * müşteri **ham JSON** görüyordu:
 *
 * ```
 * {"document":{"version_id":1,"content":"# Mesafeli Satış Sözleşmesi\n…
 * ```
 *
 * ⚠️ Bu yalnızca çirkin değil, YASAL BİR SORUN: mesafeli satışta
 * müşterinin sözleşmeyi okuyabilmesi zorunlu. Onay kutusunu işaretlemesini
 * istediğimiz metni gösteremiyorduk.
 *
 * ⚠️ 4B'de gözden kaçtı çünkü test `assertSee('Mesafeli satış
 * sözleşmesini')` diyordu — BAĞLANTININ VARLIĞINI ölçüyordu, NEREYE
 * GİTTİĞİNİ değil.
 */
class LegalPageController extends Controller
{
    public function __construct(private readonly LegalDocumentService $metinler) {}

    /** Bütün yasal metinlerin listesi — vitrinin altındaki bağlantı. */
    public function index(): View
    {
        $belgeler = [];

        foreach (LegalDocumentType::cases() as $tur) {
            /*
            | ⚠️ YAYINLANMAMIŞ metin listede GÖRÜNMÜYOR. Görünseydi
            | müşteri tıklar ve 404 alırdı; "var ama yok" hâli, hiç
            | olmamasından kötü.
            */
            if ($this->metinler->guncelSurum($tur) !== null) {
                $belgeler[] = ['tur' => $tur->value, 'ad' => $tur->etiket()];
            }
        }

        return view('storefront.yasal-liste', ['belgeler' => $belgeler]);
    }

    public function show(string $tur): View
    {
        $tip = LegalDocumentType::tryFrom($tur);

        if ($tip === null) {
            throw new NotFoundHttpException;
        }

        $surum = $this->metinler->guncelSurum($tip);

        /*
        | ⚠️ Hiç yayınlanmamışsa 404 — boş sayfa değil. Boş gösterilseydi
        | müşteri "sözleşme buymuş" sanır, marka da eksiği fark etmezdi.
        */
        if ($surum === null) {
            throw new NotFoundHttpException;
        }

        return view('storefront.yasal', [
            'belge' => [
                'ad' => $tip->etiket(),
                'icerik' => (string) $surum->content,

                /*
                | ⚠️ SÜRÜM ve TARİH gösteriliyor. Müşteri hangi metni
                | okuduğunu, marka da hangi metnin yürürlükte olduğunu
                | tartışmasız bilmeli (1A.4 · 1D-K2).
                */
                'surum' => $surum->version_no,
                'tarih' => $surum->published_at?->format('d.m.Y'),
            ],
        ]);
    }
}
