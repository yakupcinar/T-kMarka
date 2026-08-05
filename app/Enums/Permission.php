<?php

namespace App\Enums;

/**
 * Marka panelindeki izinler. (docs/domain-model.md §3)
 *
 * ⚠️ Liste KODDA SABİT. Panelden yeni izin TÜRÜ üretilemez; roller bu
 * listeden seçim yapar. Üretilebilseydi her izin için ayrıca "bu izin neyi
 * kontrol ediyor" eşlemesi tutmak gerekirdi ve izin sistemi kendi başına bir
 * projeye dönerdi.
 *
 * Yeni bir izin eklemek = buraya bir `case` + onu kontrol eden yerde kullanım.
 * Enum olduğu için yazım hatası çalışma anında değil, **yazım anında** yakalanır.
 */
enum Permission: string
{
    // ── Katalog ────────────────────────────────────────────────────────
    case ProductView = 'product.view';
    case ProductWrite = 'product.write';

    // ── Sipariş ────────────────────────────────────────────────────────
    case OrderView = 'order.view';

    /** Kargoya verme, paket oluşturma. */
    case OrderFulfill = 'order.fulfill';

    /** ⚠️ Para iadesi — depocuda olmaması gereken izin. */
    case OrderRefund = 'order.refund';

    // ── Müşteri ────────────────────────────────────────────────────────
    case CustomerView = 'customer.view';

    // ── Mağaza yönetimi ────────────────────────────────────────────────

    /** ⚠️ Ödeme sağlayıcı anahtarlarına da erişim demek (settings). */
    case SettingsWrite = 'settings.write';

    /** ⚠️ Personel davet/çıkarma — yetki yükseltmeye en yakın izin. */
    case StaffManage = 'staff.manage';

    /** Ciro, kâr raporu. */
    case FinanceView = 'finance.view';

    /**
     * Panelde gösterilecek okunabilir ad.
     *
     * Burada duruyor çünkü izin listesi zaten burada; ayrı bir çeviri
     * dosyasına koymak iki yeri senkron tutmayı gerektirirdi.
     */
    public function etiket(): string
    {
        return match ($this) {
            self::ProductView => 'Ürünleri görüntüleme',
            self::ProductWrite => 'Ürün ekleme ve düzenleme',
            self::OrderView => 'Siparişleri görüntüleme',
            self::OrderFulfill => 'Siparişi kargoya verme',
            self::OrderRefund => 'İade ve para iadesi',
            self::CustomerView => 'Müşterileri görüntüleme',
            self::SettingsWrite => 'Mağaza ayarlarını değiştirme',
            self::StaffManage => 'Personel yönetimi',
            self::FinanceView => 'Finansal raporlar',
        };
    }

    /** @return list<string> */
    public static function tumDegerler(): array
    {
        return array_map(fn (self $izin) => $izin->value, self::cases());
    }
}
