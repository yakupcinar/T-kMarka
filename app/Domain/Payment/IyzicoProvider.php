<?php

namespace App\Domain\Payment;

use App\Domain\Settings\SettingsService;
use App\Enums\SettingGroup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * iyzico — barındırılan ödeme formu (Checkout Form). (1E-K7…K9)
 *
 * ★ KART VERİSİ BİZE HİÇ DEĞMİYOR. Formu iyzico çiziyor, müşteri kart
 * bilgisini onların sayfasına giriyor. Bedeli: ödeme ekranının görünümü
 * bizde değil. Karşılığı: PCI kapsamı en dar hâlinde kalıyor.
 *
 * AKIŞ:
 *
 *   baslat()          → CF başlat, `token` + `paymentPageUrl` al
 *   müşteri            → iyzico'nun sayfasında kartını girer, 3DS'ten geçer
 *   webhook            → imzalı bildirim: token + status
 *   sorgula()         → CF sorgula, GERÇEK durum + tutar (1E-K12)
 *
 * ⚠️ `provider_ref` = iyzico'nun **token**'ı. Bildirimde geri gelen ve
 * sorgulamada kullanılan kimlik o. `paymentConversationId` ise bizim
 * ödeme denemesinin uuid'si (1E-K8) — çapraz kontrol için.
 */
class IyzicoProvider implements QueryablePaymentProvider, RefundablePaymentProvider
{
    public const API_ANAHTARI = 'iyzico_api_key';

    public const GIZLI_ANAHTAR = 'iyzico_secret_key';

    private const BASLAT_YOLU = '/payment/iyzipos/checkoutform/initialize/auth/ecom';

    private const SORGU_YOLU = '/payment/iyzipos/checkoutform/auth/ecom/detail';

    private const IADE_YOLU = '/payment/refund';

    public function __construct(private readonly SettingsService $ayarlar) {}

    public function ad(): string
    {
        return 'iyzico';
    }

    /** @return list<string> */
    public function gerekliAnahtarlar(): array
    {
        return [self::API_ANAHTARI, self::GIZLI_ANAHTAR];
    }

    /**
     * ⚠️ İKİ AD. Belgede yalnızca V3 yazıyor ama sandbox ÖLÇÜMÜ eski adı
     * gönderdiğini gösterdi (`X-Iyz-Signature`). Tek ada bağlansaydık
     * gelen bildirimi hiç doğrulayamaz, hepsini reddederdik.
     *
     * @return list<string>
     */
    public function imzaBasliklari(): array
    {
        return ['X-IYZ-SIGNATURE-V3', 'X-Iyz-Signature'];
    }

    /**
     * ★ iyzico callback'e `token`'ı POST GÖVDESİNDE yolluyor.
     *
     * ⚠️ Ölçüldü (1E.7.3): `POST /odeme/donus` gövdesi
     * `token=4e27e867-…`. Dönüş ucu yalnızca `?ref=` okuduğu için üç
     * deneme de 404 aldı ve müşteri ödemeyi bitirdikten sonra "sayfa
     * bulunamadı" gördü.
     *
     * @param  array<string, mixed>  $veri
     */
    public function donusReferansi(array $veri): ?string
    {
        $jeton = $veri['token'] ?? null;

        return is_string($jeton) && $jeton !== '' ? $jeton : null;
    }

