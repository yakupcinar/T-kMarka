<?php

namespace App\Platform\Subscription;

/**
 * Yapılandırmadaki abonelik sağlayıcısını kurar. (3E)
 *
 * ⚠️ 1E'deki `PaymentProviderFactory` ile aynı desen ama AYRI sınıf: o
 * markanın ayarlarından okuyor, bu MERKEZ yapılandırmadan. Tek fabrikada
 * birleştirilseydi hangi anahtarın kullanılacağı çağrı yerine bağlı kalırdı.
 */
class SubscriptionProviderFactory
{
    public function yap(): SubscriptionProvider
    {
        $ad = (string) config('subscription.provider', 'fake');

        return match ($ad) {
            'fake' => new FakeSubscriptionProvider((string) config('subscription.webhook_secret', '')),

            /*
            | ⚠️ Bilinmeyen sağlayıcı SESSİZCE `fake`'e düşmüyor. Düşseydi
            | yapılandırma hatası olan bir ortam tahsilat yapıyor sanır,
            | hiç para almazdı.
            */
            default => throw new UnknownSubscriptionProviderException("Bilinmeyen abonelik sağlayıcısı: {$ad}"),
        };
    }
}
