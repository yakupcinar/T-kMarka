<?php

namespace App\Domain\Order;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Fulfillment;
use App\Models\FulfillmentItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Sevkiyat — kısmi gönderim. (docs/domain-model.md §7)
 *
 * ★ TEK DOĞRULAMA KURALI:
 *
 *   Bir `order_item`'ın toplam sevk edilen adedi, sipariş adedini GEÇEMEZ.
 *
 * ⚠️ Bu kural tek bir yerde uygulanıyor. Dağıtılsaydı: paket oluşturma,
 * paket düzenleme ve iptal-sonrası-yeniden-sevk yollarının her birinde
 * ayrı ayrı yazılması gerekirdi ve biri unutulunca marka aynı ürünü iki
 * kez gönderirdi — sistem hata vermeden.
 */
class FulfillmentService
{
    /**
     * Paket oluşturur.
     *
     * @param  array<int, int>  $satirlar  order_item_id → adet
     *
     * @throws OrderNotShippableException
     * @throws OverShipmentException
     */
    public function olustur(Order $siparis, array $satirlar, ?string $kargoFirmasi = null, ?string $takipNo = null): Fulfillment
    {
        /*
        | ⚠️ Ödenmemiş sipariş sevk edilemiyor.
        |
        | Kapıda ödeme (COD) olsaydı bu kural gevşerdi — Faz 1'de yok.
        | Olmasaydı ödeme başarısız olan bir sipariş kargoya verilebilirdi
        | ve para hiç tahsil edilmezdi.
        */
        if ($siparis->payment_status !== PaymentStatus::Paid) {
            throw new OrderNotShippableException($siparis->order_number, $siparis->payment_status);
        }

        $satirlar = array_filter($satirlar, fn (int $adet) => $adet > 0);

        if ($satirlar === []) {
            throw new OverShipmentException('—', 0, 0);
        }

        return DB::transaction(function () use ($siparis, $satirlar, $kargoFirmasi, $takipNo) {
            $paket = new Fulfillment(['carrier' => $kargoFirmasi, 'tracking_number' => $takipNo]);
            $paket->order()->associate($siparis);
            $paket->status = ShipmentStatus::Pending;
            $paket->save();

            foreach ($satirlar as $satirId => $adet) {
                /*
                | ⚠️ Satır SİPARİŞE DARALTILMIŞ sorgudan çözülüyor (1A.5
                | deseni): başka siparişin satırı sonuç kümesine hiç
                | girmiyor. Düz `OrderItem::find()` kullanılsaydı marka
                | A'nın paketine B'nin satırı konabilirdi.
                */
                $satir = $siparis->items()->where('id', $satirId)->firstOrFail();

                $this->asimiDogrula($satir, $adet);

                $kalem = new FulfillmentItem;
                $kalem->fulfillment()->associate($paket);
                $kalem->orderItem()->associate($satir);
                $kalem->quantity = $adet;
                $kalem->save();
            }

            $this->siparisDurumunuGuncelle($siparis);

            return $paket->load('items');
        });
    }

    /** Kargoya verildi. */
    public function kargoyaVer(Fulfillment $paket, ?string $kargoFirmasi = null, ?string $takipNo = null): Fulfillment
    {
        $paket->fill(array_filter([
            'carrier' => $kargoFirmasi,
            'tracking_number' => $takipNo,
        ]));

        $paket->status = ShipmentStatus::Shipped;
        $paket->shipped_at = now();
        $paket->save();

        return $paket;
    }

    public function teslimEdildi(Fulfillment $paket): Fulfillment
    {
        $paket->status = ShipmentStatus::Delivered;
        $paket->delivered_at = now();
        $paket->save();

        return $paket;
    }

    /**
     * Paketi iptal eder.
     *
     * ⚠️ Kalemler SİLİNMİYOR — paket denetim izi olarak duruyor. İptal
     * edilen paketin adetleri "sevk edilmiş" sayılmadığı için o satırlar
     * yeniden sevk edilebilir hâle geliyor; sipariş durumu da yeniden
     * hesaplanıyor.
     */
    public function iptal(Fulfillment $paket): Fulfillment
    {
        DB::transaction(function () use ($paket) {
            $paket->status = ShipmentStatus::Cancelled;
            $paket->save();

            $siparis = $paket->order;

            if ($siparis !== null) {
                $this->siparisDurumunuGuncelle($siparis);
            }
        });

        return $paket;
    }

    /**
     * ★ `orders.fulfillment_status` TÜRETİLİYOR — elle yazılmıyor.
     *
     * ⚠️ Elle yazılan bir alan olsaydı kısmi sevkiyatta gerçekle uyuşmayan
     * bir durum kalırdı: marka üçüncü paketi gönderir, alan hâlâ "partial"
     * gösterir ve kimse fark etmezdi.
     *
     *   hiç sevk edilmemiş  → unfulfilled
     *   bir kısmı gitmiş    → partial
     *   hepsi gitmiş        → fulfilled
     */
    public function siparisDurumunuGuncelle(Order $siparis): FulfillmentStatus
    {
        $siparis->load('items');

        $toplamAdet = 0;
        $sevkEdilen = 0;

        foreach ($siparis->items as $satir) {
            $toplamAdet += $satir->quantity;
            $sevkEdilen += $this->sevkEdilenAdet($satir);
        }

        $durum = match (true) {
            $sevkEdilen === 0 => FulfillmentStatus::Unfulfilled,
            $sevkEdilen >= $toplamAdet => FulfillmentStatus::Fulfilled,
            default => FulfillmentStatus::Partial,
        };

        $siparis->fulfillment_status = $durum;
        $siparis->save();

        return $durum;
    }

    /**
     * Bir satırın SEVK EDİLMİŞ adedi.
     *
     * ⚠️ İptal edilen paketler sayılmıyor — o satırlar yeniden sevk
     * edilebilir olmalı.
     */
    public function sevkEdilenAdet(OrderItem $satir): int
    {
        return (int) FulfillmentItem::where('order_item_id', $satir->id)
            ->whereHas('fulfillment', fn ($sorgu) => $sorgu->where('status', '!=', ShipmentStatus::Cancelled->value))
            ->sum('quantity');
    }

    /**
     * @throws OverShipmentException
     */
    private function asimiDogrula(OrderItem $satir, int $eklenecek): void
    {
        $mevcut = $this->sevkEdilenAdet($satir);

        if ($mevcut + $eklenecek > $satir->quantity) {
            throw new OverShipmentException($satir->sku, $satir->quantity, $mevcut + $eklenecek);
        }
    }
}
