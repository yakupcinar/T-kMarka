<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailables\Content;

/**
 * Sipariş onayı.
 *
 * ⚠️ ÖDEME BAŞARILI OLUNCA gider, sipariş oluşunca DEĞİL.
 *
 * Sipariş `pending` doğuyor (1D) ve ödemesi hiç tamamlanmayabiliyor.
 * Oluşma anında gönderilseydi, ödeme sayfasını açıp vazgeçen her müşteri
 * "siparişiniz alındı" maili alır ve gelmeyecek bir kargoyu beklerdi.
 */
class OrderPaidMail extends BrandMail
{
    public function __construct(public readonly Order $siparis) {}

    protected function konu(): string
    {
        return "Siparişiniz alındı — {$this->siparis->order_number}";
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-paid',
            with: $this->marka() + ['siparis' => $this->siparis->load('items')],
        );
    }
}
