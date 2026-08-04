<?php

namespace App\Http\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Caddy'nin "bu alan adı sizin mi?" sorusunu cevaplar.
 *
 * On-demand TLS akışında Caddy, tanımadığı bir alan adı için sertifika
 * almadan önce bu ucu çağırır (M-4.1/1):
 *
 *     200 → alan adı kayıtlı, sertifika alınabilir
 *     404 → kayıtlı değil, sertifika ALINMAZ
 *
 * ⚠️ Bu uç olmadan on-demand TLS açılamaz. Açılırsa IP'mize yönlendirilen
 *    her alan adı için sertifika istenir ve Let's Encrypt kotamız yanar.
 *
 * Kimlik doğrulaması YOK — Caddy kimlik sunamaz. Sızdırdığı tek bilgi
 * "bu alan adı sistemde kayıtlı mı", ki alan adları zaten herkese açık.
 */
class DomainCheckController
{
    public function __invoke(Request $request): Response
    {
        $domain = strtolower(trim((string) $request->query('domain', '')));

        if ($domain === '' || ! Domain::where('domain', $domain)->exists()) {
            return response('', 404);
        }

        return response('', 200);
    }
}
