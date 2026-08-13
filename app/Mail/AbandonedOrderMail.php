<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailables\Content;

/**
 * Ödemesi yarım kalmış sipariş hatırlatması. (2F)
 *
 * ⚠️ "Ödeme alınamadı" (PaymentFailedMail) ile KARIŞTIRILMAMALI — bunlar
 * iki farklı hikâye: orada müşteri denedi ve reddedildi, burada hiç
 * denemedi. Aynı mail kullanılsaydı vazgeçen müşteri kartında sorun
 * olduğunu sanırdı.
 *
 * ⚠️ Mail STOK SÖZÜ VERMİYOR. Rezervasyon 60 dakikada düşüyor (1D-K3) ve
 * bu mail o süre dolduktan sonra gidiyor; "ürünleriniz sizin için ayrıldı"
 * demek YANLIŞ olurdu. Ödeme yine de kabul ediliyor ama stok açığı
 * işaretleniyor (1E-K5) — yani söz verilse tutulamayabilirdi.
 */
class AbandonedOrderMail extends BrandMail
{
    public function __construct(public readonly Order $siparis) {}

    protected function konu(): string
    {
        return "Siparişiniz sizi bekliyor — {$this->siparis->order_number}";
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.abandoned-order',
            with: $this->marka() + ['siparis' => $this->siparis],
        );
    }
}
