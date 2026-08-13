<?php

namespace App\Domain\Promotion;

use RuntimeException;

/**
 * Kupon uygulanamıyor: yok, süresi geçmiş, kotası dolmuş ya da sepet
 * tutarı yetersiz.
 *
 * ⚠️ 422 — verinin kendisi geçersiz. Sebep müşteriye söyleniyor ("tutar
 * yetersiz" gibi) çünkü müşterinin yapabileceği bir şey var; ama
 * kuponun VARLIĞI hakkında bilgi verilmiyor: "yok" ile "süresi geçmiş"
 * ayrımı, kod deneyerek geçerli kupon aramanın kapısını açardı.
 */
class InvalidCouponException extends RuntimeException {}
