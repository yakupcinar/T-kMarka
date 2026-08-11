<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Varyant — müşterinin SATIN ALDIĞI şey.
 *
 * Tek seçenekli üründe bile bir varyant vardır, `options` boş kalır (1B-K1).
 * İstisna olsaydı "ürün mü varyant mı" sorusu sepet, stok, sipariş ve fiyat
 * yollarının her birine bir `if` olarak dağılırdı.
 *
 * ⚠️ `@property` notları şart: statik analiz tipleri KOLONDAN çıkarıyor ve
 * `casts()` içindeki dönüşümleri göremiyor — `options`'ı metin, para
 * alanlarını float sanıyor.
 *
 * @property array<string, string> $options eksen slug → değer slug
 * @property string $price `decimal:2` METİN döndürüyor; para float'ta tutulmaz
 * @property string|null $compare_at_price
 * @property string|null $cost_price
 * @property int $committed siparişe bağlanmış, henüz sevk edilmemiş adet
 */
class ProductVariant extends Model
{
    use HasUuids;
    use SoftDeletes;

    /**
     * ⚠️ MODEL VARSAYILANLARI — veritabanı varsayılanı YETMİYOR.
     *
     * `stock` ve `is_active` kolonlarında `default()` var, ama o değer
     * yalnızca DİSKE yazılırken uygulanıyor; INSERT'ten dönen nesnede alan
     * hiç bulunmuyor ve `null` okunuyor. `satinAlinabilirMi()` yeni
     * eklenen varyantta `false` dönüyordu.
     *
     * Aynı tuzağa üçüncü kez düşüldü: `accepts_marketing` (1A.2),
     * `is_system` (1A.6), `is_active` (burada). İlk ikisinde `refresh()`
     * ile çözülmüştü — bu ek sorgu demek ve her çağrı yerinde
     * hatırlanması gerekiyor. Model varsayılanı sorunu kaynağında bitiriyor.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'stock' => 0,
        'committed' => 0,
        'is_active' => true,
    ];

    /** ⚠️ `product_id` listede YOK — ilişki üzerinden konuyor (1A.5 deseni). */
    protected $fillable = [
        'sku',
        'barcode',
        'options',
        'price',
        'compare_at_price',
        'cost_price',
        'stock',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'options' => 'array',

            // ⚠️ Para `decimal:2` — float değil (domain-model §0).
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',

            'stock' => 'integer',
            'committed' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * ★ SATIN ALINABİLİR Mİ — TEK KAPI.
     *
     * ⚠️ Bu kural başka hiçbir yere yazılmayacak. 1D'de stok rezervasyonu
     * gelince cevap `stock - rezerve > 0` olacak ve YALNIZCA BURASI
     * değişecek.
     *
     * Kontrol üç-beş yere serpiştirilseydi 1D'de hepsini bulmak gerekirdi;
     * biri kaçarsa AŞIRI SATIŞ olur — müşteri ödeme yapar, ürün yoktur ve
     * sistem hata vermez.
     */
    public function satinAlinabilirMi(): bool
    {
        return $this->is_active && $this->satilabilirAdet() > 0;
    }

    /**
     * ★ SATILABİLİR ADET — `available` (1D-K1).
     *
     * Shopify'ın "her konumda tutması gereken özdeşlik"inin bizdeki hâli:
     *
     *     stock (on_hand) − committed = available
     *
     * `committed`: siparişe bağlanmış ama henüz sevk edilmemiş adet.
     * Rezervasyon tablosundan `SUM` almak yerine materyalleştirilmiş —
     * yoksa her ürün listesinde bir alt sorgu olurdu.
     */
    public function satilabilirAdet(): int
    {
        return max(0, $this->stock - $this->committed);
    }

    /**
     * ★ Yukarıdaki kuralın SQL İKİZİ — sorguda kullanılır.
     *
     * ⚠️ İKİSİ BİR ÇİFTTİR, BİRLİKTE DEĞİŞİR.
     *
     * Tek uygulama mümkün değil: liste sorgusu bunu veritabanında çözmek
     * zorunda (50 ürünün tüm varyantlarını PHP'ye çekip süzmek anlamsız),
     * tek nesnede ise PHP'de. Bu yüzden yan yana duruyorlar ve
     * ProductTest'te bir test ikisinin AYNI cevabı verdiğini koruyor —
     * biri değişip diğeri unutulursa test kırılır.
     *
     * ✅ 1D.1'de SÖZ ÖDENDİ: kural `stock > 0`'dan `stock − committed > 0`'a
     * çevrildi ve İKİSİ BİRDEN güncellendi. Testi 1B.5'te yazmıştık, tam da
     * bunun için.
     *
     * @param  Builder<ProductVariant>  $sorgu
     * @return Builder<ProductVariant>
     */
    public function scopeSatinAlinabilir($sorgu)
    {
        return $sorgu->where('is_active', true)->whereRaw('stock - committed > 0');
    }
}