    public function baslat(PaymentRequest $istek): PaymentInitiation
    {
        $govde = [
            'locale' => 'tr',

            // ★ 1E-K8: bildirimde bu geri gelecek.
            'conversationId' => $istek->denemeUuid,

            /*
            | ⚠️ `price` ile `paidPrice` AYNI: taksit farkı yok (Faz 1'de
            | taksit kapalı). Farklı olsalardı iyzico aradaki farkı vade
            | farkı sayardı ve müşteriden fazla tahsil edilirdi.
            */
            'price' => $istek->tutar,
            'paidPrice' => $istek->tutar,
            'currency' => 'TRY',
            'basketId' => $istek->siparisNumarasi,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => $istek->donusAdresi,

            // ⚠️ Taksit YOK (Faz 1). Açılsaydı `paidPrice` değişirdi ve
            // `orders.grand_total` ile ödenen tutar birbirini tutmazdı.
            'enabledInstallments' => [1],

            'buyer' => $this->alici($istek),
            'shippingAddress' => $this->adres($istek),
            'billingAddress' => $this->adres($istek),
            'basketItems' => $this->sepet($istek),
        ];

        $cevap = $this->cagir(self::BASLAT_YOLU, $govde);

        $jeton = $cevap['token'] ?? null;
        $adres = $cevap['paymentPageUrl'] ?? null;

        /*
        | ⚠️ Eksik cevapta GÜRÜLTÜLÜ hata. Boş bir adresle devam edilseydi
        | müşteri hiçbir yere yönlendirilemez, ödeme denemesi `pending`
        | kalır ve kimse sebebini bilemezdi.
        */
        if (! is_string($jeton) || ! is_string($adres) || $jeton === '' || $adres === '') {
            throw new PaymentProviderException($this->ad(), 'Başlatma cevabı eksik döndü.', $cevap);
        }

        return new PaymentInitiation(yonlendirmeAdresi: $adres, saglayiciReferansi: $jeton);
    }

    /**
     * ★ 1E-K9 + 1E-K12 — GERÇEK SAĞLAYICIDAN SORULUYOR.
     *
     * İki sebep birden:
     *   tutar   iyzico'nun bildiriminde HİÇ YOK
     *   durum   bildirim imzasız geliyor, gövdesine güvenilmiyor
     *
     * ⚠️ Başarı ölçütü `paymentStatus`, bildirimdeki `status` DEĞİL.
     * Ölçüldü: başarısız bir ödemede bile `paidPrice` doğru dönüyor —
     * yani tutara bakıp "ödendi" demek yanlış olurdu.
     */
    public function sorgula(string $referans): PaymentOutcome
    {
        /*
        | ★ "ÇAĞRI BAŞARISIZ" ile "ÖDEME BAŞARISIZ" AYRI ŞEYLER.
        |
        | ⚠️ Gerçek sandbox'ta ölçüldü: yetersiz bakiyeli bir ödemede
        | iyzico servis düzeyinde de `status: failure` döndürüyor —
        | `errorCode: 10051`, `paidPrice` YOK, ama `paymentStatus: FAILURE`
        | VAR. Yani cevap geçerli, ödeme başarısız.
        |
        | İkisi ayrılmasaydı (ve ayrılmıyordu) başarısız ödemenin webhook'u
        | 502 alırdı: sipariş `pending` kalır, bağlı stok 60 dakika boyunca
        | kimseye satılamaz ve müşteri neden reddedildiğini öğrenemezdi.
        | Ölçülen buydu.
        |
        | Ayırt edici işaret: cevapta `paymentStatus` VARSA bu bir ÖDEME
        | cevabıdır — çağrı başarılı olmuş demektir.
        */
        $cevap = $this->cagir(self::SORGU_YOLU, ['locale' => 'tr', 'token' => $referans], odemeCevabi: true);

        $durum = $this->metin($cevap, 'paymentStatus');

        /*
        | ⚠️ Başarısız ödemede `paidPrice` YOK — olmaması normal, para
        | çekilmedi. Zorunlu tutulsaydı yine 502'ye düşerdik.
        */
        $tutar = $cevap['paidPrice'] ?? null;
        $tutar = is_numeric($tutar) ? (string) $tutar : '0';

        if ($durum === 'SUCCESS' && $tutar === '0') {
            throw new PaymentProviderException($this->ad(), 'Başarılı ödemede tutar yok.', $cevap);
        }

        return new PaymentOutcome(
            siparisNumarasi: $this->metin($cevap, 'conversationId'),
            saglayiciReferansi: $referans,
            basarili: $durum === 'SUCCESS',
            tutar: $tutar,
            hamCevap: $this->maskele($cevap),

            /*
            | Marka "neden alınamadı" sorusunun cevabını burada buluyor:
            | `NOT_SUFFICIENT_FUNDS` gibi bir grup ya da hata kodu.
            */
            hataKodu: $durum === 'SUCCESS'
                ? null
                : ($this->metin($cevap, 'errorGroup') ?: ($this->metin($cevap, 'errorCode') ?: 'unknown')),
        );
    }

