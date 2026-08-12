<?php

namespace App\Domain\Payment;

use App\Domain\Order\CheckoutService;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Ödeme başlatma. (PLAN 1E.3)
 *
 * ★ Bu sınıfın işi para tahsil etmek DEĞİL — ödemeyi başlatıp müşteriyi
 * sağlayıcıya yollamak. Sonuç dakikalar sonra webhook'la gelecek (1E.4).
 *
 * Üç şeyi garanti ediyor:
 *   1  tutar SUNUCUDA üretiliyor — istemciden gelen hiçbir tutara bakılmıyor
 *   2  aynı sipariş için ikinci çekim AÇILMIYOR (idempotanslık)
 *   3  yönlendirmeden önce rezervasyon ömrü 60 dakikaya çıkıyor (1E.2)
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentProviderFactory $saglayicilar,
        private readonly CheckoutService $siparisler,
        private readonly PaymentReadiness $hazirlik,
    ) {}

    /**
     * Ödemeyi başlatır, müşterinin yönlendirileceği adresi döndürür.
     *
     * ⚠️ `$donusAdresi` İSTEMCİDEN ALINMIYOR — controller kendi alan
     * adından üretiyor. İstekten alınsaydı saldırgan kendi sitesini
     * yazar, müşteri ödeme sonrası oraya düşer ve sahte bir "başarılı"
     * ekranı görürdü (açık yönlendirme).
     *
     * @throws OrderNotPayableException
     * @throws PaymentNotConfiguredException
     */
    public function baslat(Order $siparis, string $donusAdresi): PaymentInitiation
    {
        /*
        | ⚠️ Ödenmiş siparişe ikinci ödeme AÇILMIYOR.
        |
        | Bu kontrol olmasaydı, "teşekkürler" sayfasını yenileyen müşteri
        | ikinci kez ödeme başlatabilirdi — ve idempotanslık anahtarı
        | aynı olsa bile sipariş zaten kapanmış olurdu.
        */
        if ($siparis->payment_status !== PaymentStatus::Pending) {
            throw new OrderNotPayableException($siparis->order_number, $siparis->payment_status);
        }

        $saglayici = $this->saglayicilar->coz();

        /*
        | ★ EKSİK YAPILANDIRMAYLA ÖDEME BAŞLATILMIYOR (1E-K11).
        |
        | ⚠️ Kontrol EN BAŞTA: deneme satırı açıldıktan sonra patlasaydı
        | müşteri hata görür ama arkada yarım bir ödeme kaydı kalırdı ve
        | o anahtar `UNIQUE (order_id, idempotency_key)` yüzünden ikinci
        | denemeyi de engellerdi.
        */
        $eksikler = $this->hazirlik->eksikler();

        if ($eksikler !== []) {
            throw new PaymentNotConfiguredException($saglayici->ad(), $eksikler);
        }

        /*
        | ★ İDEMPOTANSLIK ANAHTARI = SİPARİŞ NUMARASI (1E-K4).
        |
        | Marka içinde tekil ve zaten üretilmiş. Rastgele üretilseydi her
        | tıklama yeni bir anahtar doğurur ve idempotanslık hiçbir şey
        | ifade etmezdi.
        */
        $anahtar = $siparis->order_number;

        $mevcut = $this->mevcutDeneme($siparis, $saglayici->ad(), $anahtar);

        /*
        | Aynı deneme zaten sağlayıcıya iletilmiş: YENİDEN İLETİLMİYOR,
        | saklanan yönlendirme adresi geri veriliyor.
        |
        | ⚠️ Sağlayıcıya tekrar gidilseydi (anahtar aynı olsa bile) sahte
        | sağlayıcı yeni bir referans üretirdi ve elimizde aynı sipariş
        | için iki referans olurdu — hangisinin webhook'u geleceği belirsiz.
        */
        if ($mevcut !== null && $mevcut->provider_ref !== null) {
            return $this->saklanandanUret($mevcut);
        }

        $deneme = $mevcut ?? $this->denemeAc($siparis, $saglayici->ad(), $anahtar);

        /*
        | ⚠️ `decimal:2` cast'i METİN döndürüyor ama statik analiz onun
        | sayısal olduğunu bilmiyor. Bozuk veriyle sağlayıcıya gitmektense
        | burada patlamak iyi: tutarı olmayan bir ödeme başlatılamaz.
        */
        $tutar = $siparis->grand_total;

        if (! is_numeric($tutar)) {
            throw new OrderNotPayableException($siparis->order_number, $siparis->payment_status);
        }

        $sonuc = $saglayici->baslat(new PaymentRequest(
            siparisNumarasi: $siparis->order_number,
            // ⚠️ TUTAR BURADAN: orders.grand_total. İstemci hiç karışmıyor.
            tutar: $tutar,
            eposta: $siparis->email,
            idempotanslikAnahtari: $anahtar,
            donusAdresi: $donusAdresi,
        ));

        $deneme->provider_ref = $sonuc->saglayiciReferansi;
        $deneme->raw_response = ['redirect_url' => $sonuc->yonlendirmeAdresi];
        $deneme->save();

        /*
        | ★ SON ADIM: rezervasyon ömrü 15 dk → 60 dk (1E.2).
        |
        | ⚠️ Yönlendirmeden SONRA yapılamaz — müşteri o an bizden çıkmış
        | oluyor ve geri döneceğinin garantisi yok.
        */
        $this->siparisler->odemeBaslatildi($siparis);

        return $sonuc;
    }

    private function saklanandan(Payment $deneme): string
    {
        $ham = $deneme->raw_response ?? [];
        $adres = $ham['redirect_url'] ?? null;

        return is_string($adres) ? $adres : '';
    }

    private function saklanandanUret(Payment $deneme): PaymentInitiation
    {
        return new PaymentInitiation(
            yonlendirmeAdresi: $this->saklanandan($deneme),
            saglayiciReferansi: (string) $deneme->provider_ref,
        );
    }

    private function mevcutDeneme(Order $siparis, string $saglayici, string $anahtar): ?Payment
    {
        /*
        | ⚠️ Sorgu SİPARİŞE DARALTILMIŞ (1A.5 deseni): başka siparişin
        | denemesi sonuç kümesine hiç girmiyor.
        */
        return Payment::where('order_id', $siparis->id)
            ->where('provider', $saglayici)
            ->where('idempotency_key', $anahtar)
            ->first();
    }

    /**
     * Deneme satırını açar.
     *
     * ⚠️ Satır sağlayıcıya İSTEK GİTMEDEN önce açılıyor. Sonra açılsaydı
     * ve cevap dönerken bağlantı koparsa, para çekilmiş ama bizde hiçbir
     * iz kalmamış olurdu.
     */
    private function denemeAc(Order $siparis, string $saglayici, string $anahtar): Payment
    {
        try {
            return DB::transaction(function () use ($siparis, $saglayici, $anahtar) {
                $deneme = new Payment;
                $deneme->order()->associate($siparis);
                $deneme->provider = $saglayici;
                $deneme->idempotency_key = $anahtar;
                $deneme->amount = $siparis->grand_total;
                $deneme->status = PaymentAttemptStatus::Pending;
                $deneme->save();

                return $deneme;
            });
        } catch (QueryException $e) {
            /*
            | ★ ÇİFT TIKLAMA — iki istek AYNI ANDA buraya geldi.
            |
            | İkisi de `mevcutDeneme()`'de boş gördü, ikisi de satır açmaya
            | çalıştı. UNIQUE (order_id, idempotency_key) ikincisini
            | reddediyor; burada birincinin satırını okuyup devam ediyoruz.
            |
            | ⚠️ Kontrolü uygulamada yapıp kısıtı koymasaydık iki deneme
            | açılır, sağlayıcıya iki istek giderdi ve MÜŞTERİDEN İKİ KEZ
            | PARA ÇEKİLİRDİ.
            */
            $mevcut = $this->mevcutDeneme($siparis, $saglayici, $anahtar);

            if ($mevcut === null) {
                throw $e;   // başka bir veritabanı hatası — yutmuyoruz
            }

            return $mevcut;
        }
    }
}
