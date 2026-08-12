<?php

namespace App\Domain\Payment;

/**
 * Webhook işlemenin sonucu — sağlayıcıya değil BİZE bilgi.
 *
 * ⚠️ Üçü de HTTP 200 dönüyor. `ZatenIslendi` bir hata değil: sağlayıcı
 * açısından bildirim başarıyla teslim edildi, tekrar denemesine gerek yok.
 * Hata dönseydi iyzico 15 dakika sonra yine dener, biz yine "zaten
 * işlendi" der ve bu üç kez sürerdi.
 */
enum WebhookResult: string
{
    case Odendi = 'paid';
    case Basarisiz = 'failed';
    case ZatenIslendi = 'already_processed';
}
