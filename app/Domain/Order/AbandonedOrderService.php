<?php

namespace App\Domain\Order;

use App\Domain\Notification\Notifier;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ödemesi yarım kalmış siparişler — terk edilmiş ödeme. (2F)
 *
 * ★ 2F-K1: SEPET DEĞİL, SİPARİŞ hedefleniyor.
 *
 * Sınır dürüstçe kabul edildi: misafirin e-postasını ancak ödeme adımında
 * öğreniyoruz, sepette e-posta alanı yok. Ama `pending` kalmış sipariş daha
 * güçlü bir sinyal — orada e-posta zaten dolu (1D) ve müşteri niyetini çoktan
 * göstermiş.
 */
class AbandonedOrderService
{
    /**
     * Hatırlatma için beklenen süre.
     *
     * ⚠️ `StockService::ODEME_DAKIKA` (60) ile AYNI, tesadüfen değil:
     * rezervasyon o süre boyunca ayakta. Daha erken gönderilseydi müşteri
     * hâlâ 3DS ekranında olabilirdi — "ödemenizi tamamlayın" maili tam
     * ödeme yaparken düşerdi.
     */
    public const BEKLEME_DAKIKA = 60;

    /**
     * ★ ÜST SINIR — EN ÖNEMLİ KORUMA.
     *
     * ⚠️ `abandoned_reminded_at` kolonu SONRADAN eklendi; eklendiği an
     * geçmişteki BÜTÜN `pending` siparişler "hatırlatılmamış" görünüyor.
     * Üst sınır olmasaydı görevin ilk koşusu aylar öncesine kadar herkese
     * mail atardı — hata vermeden, tek seferde.
     *
     * (2C'de aynı sınıf hata yaşandı: sonradan eklenen kolon geçmiş
     * satırlarda boş kalıyor. Orada sonuç sessiz bir eksiklikti, burada
     * sessiz bir SALDIRI olurdu.)
     *
     * ⚠️ Ayrıca anlamlı: üç gün önce vazgeçen müşteriye "ödemenizi
     * tamamlayın" demek geç ve rahatsız edici.
     */
    public const SON_GECERLILIK_SAAT = 72;

    public function __construct(private readonly Notifier $bildirimler) {}

    /**
     * Hatırlatma bekleyen siparişler.
     *
     * @return Builder<Order>
     */
    public function bekleyenler(): Builder
    {
        $simdi = now();

        return Order::query()
            /*
            | ⚠️ Yalnızca `pending`. `failed` DIŞARIDA: ona ödeme başarısız
            | maili zaten gitti (1E) ve ikinci bir "tamamlayın" maili
            | müşteriye iki farklı hikâye anlatırdı.
            */
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNull('abandoned_reminded_at')

            // Rezervasyon süresi dolmuş olanlar.
            ->where('created_at', '<=', $simdi->copy()->subMinutes(self::BEKLEME_DAKIKA))

            // ★ Üst sınır — gerekçesi sabitte.
            ->where('created_at', '>=', $simdi->copy()->subHours(self::SON_GECERLILIK_SAAT))

            /*
            | ⚠️ `whereNotNull` DEĞİL — ölçüldü: `orders.email` kolonu zaten
            | `NOT NULL` (veritabanı reddediyor, test bunu kanıtladı).
            | Null kontrolü ölü kod olurdu.
            |
            | BOŞ METİN ise mümkün ve tehlikeli: gönderim sessizce düşer,
            | sipariş yine de "hatırlatıldı" işaretlenir — yani müşteri
            | hiçbir zaman mail almaz, kayıt aldığını söyler.
            */
            ->where('email', '!=', '')
            ->orderBy('id');
    }

    /**
     * Hatırlatmaları gönderir.
     *
     * @return int gönderilen sayısı
     */
    public function hatirlat(): int
    {
        $sayac = 0;

        foreach ($this->bekleyenler()->cursor() as $siparis) {
            if ($this->hatirlatBir($siparis)) {
                $sayac++;
            }
        }

        return $sayac;
    }

    /**
     * Tek siparişe hatırlatma — işaretleme ÖNCE.
     *
     * ⚠️ PUBLIC, ve bunun sebebi bir KIRMA DENEMESİ: özelken "işaretleme
     * gönderimden önce" iddiasını hiçbir test ölçemiyordu. `bekleyenler()`
     * zaten işaretlileri eliyor, dolayısıyla `hatirlat()` üzerinden yapılan
     * her deneme yanlış sebeple yeşil kalıyordu. Gerçek yarış ancak bu
     * metot iki kez çağrılarak görülüyor.
     *
     * @return bool gönderildiyse true
     */
    public function hatirlatBir(Order $siparis): bool
    {
        /*
        | ★ İŞARETLEME GÖNDERİMDEN ÖNCE ve KOŞULLU GÜNCELLEMEYLE.
        |
        | ⚠️ Sonra işaretlenseydi: mail kuyruğa atıldıktan sonra süreç
        | düşerse sipariş "hatırlatılmamış" kalır ve bir sonraki koşuda
        | ikinci mail giderdi (2F-K3'ün ihlali).
        |
        | ⚠️ `where('abandoned_reminded_at', null)` KOŞULU şart: iki
        | zamanlanmış görev aynı anda koşarsa (birden çok scheduler
        | konteyneri — `withoutOverlapping` yalnızca kendi süreci için
        | geçerli) ikisi de aynı siparişi görür. Koşullu güncelleme
        | yalnızca BİRİNİ kazandırıyor; `affected === 0` olan gönderme.
        |
        | 1D-K5'in tekrarı: "acaba gönderilmiş mi" kontrolü yarışı çözmez.
        */
        $etkilenen = DB::table('orders')
            ->where('id', $siparis->id)
            ->whereNull('abandoned_reminded_at')
            ->update(['abandoned_reminded_at' => now()]);

        if ($etkilenen === 0) {
            return false;
        }

        $this->bildirimler->odemeHatirlatmasi($siparis);

        return true;
    }
}
