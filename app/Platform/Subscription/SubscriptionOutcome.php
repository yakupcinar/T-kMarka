<?php

namespace App\Platform\Subscription;

/**
 * Bir abonelik bildiriminin ÇÖZÜLMÜŞ hâli. (3E)
 *
 * ⚠️ `referans` olmadan bildirim işe yaramaz: hangi markanın aboneliği
 * olduğunu ondan buluyoruz.
 */
final readonly class SubscriptionOutcome
{
    public function __construct(
        public string $referans,
        public SubscriptionState $durum,

        /**
         * ⚠️ Tutar KARŞILAŞTIRMA için taşınıyor, kayıt için değil.
         * 1E'de ölçüldü: sağlayıcı "başarılı" deyip beklenenden farklı
         * tutar döndürebiliyor. "Cevabın durumuna değil sonucuna bak."
         */
        public ?string $tutar = null,
    ) {}
}
