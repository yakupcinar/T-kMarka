<?php

namespace App\Domain\Review;

use App\Domain\Returns\WithdrawalWindow;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;

/**
 * "Bu müşteri bu ürünü gerçekten aldı mı?" (2E-K1)
 *
 * ★ Yorumun dayanağı bir SİPARİŞ SATIRI. Kontrol olmasaydı rakip ve bot
 * yorumu kaçınılmazdı — hiçbiri hata vermeden.
 */
class PurchaseProof
{
    public function __construct(private readonly WithdrawalWindow $pencere) {}

    /**
     * Müşterinin bu ürüne yorum yazmasını sağlayan sipariş satırı.
     *
     * @throws NotPurchasedException
     */
    public function bul(Customer $musteri, Product $urun): OrderItem
    {
        /*
        | ⚠️ `whereHas` ile SİPARİŞİN sahibine bakılıyor, satıra değil:
        | satırda müşteri yok, sipariş fotoğrafında var.
        |
        | ⚠️ Ödenmemiş sipariş sayılmıyor. Sayılsaydı sepete atıp ödemeden
        | vazgeçen herkes yorum yazabilirdi.
        |
        | ⚠️ İADE EDİLMİŞ sipariş SAYILIYOR: parası geri verilmiş olabilir
        | ama ürünü kullandı ve deneyimi gerçek. Dışlansaydı memnun
        | olmayan müşteri tam da yorumu en değerli olan kişi olarak
        | susturulurdu.
        */
        $satirlar = OrderItem::query()
            ->whereHas('order', function ($sorgu) use ($musteri): void {
                $sorgu->where('customer_id', $musteri->id)
                    ->whereIn('payment_status', [
                        PaymentStatus::Paid,
                        PaymentStatus::PartiallyRefunded,
                        PaymentStatus::Refunded,
                    ]);
            })
            ->whereHas('variant', fn ($sorgu) => $sorgu->where('product_id', $urun->id))
            ->with('fulfillmentItems.fulfillment')
            ->get();

        foreach ($satirlar as $satir) {
            /*
            | ★ TESLİM ŞARTI. "Ödendi" yetmiyor: eline geçmemiş ürün
            | hakkında yorum, ürün deneyimi değil beklenti olurdu.
            |
            | ⚠️ Teslim tespiti [WithdrawalWindow]'dan geliyor, kopyası
            | yazılmadı: orada iptal edilmiş paketlerin sayılmaması gibi
            | ölçülmüş bir incelik var (1D.4). İki kopya olsaydı biri
            | düzeltilir, diğeri sessizce eski davranışta kalırdı.
            */
            if ($this->pencere->teslimTarihi($satir) !== null) {
                return $satir;
            }
        }

        throw new NotPurchasedException('Bu ürüne yorum yazabilmek için teslim almış olmanız gerekiyor.');
    }
}
