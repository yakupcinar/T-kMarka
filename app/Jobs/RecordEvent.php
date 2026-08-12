<?php

namespace App\Jobs;

use App\Enums\EventType;
use App\Models\Customer;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Olayı marka şemasına yazan kuyruk işi. (1F)
 *
 * ★ NEDEN KUYRUK: olay kaydı müşteriyi bekletmemeli. Sepete ekleme
 * isteği bir yazma işlemi daha beklerse vitrin yavaşlar; oysa olayın
 * bir saniye geç yazılmasının kimseye zararı yok.
 *
 * ⚠️ ★ KİRACILIK TUZAĞI (M-2.4 / 1) — beşinin en sinsisi.
 *
 * Bu iş, onu yaratan istekten dakikalar sonra, BAŞKA bir konteynerde
 * çalışıyor. O konteynerin hangi markada olduğuna dair hiçbir bilgisi
 * yok. Kimlik taşınmazsa iş merkez bağlamda koşar ya da bir önceki
 * markanın şemasına yazar — ve HATA VERMEZ.
 *
 * Kimlik işin GÖVDESİNDE taşınıyor: `QueueTenancyBootstrapper`
 * (config/tenancy.php) atarken `tenant_id` ekliyor, alırken bağlamı
 * kuruyor. 0.5'te ölçüldü, `tests/Tenancy/KuyrukTest` koruyor.
 *
 * ⚠️ Kod değişince `docker compose restart worker` — işçi kodu belleğe
 * alıyor (CLAUDE.md).
 */
class RecordEvent implements ShouldQueue
{
    use Queueable;

    /**
     * ⚠️ Denenme sayısı SINIRLI. Sonsuz olsaydı, veritabanı bir süre
     * erişilemez olduğunda kuyruk olay işleriyle dolar ve asıl işler
     * (ödeme bildirimi gibi) arkada sıraya girerdi.
     */
    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly EventType $tip,
        private readonly array $payload,
        private readonly ?int $musteriId,
        private readonly CarbonInterface $olusmaAni,
    ) {}

    public function handle(): void
    {
        $olay = new Event;
        $olay->type = $this->tip;
        $olay->payload = $this->payload;
        /*
        | ⚠️ `customer_id` ilişkiden atanıyor, kolona doğrudan değil:
        | Larastan kolonu işaretsiz tam sayı görüyor ve düz atama tip
        | uyuşmazlığı veriyor. İlişki üzerinden atamak hem analizi
        | memnun ediyor hem de niyeti açık yazıyor.
        */
        if ($this->musteriId !== null) {
            $olay->customer()->associate(Customer::find($this->musteriId));
        }

        /*
        | ⚠️ OLAYIN OLDUĞU AN, yazıldığı an değil. Kuyruk gecikirse ikisi
        | dakikalarca ayrışıyor; `created_at` kullanılsaydı rapor yanlış
        | saati gösterirdi.
        */
        $olay->occurred_at = $this->olusmaAni;

        $olay->save();
    }
}
