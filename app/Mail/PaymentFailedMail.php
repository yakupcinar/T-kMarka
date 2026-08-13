<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailables\Content;

/**
 * Ödeme alınamadı.
 *
 * ⚠️ Bu mail 1E.7.3'te ölçülen bir boşluğu kapatıyor: yetersiz bakiyede
 * müşteri neden reddedildiğini öğrenemiyordu. Sipariş silinmiyor (1D),
 * yani müşteri aynı numarayla tekrar deneyebilir.
 *
 * ⚠️ Sağlayıcının HAM hata mesajı GÖNDERİLMİYOR — hesap yapılandırmasına
 * dair ayrıntı içerebiliyor (1E.7.3'te 502 eşlemesinde de aynı karar).
 */
class PaymentFailedMail extends BrandMail
{
    public function __construct(public readonly Order $siparis) {}

    protected function konu(): string
    {
        return "Ödeme alınamadı — {$this->siparis->order_number}";
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-failed',
            with: $this->marka() + ['siparis' => $this->siparis],
        );
    }
}
