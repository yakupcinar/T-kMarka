<?php

namespace App\Mail;

use App\Enums\ShipmentStatus;
use App\Models\Fulfillment;
use Illuminate\Mail\Mailables\Content;

/**
 * Kargoya verildi / teslim edildi.
 *
 * ⚠️ İki mail TEK sınıfta: gövde neredeyse aynı, değişen tek şey durum.
 * Ayrılsaydı takip numarası biçimi iki yerde durur ve biri unutulurdu.
 *
 * ⚠️ PAKET bazında gidiyor, sipariş bazında değil: kısmi sevkiyat var
 * (1D.4). Sipariş bazında gitseydi üç paketli siparişte müşteri tek
 * mail alır, ilk paket geldiğinde kalanını beklemesi gerektiğini bilmezdi.
 */
class ShipmentMail extends BrandMail
{
    public function __construct(public readonly Fulfillment $paket) {}

    protected function konu(): string
    {
        /*
        | ⚠️ İlişki teknik olarak boş dönebiliyor (yabancı anahtar
        | nullable değil ama statik analiz bunu bilmiyor). Konu satırı
        | numarasız kalsın, posta hiç gitmemesin.
        */
        $siparis = $this->paket->order;
        $no = $siparis === null ? '' : $siparis->order_number;

        return $this->paket->status === ShipmentStatus::Delivered
            ? "Siparişiniz teslim edildi — {$no}"
            : "Siparişiniz kargoya verildi — {$no}";
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.shipment',
            with: $this->marka() + [
                'paket' => $this->paket->load('items.orderItem', 'order'),
                'teslim' => $this->paket->status === ShipmentStatus::Delivered,
            ],
        );
    }
}
