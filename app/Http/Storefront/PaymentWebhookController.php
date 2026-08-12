<?php

namespace App\Http\Storefront;

use App\Domain\Payment\PaymentProviderFactory;
use App\Domain\Payment\PaymentWebhookService;
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
        if (! $saglayici->webhookuDogrula($yuk, $istek->header($saglayici->imzaBasligi()))) {
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
}
