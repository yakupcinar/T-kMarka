<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kategori ağacı — kurma, taşıma, silme. (1B-K6)
 *
 * ⚠️ `path` ve `level` alanlarını YALNIZCA bu sınıf yazıyor. Elle
 * yazılabilseydi ağaç sessizce tutarsız hâle gelir, "Giyim'in altındaki her
 * şey" sorgusu eksik sonuç döner ve kimse fark etmezdi.
 */
class CategoryService
{
    /**
     * Ağacın tamamı, `path` sırasına göre — yani ata hep çocuğundan önce.
     *
     * @return Collection<int, Category>
     */
    public function listele(): Collection
    {
        return Category::orderBy('path')->get();
    }

    /** @throws EmptySlugException */
    public function olustur(string $ad, ?Category $ust = null, int $sira = 0): Category
    {
        return DB::transaction(function () use ($ad, $ust, $sira) {
            $kategori = new Category(['name' => trim($ad), 'position' => $sira]);
            $kategori->slug = $this->slug($ad);

            // `associate()` kullanılıyor, `parent_id = $ust?->id` değil:
            // ilişki üzerinden atamak tip güvenli ve niyeti açık.
            $kategori->parent()->associate($ust);

            /*
            | ⚠️ İKİ AŞAMALI KAYIT — mecburen.
            |
            | `path` kendi id'siyle bitiyor, ama id ancak INSERT'ten sonra
            | biliniyor. Bu yüzden önce geçici bir değerle kaydediliyor,
            | sonra gerçek path yazılıyor. Transaction içinde olduğu için
            | arada kalan hâli kimse görmüyor.
            */
            $kategori->path = '/';
            $kategori->level = 0;
            $kategori->save();

            $kategori->path = $this->ustPath($ust).$kategori->id.'/';
            $kategori->level = $ust === null ? 0 : $ust->level + 1;
            $kategori->save();

            return $kategori;
        });
    }

    /** @throws EmptySlugException */
    public function guncelle(Category $kategori, string $ad, int $sira): Category
    {
        // Ad değişikliği path'i ETKİLEMİYOR — path id zinciri (1B-K6).
        // Slug zinciri olsaydı burada alt ağacın tamamı yeniden yazılırdı.
        $kategori->fill(['name' => trim($ad), 'position' => $sira]);
        $kategori->slug = $this->slug($ad);
        $kategori->save();

        return $kategori;
    }

    /**
     * Kategoriyi başka bir üstün altına taşır — ALT AĞACIYLA BİRLİKTE.
     *
     * @throws CategoryCycleException kategori kendi torununun altına taşınırsa
     */
    public function tasi(Category $kategori, ?Category $yeniUst): Category
    {
        /*
        | ⚠️ DÖNGÜ ENGELİ.
        |
        | "Giyim"i kendi altındaki "Tişört"ün altına taşımak isterse: ağaç
        | kendi kuyruğunu yutar, `path` sonsuza gider ve alt ağaç sorgusu
        | asla dönmez.
        |
        | Kontrol tek satır: hedefin path'i, taşınanın path'iyle BAŞLIYOR mu?
        | Başlıyorsa hedef zaten taşınanın torunudur. (Kendisi de dahil —
        | kategori kendi altına taşınamaz.)
        */
        if ($yeniUst !== null && str_starts_with($yeniUst->path, $kategori->path)) {
            throw new CategoryCycleException($kategori->name, $yeniUst->name);
        }

        if ($kategori->parent_id === $yeniUst?->id) {
            return $kategori;   // zaten orada
        }

        return DB::transaction(function () use ($kategori, $yeniUst) {
            $eskiPath = $kategori->path;
            $yeniPath = $this->ustPath($yeniUst).$kategori->id.'/';
            $yeniSeviye = $yeniUst === null ? 0 : $yeniUst->level + 1;
            $seviyeFarki = $yeniSeviye - $kategori->level;

            /*
            | ALT AĞACIN TAMAMI TEK SORGUDA.
            |
            | Tek tek dolaşılsaydı 500 kategorilik bir dalda 500 UPDATE
            | olurdu; ayrıca yarıda kalırsa ağaç KOPARDI — bir kısmı eski
            | yolu, bir kısmı yeniyi gösterirdi. Transaction + tek ifade.
            |
            | `substring(path, N+1)` → eski ön eki atıp kalanı koruyor,
            | böylece torunların kendi zincirleri bozulmuyor.
            |
            | ⚠️ `?::int` CAST'i ŞART. PostgreSQL'de iki `substring` var:
            |     substring(text, int)   konumdan itibaren kes   ← istediğimiz
            |     substring(text, text)  DÜZENLİ İFADE eşleştir
            | Parametre metin olarak gidince ikincisi seçiliyor: '/1/2/'
            | içinde "6" deseni aranıyor, bulunamıyor ve NULL dönüyor.
            | Ölçüldü — `path` NOT NULL olduğu için patladı; nullable olsaydı
            | ALT AĞACIN TAMAMI sessizce NULL olur, kategoriler ağaçtan
            | düşerdi.
            */
            DB::update(
                'UPDATE categories
                    SET path = ? || substring(path, ?::int),
                        level = level + ?::int
                  WHERE path LIKE ?',
                [$yeniPath, strlen($eskiPath) + 1, $seviyeFarki, $eskiPath.'%'],
            );

            $kategori->parent()->associate($yeniUst);
            $kategori->save();

            return $kategori->refresh();
        });
    }

    /**
     * @throws CategoryHasChildrenException
     */
    public function sil(Category $kategori): void
    {
        /*
        | ⚠️ Alt kategorisi olan silinemez.
        |
        | Cascade olsaydı marka "Giyim"i silince altındaki 40 kategori de
        | sessizce giderdi. Rol silmede aldığımız kararla aynı: sessiz
        | yeniden yapılandırma yerine bilinçli hamle.
        */
        if ($kategori->children()->exists()) {
            throw new CategoryHasChildrenException($kategori->name, $kategori->children()->count());
        }

        // TODO(1B.3): bu kategoride ürün varsa silinemesin.
        // `products.category_id` 1B.3'te doğacak.

        $kategori->delete();
    }

    /**
     * Üstün path'i; kök için '/'.
     *
     * Ayrı metot çünkü `$ust?->path ?? '/'` yazımı statik analizde
     * "gereksiz nullsafe" uyarısı veriyor — `path` alanı null olamıyor,
     * null olabilen `$ust`'ün kendisi.
     */
    private function ustPath(?Category $ust): string
    {
        return $ust === null ? '/' : $ust->path;
    }

    /**
     * @throws EmptySlugException
     *
     * @see OptionService::slug() — aynı gerekçe: Türkçe'de küçük harf
     * çevrimi bölünüyor, benzersizlik anahtarı slug olmalı.
     */
    private function slug(string $metin): string
    {
        $slug = Str::slug($metin);

        if ($slug === '') {
            throw new EmptySlugException($metin);
        }

        return $slug;
    }
}
