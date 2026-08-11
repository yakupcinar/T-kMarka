<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sepet satırı.
 *
 * ⚠️ Fiyat alanı YOK ve olmayacak — fiyat varyanttan CANLI okunuyor.
 * Kopyalansaydı marka fiyatı düşürdüğünde sepette eski fiyat kalır,
 * müşteri vitrinde 199 görüp sepette 249 öderdi.
 *
 * @property int $quantity
 */
class CartItem extends Model
{
    /** ⚠️ `cart_id` ve `variant_id` listede YOK — ilişki üzerinden konuyor. */
    protected $fillable = [
        'quantity',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Bu satır hâlâ satın alınabilir mi? (1C-K2)
     *
     * Üç şey değişmiş olabilir: ürün arşivlendi · varyant kapatıldı ·
     * stok bitti. Üçünde de satır SİLİNMİYOR, işaretleniyor — kullanıcı
     * ne kaybettiğini görsün diye.
     */
    public function kullanilabilirMi(): bool
    {
        $varyant = $this->variant;

        if ($varyant === null) {
            return false;
        }

        // ⚠️ Aynı tek kapı (1B-K8): 1D'de `stock - rezerve > 0` olunca
        // burası kendiliğinden doğru davranacak.
        return $varyant->satinAlinabilirMi()
            && $varyant->product?->status === ProductStatus::Active;
    }

    /** Stok yetiyor mu — adet bazında. */
    public function stokYetiyorMu(): bool
    {
        $varyant = $this->variant;

        // `kullanilabilirMi()` zaten varyantın varlığını doğruluyor, ama
        // statik analiz iki ayrı çağrı arasında bunu taşıyamıyor.
        return $this->kullanilabilirMi() && $varyant !== null && $varyant->stock >= $this->quantity;
    }
}
