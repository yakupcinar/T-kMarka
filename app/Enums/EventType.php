<?php

namespace App\Enums;

/**
 * Kaydedilen olay tipleri. (docs/domain-model.md §11 · 1F)
 *
 * ⚠️ Enum, serbest metin DEĞİL: `'cart_item_add'` yazan tek satır hata
 * vermez, hiçbir raporda görünmeyen bir tip üretir ve eksiklik ancak
 * aylar sonra "sepete ekleme sayısı neden düşük" diye fark edilir.
 *
 * ⚠️ Tüketicisi ŞU AN YOK. Besleyeceği şeyler sonra geliyor: terk edilmiş
 * sepet hatırlatması (Faz 2), ürün önerisi (Faz 3), markanın satış raporu.
 * Veri toplanmaya şimdi başlıyor çünkü geçmiş sonradan üretilemiyor.
 */
enum EventType: string
{
    case ProductViewed = 'product_viewed';

    /**
     * ⚠️ Arama ucu Faz 2'de geliyor; tip şimdiden tanımlı çünkü tablo ve
     * raporlar ona göre kuruluyor. Üreten yer yok, bu bilinçli.
     */
    case SearchPerformed = 'search_performed';

    case CartItemAdded = 'cart_item_added';

    case CartItemRemoved = 'cart_item_removed';

    case OrderPlaced = 'order_placed';
}
