<?php

namespace App\Http\Storefront;

use App\Domain\Payment\PaymentProvider;
use App\Domain\Payment\PaymentProviderFactory;
use App\Domain\Payment\PaymentWebhookService;
use App\Domain\Payment\QueryablePaymentProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ödeme bildirimi (webhook) — GERÇEĞİN geldiği yer. (1E.4)
 *
 * ⚠️ Bu uç KİMLİK DOĞRULAMASIZ olmak zorunda: sağlayıcı bizim token'ımızı
 * bilmiyor. Tek koruma İMZA.
 *
 * ⚠️ Uç `magaza-acik` kapısının DIŞINDA — bilerek. Kapının arkasında
 * olsaydı marka mağazasını kapattığı an, çoktan başlamış ödemelerin
 * bildirimleri 503 alırdı: para çekilmiş, sipariş sonsuza kadar `pending`.
 * Mağazanın kapalı olması "yeni sipariş alma" demek; "başlamış ödemeyi
 * görmezden gel" demek değil.
 *
 * ⚠️ Kiracı ALAN ADINDAN çözülüyor (marka-a.localhost/webhooks/payment).
 * Tek merkezî adrese yollayan bir sağlayıcı çıkarsa marka ayrımı yükten
 * yapılmak zorunda kalır; o zaman merkez şemada eşleme tablosu gerekir.
 * ⚠️ Yanlış şemaya yazılan tahsilat HATA VERMEZ — A'nın parası B'nin
 * defterinde görünür (0.5, kiracılık tuzağı).
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentProviderFactory $saglayicilar,
        private readonly PaymentWebhookService $bildirimler,
    ) {}

    public function store(Request $istek): JsonResponse
    {
        $saglayici = $this->saglayicilar->coz();

        /** @var array<string, mixed> $yuk */
        $yuk = $istek->all();

        /*
        | ★ İLK KAPI: İMZA.
        |
        | ⚠️ Doğrulama BAŞARISIZSA hiçbir kayıt açılmıyor, hiçbir stok
        | hareketi olmuyor, yük bile saklanmıyor. Saklansaydı, herkesin
        | doldurabileceği bir tabloya sahip olurduk.
        |
        | ⚠️ 401 dönüyoruz: sağlayıcı bunu "yeniden dene" olarak
        | yorumlamaz. Sahte istek atan biri tekrar denemeye teşvik
        | edilmemeli.
        */
        $imza = $this->imza($istek, $saglayici);

        /*
        | ★ İMZA VARSA MUTLAKA DOĞRULANIR (A).
        |
        | ⚠️ İmzasız kabul ediyoruz diye imzalıyı gevşetmiyoruz: bozuk
        | imza, imzasızdan DAHA kötü bir işarettir — ya anahtar değişmiş
        | ya da biri kurcalıyor.
        */
        if ($imza !== null && ! $saglayici->webhookuDogrula($yuk, $imza)) {
            return response()->json(['message' => 'İmza doğrulanamadı.'], 401);
        }

        /*
        | ★ İMZA YOKSA: yalnızca SORGULANABİLİR sağlayıcıya izin (B · 1E-K12).
        |
        | ⚠️ Ölçüldü: iyzico sandbox `X-Iyz-Signature` başlığını BOŞ
        | gönderiyor (imza özelliği hesapta ayrıca aktive ediliyor).
        | İmzasız mesaj bir yabancının yazdığı kâğıttan farksız — ama
        | sorgulanabilir sağlayıcıda mesajın GÖVDESİNE hiç güvenilmiyor:
        | içinden yalnızca referans okunup gerçek sağlayıcıya soruluyor.
        |
        | ⚠️ Sorgulanamayan sağlayıcıda imzasız bildirim REDDEDİLİR.
        | Genel gevşetme değil, sağlayıcı başına beyan edilen yetenek.
        */
        if ($imza === null && ! $saglayici instanceof QueryablePaymentProvider) {
            return response()->json(['message' => 'İmza doğrulanamadı.'], 401);
        }

        $sonuc = $this->bildirimler->isle($saglayici->webhookuCoz($yuk), $saglayici->ad());

        /*
        | ⚠️ 200 — üç sonuçta da. `already_processed` bir hata değil:
        | sağlayıcı açısından bildirim teslim edildi. Hata dönseydi
        | 15 dakika sonra yine dener, biz yine aynı cevabı verirdik.
        */
        return response()->json(['result' => $sonuc->value]);
    }

    /**
     * İmzayı sağlayıcının bildirdiği başlıklardan ilk DOLU olanından okur.
     *
     * ⚠️ Boş başlık imza SAYILMIYOR. iyzico sandbox'ta `X-Iyz-Signature`
     * başlığını BOŞ değerle gönderdiği ölçüldü (1E.7.3); boş değer
     * "imzalanmamış" demektir ve kabul edilirse imza kontrolü hiçbir şey
     * korumaz.
     */
    private function imza(Request $istek, PaymentProvider $saglayici): ?string
    {
        foreach ($saglayici->imzaBasliklari() as $baslik) {
            $deger = $istek->header($baslik);

            if (is_string($deger) && trim($deger) !== '') {
                return $deger;
            }
        }

        return null;
    }
}