    /**
     * ★ 2B-K7 — para GERİ gönderiliyor.
     *
     * ⚠️ ★ GERÇEK SANDBOX'TA ÖLÇÜLDÜ: iyzico ödemeyi sepet satırlarına
     * bölüyor ("kırılım") ve **iade her kırılım için AYRI** yapılıyor.
     *
     * ```
     * ödeme 299,80  →  kırılım 0: ürün   249,90   (paymentTransactionId A)
     *                  kırılım 1: kargo   49,90   (paymentTransactionId B)
     * ```
     *
     * İlk yazımda tek kırılıma tüm tutar gönderildi ve gerçek sandbox
     * reddetti:
     *   `5093 — verilen iade tutarı … kırılımın tutarından büyük olamaz`
     *
     * Taklit bunu uyduramazdı; 1E.7.3'ün dersinin tekrarı.
     *
     * ⚠️ `conversationId` kırılım numarasıyla tekilleştiriliyor: aynı
     * anahtarla iki kırılıma istek gidiyor ve sağlayıcı bunları ayırabilmeli.
     */
    public function iadeEt(string $referans, string $tutar, string $idempotanslikAnahtari): PaymentOutcome
    {
        $ayrinti = $this->cagir(self::SORGU_YOLU, ['locale' => 'tr', 'token' => $referans], odemeCevabi: true);

        $kirilimlar = $this->kirilimlar($ayrinti);

        if ($kirilimlar === []) {
            throw new PaymentProviderException($this->ad(), 'İade için ödeme kırılımı bulunamadı.', $this->maskele($ayrinti));
        }

        $kalan = $this->sayisal($tutar);
        $referanslar = [];
        $cevaplar = [];

        foreach ($kirilimlar as $kirilim) {
            if (bccomp($kalan, '0', 2) <= 0) {
                break;
            }

            /*
            | ⚠️ Her kırılıma EN FAZLA kendi kalanı kadar gönderiliyor.
            | Fazlası 5093 ile reddediliyor — ölçüldü.
            */
            $pay = bccomp($kirilim['kalan'], $kalan, 2) >= 0 ? $kalan : $kirilim['kalan'];

            if (bccomp($pay, '0', 2) <= 0) {
                continue;
            }

            $cevap = $this->cagir(self::IADE_YOLU, [
                'locale' => 'tr',
                'conversationId' => $idempotanslikAnahtari.'-'.$kirilim['id'],
                'paymentTransactionId' => $kirilim['id'],
                'price' => $pay,
                'currency' => 'TRY',
            ]);

            $referanslar[] = $this->metin($cevap, 'paymentId') ?: $kirilim['id'];
            $cevaplar[] = $this->maskele($cevap);

            /*
            | ★ SAĞLAYICININ İADE ETTİĞİ TUTAR, İSTEDİĞİMİZLE AYNI MI?
            |
            | ⚠️ Gerçek sandbox koşusunda ölçüldü: bir çağrıda 249,90
            | istendi, cevapta `price: 200` döndü ve sebebi cevaptan
            | anlaşılamadı. Kontrol edilmeseydi kayıtta 299,80 iade
            | yazarken müşteriye 249,90 gitmiş olurdu — ve bu hiçbir yerde
            | görünmezdi.
            |
            | `status: success` YETMİYOR: tutarın kendisi de doğrulanmalı.
            */
            $gerceklesen = is_numeric($cevap['price'] ?? null) ? (string) $cevap['price'] : '0';

            if (bccomp($gerceklesen, $pay, 2) !== 0) {
                throw new PaymentProviderException(
                    $this->ad(),
                    'Sağlayıcı istenen tutardan farklı iade etti.',
                    ['istenen' => $pay, 'gerceklesen' => $gerceklesen, 'kirilim' => $kirilim['id']],
                );
            }

            $kalan = bcsub($kalan, $pay, 2);
        }

        /*
        | ⚠️ Tutarın tamamı karşılanamadıysa GÜRÜLTÜLÜ hata. Sessiz
        | kalsaydı müşteriye eksik para gider ve kayıt "tamamlandı"
        | görünürdü.
        */
        if (bccomp($kalan, '0', 2) > 0) {
            throw new PaymentProviderException(
                $this->ad(),
                'İade tutarı kırılımlarla karşılanamadı.',
                ['kalan' => $kalan, 'kirilim' => count($kirilimlar)],
            );
        }

        return new PaymentOutcome(
            siparisNumarasi: $idempotanslikAnahtari,
            saglayiciReferansi: implode(',', $referanslar),
            basarili: true,
            tutar: $this->sayisal($tutar),
            hamCevap: ['refunds' => $cevaplar],
        );
    }

