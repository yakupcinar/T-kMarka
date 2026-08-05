<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Müşterinin adres DEFTERİ kaydı.
 *
 * ⚠️ Sipariş adresi DEĞİL. Sipariş verilirken alanlar `orders` tablosuna
 * kopyalanır; sipariş bu kayda bağlanmaz. Müşteri adresini düzeltirse geçmiş
 * siparişlerin nereye gittiği değişmemeli (docs/domain-model.md §7).
 */
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'title',
        'full_name',
        'phone',
        'city',
        'district',
        'neighborhood',
        'line1',
        'line2',
        'postal_code',
    ];

    /**
     * ⚠️ `customer_id` bilerek `$fillable` DIŞINDA.
     *
     * Adres her zaman bir müşteriye ait olarak, o müşteri üzerinden
     * oluşturulacak: `$customer->addresses()->create([...])`.
     * Listede olsaydı istekten gelen `customer_id` ile başka müşterinin
     * defterine adres eklenebilirdi — kütle atama açığının tam örneği.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
