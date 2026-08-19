<?php

namespace App\Http\Panel;

use App\Domain\Review\ReviewService;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Yorum moderasyonu. (4.5F)
 *
 * ★ EKRANI OLMAYAN SON ALANDI. Uçları 2E'de vardı; ekran olmadığı için
 * müşteri yorumları hiç onaylanamıyordu — yani vitrinde hiçbir yorum
 * görünmüyordu ve marka bunu fark edemiyordu.
 *
 * ⚠️ Yorum ONAYLANMADAN vitrinde görünmüyor ve ortalamaya girmiyor (2E).
 * Bu ekran o kuyruğun tek çıkışı.
 */
class ReviewPageController extends Controller
{
    public const SAYFA = 25;

    public function __construct(private readonly ReviewService $yorumlar) {}

    public function index(Request $istek): Response
    {
        $durum = (string) $istek->query('durum', ReviewStatus::Pending->value);

        $sorgu = Review::query()->with(['product', 'customer']);

        if (ReviewStatus::tryFrom($durum) !== null) {
            $sorgu->where('status', $durum);
        }

        /*
        | ⚠️ ESKİDEN YENİYE sıralanıyor — listenin geri kalanının tersine.
        | Moderasyon kuyruğunda en eski yorum en çok bekleyen demek;
        | yeniden eskiye sıralansaydı ilk yazan müşteri en son sırada
        | kalırdı.
        */
        $yorumlar = $sorgu->orderBy('id')->paginate(self::SAYFA)->withQueryString();

        return Inertia::render('Yorumlar', [
            'yorumlar' => $yorumlar->through(fn (Review $y) => [
                'uuid' => $y->uuid,
                'rating' => $y->rating,
                'title' => $y->title,
                'body' => $y->body,
                'status' => $y->status->value,
                'created_at' => $y->created_at?->format('d.m.Y H:i'),
                'product' => $y->product?->title,

                /*
                | ⚠️ Panelde TAM AD görünüyor, vitrinde kısaltılmış (2E).
                | Marka kimin yazdığını bilmeli; ziyaretçi bilmemeli.
                */
                'customer' => $y->customer?->name,
                'moderation_note' => $y->moderation_note,
            ]),

            'durum' => $durum,
            'bekleyen' => Review::where('status', ReviewStatus::Pending)->count(),
        ]);
    }

    public function onayla(Request $istek, Review $yorum): RedirectResponse
    {
        $this->yorumlar->onayla($yorum, $this->personel($istek));

        return back()->with('mesaj', 'Yorum onaylandı.');
    }

    public function reddet(Request $istek, Review $yorum): RedirectResponse
    {
        $veri = $istek->validate(['note' => ['nullable', 'string', 'max:255']]);

        /*
        | ⚠️ Gerekçe İSTEĞE BAĞLI ama saklanıyor: "neden reddedildi"
        | sorusu sonradan cevaplanabilmeli. Vitrinde GÖRÜNMÜYOR (2E).
        */
        $this->yorumlar->reddet($yorum, $this->personel($istek), $veri['note'] ?? null);

        return back()->with('mesaj', 'Yorum reddedildi.');
    }

    private function personel(Request $istek): User
    {
        $kullanici = $istek->user('staff-web');

        abort_unless($kullanici instanceof User, 403);

        return $kullanici;
    }
}