    /**
     * Ödeme kırılımları: hangi işlem numarası, ne kadar iade edilebilir.
     *
     * ⚠️ `refundedPrice` daha önce iade edilen tutar — kısmi iadeden
     * sonra kalan buradan çıkıyor. Hesaba katılmasaydı ikinci iade yine
     * 5093 alırdı.
     *
     * @param  array<string, mixed>  $cevap
     * @return list<array{id: string, kalan: numeric-string}>
     */
    private function kirilimlar(array $cevap): array
    {
        $satirlar = $cevap['itemTransactions'] ?? null;

        if (! is_array($satirlar)) {
            return [];
        }

        $sonuc = [];

        foreach ($satirlar as $satir) {
            if (! is_array($satir)) {
                continue;
            }

            $no = $satir['paymentTransactionId'] ?? null;
            $odenen = $satir['paidPrice'] ?? null;

            if (! is_scalar($no) || ! is_numeric($odenen)) {
                continue;
            }

            $iade = is_numeric($satir['refundedPrice'] ?? null) ? (string) $satir['refundedPrice'] : '0';

            $sonuc[] = [
                'id' => (string) $no,
                'kalan' => $this->sayisal(bcsub((string) $odenen, $iade, 2)),
            ];
        }

        return $sonuc;
    }

    /** @return numeric-string */
    private function sayisal(mixed $deger): string
    {
        return is_numeric($deger) ? (string) $deger : '0';
    }

    public function webhookuDogrula(array $yuk, ?string $imza): bool
    {
        if ($imza === null) {
            return false;
        }

        /*
        | ★ İMZA DÜZENİ iyzico'nun belgesinden: gizli anahtar + beş alan
        | BELİRLİ SIRAYLA birleştirilip HMAC-SHA256, onaltılık gösterim.
        |
        | ⚠️ Sıra sabit ve alanlar tek tek sayılı. Yükün tamamı
        | birleştirilseydi iyzico bir gün yeni bir alan eklediğinde imza
        | tutmaz, hiçbir ödeme işlenmez olurdu.
        */
        $metin = $this->gizliAnahtar()
            .$this->metin($yuk, 'iyziEventType')
            .$this->metin($yuk, 'iyziPaymentId')
            .$this->metin($yuk, 'token')
            .$this->metin($yuk, 'paymentConversationId')
            .$this->metin($yuk, 'status');

        /*
        | ⚠️ `hash_equals` — düz `===` DEĞİL. Düz karşılaştırma ilk farklı
        | karakterde duruyor; saldırgan cevap süresini ölçerek imzayı
        | karakter karakter bulabilir (zamanlama saldırısı).
        */
        return hash_equals(hash_hmac('sha256', $metin, $this->gizliAnahtar()), $imza);
    }

