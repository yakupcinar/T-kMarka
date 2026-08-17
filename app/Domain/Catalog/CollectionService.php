<?php

namespace App\Domain\Catalog;

use App\Domain\Quota\QuotaGuard;
use App\Enums\CollectionType;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

/**
 * Koleksiyon yönetimi. (2D)
 *
 * ★ BU SINIFIN ASIL İŞİ: `type` ile `rules`'un birlikte tutarlı kalması.
 * İkisi ayrı ayrı yazılabilseydi tipi `rule`, kuralı `null` olan bir
 * koleksiyon oluşurdu — vitrin onu açtığında ne ürün gösterirdi ne hata
 * verirdi.
 */
class CollectionService
{
    public function __construct(private readonly QuotaGuard $kota) {}

    /** @return EloquentCollection<int, ProductCollection> */
    public function listele(): EloquentCollection
    {
        return ProductCollection::query()->orderBy('position')->orderBy('id')->get();
    }

    /**
     * @param  array<string, mixed>  $veri
     * @param  array<string, mixed>|null  $kural  yalnızca `rule` tipinde
     *
     * @throws CollectionRuleException|EmptySlugException
     */
    public function olustur(array $veri, CollectionType $tip, ?array $kural = null): ProductCollection
    {
        /*
        | ★ ÖZELLİK BAYRAĞI (3F). Plan koleksiyonu kapsamıyorsa YENİ
        | koleksiyon açılamıyor.
        |
        | ⚠️ VAR OLAN koleksiyonlar SİLİNMİYOR ve listelenmeye devam
        | ediyor: plan düşüren marka verisini kaybetmemeli. Kota yeni
        | işlemi engelliyor, geçmişi değil.
        */
        $this->kota->ozelligiDogrula('collections');

        $koleksiyon = new ProductCollection;
        $koleksiyon->fill($veri);
        $koleksiyon->slug = $this->benzersizSlug((string) ($veri['title'] ?? ''));

        $this->tipiAyarla($koleksiyon, $tip, $kural);

        $koleksiyon->save();

        return $koleksiyon;
    }

    /**
     * @param  array<string, mixed>  $veri
     * @param  array<string, mixed>|null  $kural
     *
     * @throws CollectionRuleException
     */
    public function guncelle(ProductCollection $koleksiyon, array $veri, ?CollectionType $tip = null, ?array $kural = null): ProductCollection
    {
        $koleksiyon->fill($veri);

        /*
        | ⚠️ Başlık değişince slug DEĞİŞMİYOR — ürünle aynı gerekçe:
        | paylaşılmış bağlantı ölmemeli.
        */
        if ($tip !== null) {
            $this->tipiAyarla($koleksiyon, $tip, $kural);
        } elseif ($kural !== null) {
            $this->tipiAyarla($koleksiyon, $koleksiyon->type, $kural);
        }

        $koleksiyon->save();

        return $koleksiyon;
    }

    /**
     * Manuel koleksiyona ürün ekler.
     *
     * @throws CollectionRuleException
     */
    public function urunEkle(ProductCollection $koleksiyon, Product $urun, int $sira = 0): void
    {
        $this->manuelOlmali($koleksiyon);

        /*
        | ⚠️ `syncWithoutDetaching` DEĞİL `attach`: aynı ürün iki kez
        | eklenirse veritabanı kısıtı patlardı. Burada sıra da
        | güncellenebilsin diye `updateOrCreate` davranışı isteniyor.
        */
        $koleksiyon->products()->syncWithoutDetaching([$urun->id => ['position' => $sira]]);
    }

    /** @throws CollectionRuleException */
    public function urunCikar(ProductCollection $koleksiyon, Product $urun): void
    {
        $this->manuelOlmali($koleksiyon);

        $koleksiyon->products()->detach($urun->id);
    }

    /**
     * Manuel koleksiyonun sırasını baştan yazar.
     *
     * @param  list<int>  $urunIdleri
     *
     * @throws CollectionRuleException
     */
    public function sirala(ProductCollection $koleksiyon, array $urunIdleri): void
    {
        $this->manuelOlmali($koleksiyon);

        $sira = 0;

        foreach ($urunIdleri as $id) {
            /*
            | ⚠️ `updateExistingPivot` — koleksiyonda OLMAYAN bir id
            | gönderilirse hiçbir şey olmuyor, ürün eklenmiyor. Sıralama
            | ucu üyelik değiştirmemeli; değiştirseydi bir sıralama isteği
            | sessizce yeni ürün sokardı.
            */
            $koleksiyon->products()->updateExistingPivot($id, ['position' => $sira]);
            $sira++;
        }
    }

    public function sil(ProductCollection $koleksiyon): void
    {
        /*
        | ⚠️ Pivot satırları DURUYOR (yumuşak silme). Koleksiyon geri
        | alınırsa listesi de geri gelsin diye; `cascadeOnDelete` yalnızca
        | gerçek silmede çalışıyor.
        */
        $koleksiyon->delete();
    }

    /**
     * @param  array<string, mixed>|null  $kural
     *
     * @throws CollectionRuleException
     */
    private function tipiAyarla(ProductCollection $koleksiyon, CollectionType $tip, ?array $kural): void
    {
        if ($tip === CollectionType::Rule) {
            // Doğrulanmış ve normalleştirilmiş hâli saklanıyor.
            $koleksiyon->rules = CollectionRules::dogrula($kural);
            $koleksiyon->type = $tip;

            return;
        }

        /*
        | ⚠️ Manuele dönerken kural SİLİNİYOR. Kalsaydı koleksiyon manuel
        | görünür, kural gövdede öylece durur ve bir gün tip geri
        | çevrildiğinde markanın hatırlamadığı eski kural yürürlüğe girerdi.
        */
        $koleksiyon->rules = null;
        $koleksiyon->type = $tip;
    }

    /** @throws CollectionRuleException */
    private function manuelOlmali(ProductCollection $koleksiyon): void
    {
        if ($koleksiyon->type !== CollectionType::Manual) {
            /*
            | ⚠️ Kurallı koleksiyona elle ürün eklenemez. İzin verilseydi
            | "bu ürün neden burada" sorusunun iki farklı cevabı olurdu ve
            | elle eklenen ürün kural onu dışlasa bile listede kalırdı.
            */
            throw new CollectionRuleException('Kurallı koleksiyona elle ürün eklenemez.');
        }
    }

    /** @throws EmptySlugException */
    private function benzersizSlug(string $baslik): string
    {
        $taban = Str::slug($baslik);

        if ($taban === '') {
            throw new EmptySlugException($baslik);
        }

        $slug = $taban;
        $sayac = 2;

        while (ProductCollection::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $taban.'-'.$sayac;
            $sayac++;
        }

        return $slug;
    }
}
