<?php

namespace App\Http\Panel;

use App\Http\Controllers\Controller;
use App\Platform\Domains\CustomDomainService;
use App\Platform\DomainUnavailableException;
use App\Platform\Models\Domain;
use App\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Markanın kendi alan adını bağladığı ekran. (4.5C)
 *
 * ★ 3H'nin karşılığı: uçlar oradaydı, ekranı yoktu — yani marka DNS
 * talimatını hiç göremiyordu.
 *
 * ⚠️ 3. ADIM İNSAN İŞİ (3H): kaydı markanın kendisi, kendi DNS panelinde
 * ekliyor. Destek yükünün tamamı orada; bu yüzden talimat ekranda AÇIKÇA
 * ve üç seçenekle (CNAME · A · TXT) duruyor.
 */
class DomainPageController extends Controller
{
    public function __construct(private readonly CustomDomainService $alanAdlari) {}

    public function index(): Response
    {
        $kayitlar = $this->markaninAlanAdlari();

        return Inertia::render('AlanAdlari', [
            'alanAdlari' => $kayitlar->map(fn (Domain $d) => [
                'domain' => $d->domain,
                'dogrulandi' => $d->verified_at !== null,
                'dogrulama_tarihi' => $d->verified_at?->format('d.m.Y H:i'),

                /*
                | ⚠️ Talimat HER KAYIT için veriliyor, yalnızca yeni
                | eklenene değil: marka sayfayı kapatıp döndüğünde
                | doğrulanmamış kaydının ne yapması gerektiğini yeniden
                | görebilmeli.
                */
                'talimat' => $d->verified_at === null ? $this->alanAdlari->talimat($d) : null,
            ])->values()->all(),

            // ⚠️ Son alan adı silinemiyor; arayüz bunu sebebiyle göstersin.
            'sonAlanAdi' => $kayitlar->count() <= 1,
        ]);
    }

    public function ekle(Request $istek): RedirectResponse
    {
        $veri = $istek->validate(['domain' => ['required', 'string', 'max:253']]);

        try {
            $this->alanAdlari->ekle($this->marka(), (string) $veri['domain']);
        } catch (DomainUnavailableException $hata) {
            /*
            | ⚠️ Merkez alan adı, kayıtlı alan adı, ayrılmış alt ad —
            | üçü de 500 DEĞİL, ekranda sebep. Marka bir şeyi yanlış
            | denedi, bu sıradan bir sonuç.
            */
            return back()->with('hata', $hata->getMessage());
        }

        return back()->with('mesaj', 'Alan adı eklendi. DNS kaydını ekleyip "kontrol et" deyin.');
    }

    public function dogrula(string $alanAdi): RedirectResponse
    {
        $kayit = $this->kaydiBul($alanAdi);

        /*
        | ⚠️ Sonuç AÇIKÇA bildiriliyor — başarısızlıkta da. Sessizce
        | sayfayı yenilemek "ekledim ama olmuyor" çağrısı demek (3H).
        */
        if (! $this->alanAdlari->dogrula($kayit)) {
            return back()->with('hata', 'DNS kaydı henüz görünmüyor. Yayılması birkaç saat sürebilir.');
        }

        return back()->with('mesaj', 'Alan adı doğrulandı. Sertifika ilk ziyarette otomatik alınacak.');
    }

    public function sil(string $alanAdi): RedirectResponse
    {
        $kayit = $this->kaydiBul($alanAdi);

        /*
        | ⚠️ SON ALAN ADI SİLİNEMEZ: silinseydi markaya hiçbir adresten
        | ulaşılamaz, paneline de giremezdi (3H).
        */
        if ($this->markaninAlanAdlari()->count() <= 1) {
            return back()->with('hata', 'Son alan adı silinemez — markanız erişilemez hâle gelirdi.');
        }

        $kayit->delete();

        return back()->with('mesaj', 'Alan adı silindi.');
    }

    /**
     * ⚠️ Kayıt MARKAYA DARALTILMIŞ sorgudan çözülüyor (1A.5 deseni):
     * başka markanın alan adı sonuç kümesine hiç girmiyor → 404.
     */
    private function kaydiBul(string $alanAdi): Domain
    {
        /** @var Domain $kayit */
        $kayit = Domain::where('tenant_id', $this->marka()->id)
            ->where('domain', strtolower(trim($alanAdi)))
            ->firstOrFail();

        return $kayit;
    }

    /** @return Collection<int, Domain> */
    private function markaninAlanAdlari(): Collection
    {
        /** @var Collection<int, Domain> $kayitlar */
        $kayitlar = Domain::where('tenant_id', $this->marka()->id)->orderBy('id')->get();

        return $kayitlar;
    }

    private function marka(): Tenant
    {
        $marka = tenant();

        assert($marka instanceof Tenant);

        return $marka;
    }
}