    public function webhookuCoz(array $yuk): PaymentOutcome
    {
        $durum = $this->metin($yuk, 'status');

        /*
        | ⚠️ SADECE `SUCCESS` başarı sayılıyor.
        |
        | iyzico ara durumlar da gönderiyor (`INIT_THREEDS`,
        | `CALLBACK_THREEDS`). "FAILURE değilse başarılıdır" denseydi
        | müşteri daha kart bilgisini girerken sipariş ödenmiş sayılırdı.
        */
        $basarili = $durum === 'SUCCESS';

        return new PaymentOutcome(
            /*
            | ⚠️ Sipariş numarası bildirimde YOK — eşleşme `token` üzerinden
            | yapılıyor (`payments.provider_ref`). Buradaki alan bizim
            | deneme uuid'miz; çapraz kontrol için taşınıyor.
            */
            siparisNumarasi: $this->metin($yuk, 'paymentConversationId'),
            saglayiciReferansi: $this->metin($yuk, 'token'),
            basarili: $basarili,

            // ⚠️ Bildirimde tutar YOK — `sorgula()` soracak (1E-K9).
            tutar: '0',

            hamCevap: $this->maskele($yuk),
            hataKodu: $basarili ? null : ($durum === '' ? 'unknown' : $durum),
        );
    }

