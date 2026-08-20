<?php

namespace App\Http\Platform;

use App\Enums\TenantStatus;
use App\Platform\Models\Domain;
use App\Platform\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

        /*
        | ⚠️ `whereNotNull('verified_at')` ŞART (3H). Olmadan doğrulanmamış
        | her alan adı 200 alırdı: marka paneline `google.com` yazan biri
        | yüzünden Caddy o alan adı için sertifika istemeye çalışır, ACME
        | doğrulaması düşer ve Let's Encrypt kotamız yanardı — haftada 50
        | sertifikayla sınırlıyız (3-K5).
        |
        | ⚠️ Bu uç TLS EL SIKIŞMASININ KRİTİK YOLUNDA: yalnızca veritabanına
        | bakıyor, DNS sorgusu YAPMIYOR. Yapsaydı her yeni bağlantı ağ turu
        | kadar beklerdi.
        */
        /*
        | ★ MARKANIN DURUMU DA BAKILIYOR (4.5N).
        |
        | ⚠️ Yalnızca `verified_at`'e bakılsaydı ONAY BEKLEYEN ya da
        | REDDEDİLMİŞ bir başvurunun alan adı da sertifika alırdı. Kota
        | haftada 50 (3-K5); internetten kaydolan herkesin sertifika
        | yakabilmesi, `ask` ucunu koymamızın gerekçesini boşa çıkarırdı.
        |
        | ⚠️ `Closed` de dışarıda: kapatılmış markanın vitrini artık
        | yayında değil, sertifikasını yenilemenin anlamı yok.
        */
        $yayindakiDurumlar = [
            TenantStatus::Trial->value,
            TenantStatus::Active->value,
            TenantStatus::PastDue->value,
            TenantStatus::Suspended->value,
        ];

        $kayitli = $domain !== '' && Domain::where('domain', $domain)
            ->whereNotNull('verified_at')
            /*
            | ⚠️ `whereHas('tenant', ...)` DEĞİL: ilişki paketin taban
            | modelinden geliyor ve dönüş tipi yazılmadığı için Larastan
            | onu göremiyor. Alt sorgu hem analizden geçiyor hem de tek
            | sorgu kalıyor.
            */
            ->whereIn('tenant_id', Tenant::query()->whereIn('status', $yayindakiDurumlar)->select('id'))
            ->exists();

        if (! $kayitli) {
            return response('', 404);
        }

        return response('', 200);
    }
}
