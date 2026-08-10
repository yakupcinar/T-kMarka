<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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

    use HasUuids;
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
     * `HasUuids` normalde birincil anahtarı uuid yapar. Biz `id`'yi otomatik
     * artan bırakıp UUID'yi AYRI kolonda tutuyoruz — `Customer` ve `User` ile
     * aynı desen (domain-model §0).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * URL'de `id` değil `uuid` görünsün.
     *
     * ⚠️ Ardışık id ile müşteri komşu numaraları tarayıp mağazadaki toplam
     * adres sayısını çıkarabilirdi. Veri sızmıyordu (sorgu zaten müşteriye
     * daraltılı) ama sayı sızıyordu.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

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