    /**
     * iyzico'ya imzalı istek.
     *
     * ⚠️ Kimlik doğrulama düzeni (IYZWSv2):
     *   imza  = HMAC-SHA256(rastgele + yol + gövde, gizli anahtar)
     *   başlık = base64("apiKey:…&randomKey:…&signature:…")
     *
     * `x-iyzi-rnd` başlığı AYNI rastgele değeri taşımak zorunda; farklı
     * olursa iyzico imzayı yeniden üretemez ve istek reddedilir.
     *
     * @param  array<string, mixed>  $govde
     * @return array<string, mixed>
     */
    private function cagir(string $yol, array $govde, bool $odemeCevabi = false): array
    {
        $rastgele = (string) now()->getTimestampMs().Str::random(10);
        $json = (string) json_encode($govde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $imza = hash_hmac('sha256', $rastgele.$yol.$json, $this->gizliAnahtar());

        $yetki = base64_encode(
            'apiKey:'.$this->apiAnahtari().'&randomKey:'.$rastgele.'&signature:'.$imza
        );

        $cevap = Http::withHeaders([
            'Authorization' => 'IYZWSv2 '.$yetki,
            'x-iyzi-rnd' => $rastgele,
            'Content-Type' => 'application/json',
        ])
            ->withBody($json, 'application/json')
            ->timeout(20)
            ->post($this->sunucu().$yol);

        /** @var array<string, mixed> $govdeCevabi */
        $govdeCevabi = $cevap->json() ?? [];

        /*
        | ⚠️ HTTP 200 ama `status: failure` olabiliyor — iyzico iş
        | hatalarını da 200 ile döndürüyor. Yalnızca HTTP koduna
        | bakılsaydı başarısız çağrı başarılı sanılırdı.
        */
        /*
        | ⚠️ `paymentStatus` VARSA bu bir ÖDEME cevabıdır: çağrı başarılı
        | olmuş, ödeme başarısız olmuş. İstisna fırlatmıyoruz — başarısız
        | ödeme de işlenmesi gereken bir sonuç.
        */
        if ($odemeCevabi && ! $cevap->failed() && array_key_exists('paymentStatus', $govdeCevabi)) {
            return $govdeCevabi;
        }

        if ($cevap->failed() || ($govdeCevabi['status'] ?? null) !== 'success') {
            throw new PaymentProviderException(
                $this->ad(),
                is_string($govdeCevabi['errorMessage'] ?? null)
                    ? $govdeCevabi['errorMessage']
                    : 'iyzico çağrısı başarısız.',
                $this->maskele($govdeCevabi),
            );
        }

        return $govdeCevabi;
    }

    /** @return array<string, mixed> */
    private function alici(PaymentRequest $istek): array
    {
        return [
            // ⚠️ Alıcı kimliği olarak SİPARİŞ e-postası: müşteri hesabı
            // olmayabilir (misafir alışverişi, M-1).
            'id' => $istek->eposta,
            'name' => $this->adAyir($istek->aliciAdi)[0],
            'surname' => $this->adAyir($istek->aliciAdi)[1],
            'email' => $istek->eposta,
            'gsmNumber' => $istek->aliciTelefon,
            'identityNumber' => '11111111111',
            'registrationAddress' => $istek->aliciAdres,
            'city' => $istek->aliciSehir,
            'country' => 'Turkey',
            'ip' => '127.0.0.1',
        ];
    }

    /** @return array<string, mixed> */
    private function adres(PaymentRequest $istek): array
    {
        return [
            'contactName' => $istek->aliciAdi,
            'city' => $istek->aliciSehir,
            'country' => 'Turkey',
            'address' => $istek->aliciAdres,
        ];
    }

    /**
     * ⚠️ Satır tutarlarının TOPLAMI `price` ile birebir tutmak zorunda;
     * tutmazsa iyzico isteği reddediyor. Kargo bedeli de bir satır olarak
     * gönderiliyor — aksi hâlde toplam eksik kalırdı.
     *
     * @return list<array<string, mixed>>
     */
    private function sepet(PaymentRequest $istek): array
    {
        $sepet = [];

        foreach ($istek->satirlar as $sira => $satir) {
            $sepet[] = [
                'id' => (string) ($sira + 1),
                'name' => $satir['ad'],
                'category1' => 'Genel',
                'itemType' => 'PHYSICAL',
                'price' => $satir['tutar'],
            ];
        }

        return $sepet;
    }

    /** @return array{0: string, 1: string} */
    private function adAyir(string $tamAd): array
    {
        $parcalar = preg_split('/\s+/', trim($tamAd)) ?: [];

        if (count($parcalar) < 2) {
            return [$tamAd === '' ? '-' : $tamAd, '-'];
        }

        /** @var string $soyad */
        $soyad = array_pop($parcalar);

        return [implode(' ', $parcalar), $soyad];
    }

    /**
     * Denetim izine giren yükü süzer.
     *
     * ⚠️ Kart verisi zaten bize gelmiyor (1E-K7) ama sağlayıcı cevabında
     * maskeli kart numarası gibi alanlar bulunabiliyor. Ham cevabı olduğu
     * gibi saklamak, bir gün sağlayıcı yeni bir alan eklediğinde onu da
     * körü körüne kaydetmek demek.
     *
     * @param  array<string, mixed>  $yuk
     * @return array<string, mixed>
     */
    private function maskele(array $yuk): array
    {
        $izinli = [
            'status', 'paymentStatus', 'iyziEventType', 'iyziPaymentId', 'iyziReferenceCode',
            'iyziEventTime', 'token', 'paymentConversationId', 'conversationId',
            'paidPrice', 'price', 'currency', 'errorCode', 'errorMessage', 'errorGroup',
            'paymentTransactionId', 'refundedPrice',
        ];

        return array_intersect_key($yuk, array_flip($izinli));
    }

    /** @param  array<string, mixed>  $yuk */
    private function metin(array $yuk, string $anahtar): string
    {
        $deger = $yuk[$anahtar] ?? '';

        return is_scalar($deger) ? (string) $deger : '';
    }

    /**
     * Sağlayıcı adresi — sandbox mı canlı mı.
     *
     * ⚠️ `settings`'te DEĞİL `config`'te: hangi hesap sorusu markaya göre
     * değişiyor, sandbox mı canlı mı sorusu ORTAMA göre.
     */
    private function sunucu(): string
    {
        $adres = config('services.iyzico.base_uri');

        return is_string($adres) ? rtrim($adres, '/') : '';
    }

    private function apiAnahtari(): string
    {
        return $this->anahtar(self::API_ANAHTARI);
    }

    private function gizliAnahtar(): string
    {
        return $this->anahtar(self::GIZLI_ANAHTAR);
    }

    /**
     * ⚠️ Boş anahtar KABUL EDİLMİYOR — 1E.1'in dersi: `hash_hmac(..., '')`
     * hata vermez, geçerli görünen bir imza üretir ve doğrulama hiçbir şey
     * korumaz.
     *
     * @throws MissingPaymentCredentialsException
     */
    private function anahtar(string $ad): string
    {
        $deger = $this->ayarlar->al(SettingGroup::Payment, $ad);

        if (! is_string($deger) || $deger === '') {
            throw new MissingPaymentCredentialsException($this->ad(), $ad);
        }

        return $deger;
    }
}
