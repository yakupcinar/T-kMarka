<?php

namespace App\Domain\Review;

use App\Domain\Quota\QuotaGuard;
use App\Enums\ReviewStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Yorum yazma ve moderasyon. (2E)
 *
 * ★ ÜÇ KURAL, ÜÇÜ DE BURADA:
 *   1  yalnızca TESLİM ALAN yazabilir      (2E-K1, [PurchaseProof])
 *   2  yorum ONAY BEKLER                    (2E-K2)
 *   3  ortalama yalnızca ONAYLIDAN          (2E-K3, [RatingCounter])
 *
 * ⚠️ Üçü de controller'a yazılsaydı tohumlayıcıdan ya da bir artisan
 * komutundan atlanabilirdi (CLAUDE.md).
 */
class ReviewService
{
    public function __construct(
        private readonly PurchaseProof $kanit,
        private readonly RatingCounter $sayac,
        private readonly QuotaGuard $kota,
    ) {}

    /**
     * Müşteri yorum yazar — ONAY BEKLEYEREK.
     *
     * @param  array<string, mixed>  $veri
     *
     * @throws NotPurchasedException|DuplicateReviewException
     */
    public function yaz(Customer $musteri, Product $urun, array $veri): Review
    {
        /*
        | ★ ÖZELLİK BAYRAĞI (3F). Plan yorumu kapsamıyorsa müşteri yorum
        | yazamıyor.
        |
        | ⚠️ Kontrol EN BAŞTA: satın alma kanıtı sorgusu pahalı ve plan
        | kapalıysa hiç çalıştırmaya gerek yok.
        |
        | ⚠️ VAR OLAN yorumlar vitrinde KALMAYA devam ediyor — plan
        | düşüren markanın müşteri yorumları silinmemeli.
        */
        $this->kota->ozelligiDogrula('reviews');

        // Satın alma kanıtı — yoksa hiçbir kayıt oluşmuyor.
        $satir = $this->kanit->bul($musteri, $urun);

        /*
        | ⚠️ Yumuşak silinmiş yorum da SAYILIYOR (`withTrashed`). Bakılmasaydı
        | müşteri yorumunu silip yenisini yazarak kotayı sonsuz kullanır,
        | veritabanı kısıtı ise `deleted_at`'e bakmadığı için 500 verirdi.
        */
        $var = Review::withTrashed()
            ->where('product_id', $urun->id)
            ->where('customer_id', $musteri->id)
            ->exists();

        if ($var) {
            throw new DuplicateReviewException('Bu ürüne zaten yorum yazdınız.');
        }

        $yorum = new Review;
        $yorum->fill($veri);

        /*
        | ⚠️ Bu üçü `$fillable` DIŞINDA ve burada elle konuyor: müşteri
        | kendi yorumunun ürününü, sahibini ya da durumunu belirleyemez.
        */
        $yorum->product()->associate($urun);
        $yorum->customer()->associate($musteri);
        $yorum->orderItem()->associate($satir);
        $yorum->status = ReviewStatus::Pending;

        $yorum->save();

        return $yorum;
    }

    /**
     * Personel yorumu onaylar.
     *
     * ⚠️ Sayaç tazeleme ile durum değişikliği AYNI transaction'da: araya
     * bir hata girerse yorum onaylı görünüp ortalamaya girmemiş olurdu ve
     * bu ancak gecelik denetimde ortaya çıkardı.
     */
    public function onayla(Review $yorum, User $personel): Review
    {
        return $this->durumDegistir($yorum, ReviewStatus::Approved, $personel, null);
    }

    public function reddet(Review $yorum, User $personel, ?string $gerekce = null): Review
    {
        return $this->durumDegistir($yorum, ReviewStatus::Rejected, $personel, $gerekce);
    }

    private function durumDegistir(Review $yorum, ReviewStatus $durum, User $personel, ?string $gerekce): Review
    {
        return DB::transaction(function () use ($yorum, $durum, $gerekce): Review {
            $yorum->status = $durum;
            $yorum->moderated_at = now();
            $yorum->moderation_note = $gerekce;
            $yorum->save();

            /*
            | ⚠️ Onayda DA reddetmede DE tazeleniyor. Yalnızca onayda
            | yazılsaydı onaylanmış bir yorumun geri alınması ortalamayı
            | düşürmez, puan sessizce şişik kalırdı.
            */
            $urun = $yorum->product;

            if ($urun instanceof Product) {
                $this->sayac->tazele($urun);
            }

            return $yorum;
        });
    }

    /**
     * Müşteri kendi yorumunu siler.
     *
     * ⚠️ Sayaç burada da tazeleniyor: onaylı bir yorum silinince ortalama
     * düşmeli.
     */
    public function sil(Review $yorum): void
    {
        DB::transaction(function () use ($yorum): void {
            $urun = $yorum->product;

            $yorum->delete();

            if ($urun instanceof Product) {
                $this->sayac->tazele($urun);
            }
        });
    }

    /**
     * VİTRİNDE görünen yorumlar — yalnızca onaylı.
     *
     * @return Builder<Review>
     */
    public function vitrindeGorunenler(Product $urun): Builder
    {
        return Review::query()
            ->where('product_id', $urun->id)
            ->where('status', ReviewStatus::Approved)
            ->with('customer')
            ->orderByDesc('moderated_at')
            ->orderByDesc('id');
    }

    /**
     * PANELDE moderasyon kuyruğu.
     *
     * @return Builder<Review>
     */
    public function kuyruk(?ReviewStatus $durum = null): Builder
    {
        $sorgu = Review::query()->with(['product', 'customer'])->orderBy('id');

        return $durum === null ? $sorgu : $sorgu->where('status', $durum);
    }
}
