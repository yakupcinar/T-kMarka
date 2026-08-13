<?php

namespace App\Domain\Catalog;

use App\Domain\Search\ProductSearch;
use App\Domain\Settings\SettingsService;
use App\Enums\ProductStatus;
use App\Enums\SettingGroup;
use App\Models\Category;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ürün — oluşturma, düzenleme, eksen bağlama, durum değiştirme.
 *
 * Varyantlar ayrı serviste ([VariantService]): varyant kuralları (eksen
 * doğrulama, benzersizlik, sınır) kendi başına bir küme ve ürün
 * düzenlemekle karışmaları gerekmiyor.
 */
class ProductService
{
    /** Bir üründe en fazla kaç eksen olabilir (1B-K4). */
    public const MAKS_EKSEN = 3;

    public function __construct(
        private readonly SettingsService $ayarlar,
        private readonly ProductSearch $arama,
    ) {}

    /**
     * @param  array<string, mixed>  $veri  title · description · brand · model · attributes · tax_rate
     *
     * @throws EmptySlugException
     */
    public function olustur(array $veri, ?Category $kategori = null): Product
    {
        $urun = new Product($veri);

        $urun->slug = $this->benzersizSlug((string) ($veri['title'] ?? ''));
        $urun->category()->associate($kategori);

        /*
        | ⚠️ KDV oranı boş bırakılırsa mağaza varsayılanından DOLDURULUYOR,
        | kolonun varsayılanına bırakılmıyor.
        |
        | Kolonda varsayılan olsaydı marka oranı sonradan değiştirdiğinde
        | "eski ürünler eski oranda mı kalıyor yoksa değişiyor mu" belirsiz
        | kalırdı. Ürünün kendi oranı satırında YAZILI olunca soru ortadan
        | kalkıyor — sipariş fotoğrafı ilkesinin katalog tarafı.
        */
        if (! isset($veri['tax_rate'])) {
            $urun->tax_rate = (string) $this->ayarlar->al(SettingGroup::Tax, 'default_rate', 20);
        }

        // Yeni ürün her zaman TASLAK doğar; satışa almak ayrı bir karar
        // (ve denetimli — bkz. durumDegistir).
        $urun->status = ProductStatus::Draft;
        $urun->save();

        /*
        | ⚠️ ARAMA ALANLARI HER DEĞİŞİKLİKTE tazeleniyor (2C).
        | Unutulursa arama bayat kalır ve bu HATA VERMEZ — yalnızca yeni
        | ürün bulunamaz.
        */
        $this->arama->tazele($urun);

        return $urun;
    }

    /**
     * @param  array<string, mixed>  $veri
     *
     * @throws EmptySlugException
     */
    public function guncelle(Product $urun, array $veri, ?Category $kategori = null): Product
    {
        $urun->fill($veri);

        /*
        | ⚠️ Başlık değişince slug DEĞİŞMİYOR.
        |
        | Değişseydi ürünün adresi de değişir, paylaşılmış bağlantılar ve
        | arama motorundaki kayıtlar kırılırdı. Adres bir kez kurulur;
        | başlık serbestçe düzenlenebilir. (Kategori taşımada da aynı
        | kaygıyla düz adres seçmiştik — 1B-K9.)
        */
        $urun->category()->associate($kategori);
        $urun->save();

        $this->arama->tazele($urun);

        return $urun;
    }

    /**
     * Ürünün kullanacağı eksenleri belirler (sıra = dizideki sıra).
     *
     * @param  list<Option>  $eksenler
     *
     * @throws TooManyOptionsException
     * @throws OptionsLockedException
     */
    public function eksenleriAyarla(Product $urun, array $eksenler): Product
    {
        if (count($eksenler) > self::MAKS_EKSEN) {
            throw new TooManyOptionsException(count($eksenler));
        }

        /*
        | ⚠️ Varyant varken eksen değiştirilemez.
        |
        | Değiştirilebilseydi eldeki varyantlar anında geçersizleşirdi:
        | "Beden" eklenince `{"renk":"kirmizi"}` varyantı eksik anahtarlı
        | olur, ürün sayfasında seçilemez hâle gelir ve stok orada asılı
        | kalırdı — hata vermeden.
        */
        $varyantSayisi = $urun->variants()->count();

        if ($varyantSayisi > 0) {
            throw new OptionsLockedException($varyantSayisi);
        }

        $bagla = [];

        foreach ($eksenler as $sira => $eksen) {
            $bagla[$eksen->id] = ['position' => $sira];
        }

        $urun->options()->sync($bagla);

        return $urun->load('options');
    }

    /**
     * @throws ProductHasNoVariantsException
     */
    public function durumDegistir(Product $urun, ProductStatus $durum): Product
    {
        /*
        | ⚠️ Satışa almanın şartı: en az bir varyant (1B-K1).
        |
        | Taslakta varyantsız durabilmesi kasıtlı — marka ürünü birkaç
        | oturumda hazırlıyor. Ama satışa alınan ürünün satılacak bir şeyi
        | olmak zorunda. 1A.4'teki asimetrinin aynısı: taslağa yazmak
        | serbest, yayınlamak denetimli.
        */
        if ($durum === ProductStatus::Active && $urun->variants()->count() === 0) {
            throw new ProductHasNoVariantsException($urun->title);
        }

        $urun->status = $durum;
        $urun->save();

        return $urun;
    }

    /**
     * Ürünü siler (yumuşak).
     *
     * Varyantlar da yumuşak siliniyor: sert silinselerdi 1D'de siparişe
     * bağlanan varyant satırı kaybolur, geçmiş siparişin "ne satıldı"
     * bilgisi kopardı.
     */
    public function sil(Product $urun): void
    {
        DB::transaction(function () use ($urun) {
            $urun->variants()->delete();
            $urun->delete();
        });
    }

    /** @return Collection<int, Product> */
    public function listele(): Collection
    {
        return Product::with(['category', 'options', 'variants'])->orderByDesc('id')->get();
    }

    /**
     * Başlıktan benzersiz slug üretir: `tisort`, `tisort-2`, `tisort-3`…
     *
     * ⚠️ Eksen ve kategoride çakışmayı REDDEDİYORDUK, burada SONEK
     * ekliyoruz. Fark niyette: "Renk" ekseni iki kez tanımlanıyorsa bu bir
     * hatadır ve marka bilmeli. Ama aynı başlıklı iki ürün olağan
     * ("Basic Tişört" iki farklı koleksiyonda) ve markayı ad uydurmaya
     * zorlamak anlamsız. Shopify ve WooCommerce da sonek ekliyor.
     *
     * @throws EmptySlugException
     */
    private function benzersizSlug(string $baslik): string
    {
        $taban = Str::slug($baslik);

        if ($taban === '') {
            throw new EmptySlugException($baslik);
        }

        $slug = $taban;
        $sayac = 2;

        // ⚠️ `withTrashed`: silinmiş ürünün slug'ı hâlâ benzersizlik
        // kısıtında duruyor (yumuşak silme). Bakılmasaydı ikinci kayıt
        // veritabanı hatasıyla düşerdi.
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $taban.'-'.$sayac;
            $sayac++;
        }

        return $slug;
    }
}
