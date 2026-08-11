<?php

namespace App\Domain\Order;

use RuntimeException;

/**
 * Onaylanan sözleşme sürümü geçersiz.
 *
 * ⚠️ İstemci, müşterinin GÖRDÜĞÜ sürümü gönderiyor (1A.4). Sunucu kendi
 * bildiği güncel sürümü yazsaydı, 10:00:00'da sürüm 7'yi onaylayan müşteri
 * 10:00:03'te yayınlanan 8'e bağlanırdı — görmediği bir metne imza
 * attırmak olurdu.
 *
 * Ama gönderilen sürüm gerçekten MESAFELİ SATIŞ sözleşmesi olmak zorunda:
 * istemci KVKK metninin sürüm numarasını göndererek sözleşme onayını
 * atlayamaz.
 */
class StaleContractException extends RuntimeException
{
    public function __construct(public readonly int $surumId)
    {
        parent::__construct('Onaylanan mesafeli satış sözleşmesi sürümü geçersiz.');
    }
}
