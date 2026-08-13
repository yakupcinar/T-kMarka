<?php

namespace App\Mail;

use App\Enums\DataRequestType;
use App\Models\DataRequest;
use Illuminate\Mail\Mailables\Content;

/**
 * Veri talebi doğrulama postası. (2G-K3)
 *
 * ⚠️ Bu mail talebin TEK korumasıdır. Olmasaydı sipariş numarası tahmin
 * eden biri (numaralar ardışık, 1D-K4) başkasının verisini sildirebilirdi.
 */
class PrivacyVerificationMail extends BrandMail
{
    public function __construct(
        public readonly DataRequest $talep,
        public readonly string $adres,
    ) {}

    protected function konu(): string
    {
        return $this->talep->type === DataRequestType::Anonymize
            ? 'Veri silme talebinizi onaylayın'
            : 'Veri indirme talebinizi onaylayın';
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.privacy-verification',
            with: $this->marka() + [
                'silme' => $this->talep->type === DataRequestType::Anonymize,
                'adres' => $this->adres,
                'sonGecerlilik' => $this->talep->expires_at,
            ],
        );
    }
}
