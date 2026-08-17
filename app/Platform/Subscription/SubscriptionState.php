<?php

namespace App\Platform\Subscription;

/**
 * Sağlayıcıdaki abonelik durumu. (3E)
 *
 * ⚠️ iyzico'nun durumları BİREBİR alınıyor; kendi `TenantStatus`'ümüze
 * çevirme işi [SubscriptionService]'te. Burada çevrilseydi sağlayıcının
 * söylediği ile bizim kaydımız arasındaki fark görünmez olurdu.
 */
enum SubscriptionState: string
{
    case Active = 'active';

    /** Ödeme alınamadı — bizim `past_due` karşılığımız. */
    case Unpaid = 'unpaid';

    case Canceled = 'canceled';

    /** Süresi doldu (sınırlı tekrar sayısı verilmişse). */
    case Expired = 'expired';

    /** Duraklatılmış — iyzico'da `PENDING`. */
    case Pending = 'pending';
}
