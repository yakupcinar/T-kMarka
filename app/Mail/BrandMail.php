<?php

namespace App\Mail;

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Marka postalarının ortak tabanı. (2H)
 *
 * ★ HEPSİ KUYRUKTA (2H-K1): `ShouldQueue`. Müşteri mailin gitmesini
 * beklemiyor — bir saniye geç gitmesinin zararı yok, isteğin bir saniye
 * uzamasının var.
 *
 * ⚠️ ★ KİRACILIK TUZAĞI (M-2.4). Bu posta İŞÇİ SÜRECİNDE üretiliyor:
 * modeller oradan yeniden okunuyor, gönderen adresi oradaki `settings`'ten
 * alınıyor. Kiracı kimliği işin gövdesinde taşınıyor (0.5'te ölçüldü);
 * taşınmasaydı A markasının siparişi için B'nin adıyla mail giderdi —
 * ve hata vermezdi.
 *
 * ⚠️ Gönderen bilgisi ATIŞ ANINDA GÖMÜLMÜYOR, işçide okunuyor. Gömülseydi
 * marka iletişim adresini değiştirdikten sonra kuyrukta bekleyen postalar
 * eski adresle giderdi.
 */
abstract class BrandMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    abstract protected function konu(): string;

    public function envelope(): Envelope
    {
        $ayarlar = app(SettingsService::class);

        $ad = $ayarlar->al(SettingGroup::Store, 'name');
        $eposta = $ayarlar->al(SettingGroup::Store, 'contact_email');

        return new Envelope(
            /*
            | ⚠️ Gönderen MARKANIN adresi — platformun değil. Müşteri
            | "TıkMarka"dan değil alışveriş yaptığı markadan mail almalı.
            |
            | `contact_email` mağaza yayına alınırken ZORUNLU (1A.4
            | StoreReadiness), yani yayında bir markada her zaman dolu.
            | Yine de yayın kapatılmış bir markada boş kalabileceği için
            | yapılandırmadaki adrese düşülüyor.
            */
            from: new Address(
                is_string($eposta) && $eposta !== '' ? $eposta : (string) config('mail.from.address'),
                is_string($ad) && $ad !== '' ? $ad : (string) config('mail.from.name'),
            ),
            subject: $this->konu(),
        );
    }

    /**
     * Şablonların ortak marka değişkenleri. (2H-K3)
     *
     * ⚠️ Metin ve düzen KODDA; markadan yalnızca bunlar geliyor. Yasal
     * metinler gibi sürümlenmesine gerek yok — mail geçmişe dönük bir
     * dayanak değil (1A.4'teki ayrım).
     *
     * @return array<string, mixed>
     */
    protected function marka(): array
    {
        $ayarlar = app(SettingsService::class);

        return [
            'markaAdi' => $ayarlar->al(SettingGroup::Store, 'name', 'Mağaza'),
            'iletisim' => $ayarlar->al(SettingGroup::Store, 'contact_email'),
            'telefon' => $ayarlar->al(SettingGroup::Store, 'phone'),
        ];
    }
}
