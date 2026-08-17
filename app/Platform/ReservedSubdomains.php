<?php

namespace App\Platform;

use Illuminate\Support\Str;

/**
 * Marka alan adı olarak KULLANILAMAYACAK adlar. (3D)
 *
 * ★ İki ayrı tehlike var ve ikisi de sessiz:
 *
 * 1  ÇAKIŞMA — biri `panel` adıyla marka açarsa `panel.tikmarka.com`
 *    onun olur; kendi kontrol panelimizin adresini kaybederiz.
 *
 * 2  KİMLİĞE BÜRÜNME — `www`, `mail`, `admin` gibi adlar müşteriye
 *    "burası TıkMarka'nın resmi sayfası" hissi verir. Oltalama için
 *    hazır zemin.
 */
class ReservedSubdomains
{
    /**
     * ⚠️ KAPALI LİSTE. "Şüpheliyse engelle" gibi bir kural yazılsaydı
     * hangi adın geçtiği tahmin edilemezdi; burada tek tek yazılı.
     *
     * @var list<string>
     */
    public const AYRILMIS = [
        // Kendi altyapımız
        'panel', 'platform', 'admin', 'api', 'app', 'www', 'tikmarka',

        // Teknik/altyapı adları — kimliğe bürünmeye açık
        'mail', 'smtp', 'imap', 'pop', 'ftp', 'ssh', 'ns', 'ns1', 'ns2',
        'cdn', 'static', 'assets', 'img', 'images', 'media', 'files',

        // Yaygın kurumsal alt alan adları
        'blog', 'shop', 'store', 'help', 'support', 'destek', 'yardim',
        'docs', 'status', 'dev', 'test', 'staging', 'demo', 'beta',

        // Ödeme/güvenlik çağrışımı — oltalama riski en yüksek grup
        'pay', 'odeme', 'payment', 'secure', 'guvenli', 'login', 'giris',
        'account', 'hesap', 'billing', 'fatura',

        // Yerel geliştirme
        'localhost',
    ];

    /** Bu alt alan adı ayrılmış mı? */
    public static function ayrilmisMi(string $altAlanAdi): bool
    {
        /*
        | ⚠️ Karşılaştırma SLUG üzerinden: "Panel" ve "PANEL" de aynı
        | yere düşüyor. Ham metin karşılaştırılsaydı büyük harfle
        | yazılan ayrılmış bir ad listeden kaçardı.
        */
        return in_array(Str::slug($altAlanAdi), self::AYRILMIS, true);
    }
}
