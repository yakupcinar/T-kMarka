<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markaya özel HER ŞEY. (docs/domain-model.md §4)
 *
 * White-label iskeletin kalbi (M-1): iki markayı birbirinden ayıran şey kod
 * değil, bu tablodaki satırlar. Logo, renk, KDV oranı, kargo ücreti, yasal
 * metinler ve ödeme sağlayıcı anahtarları hep burada.
 *
 * ⚠️ Kodda hiçbir yerde marka adı, rengi, KDV oranı veya yasal metin sabit
 *   yazılmayacak. Kabul testi: `grep -ri "tıkmarka" app/` boş dönmeli
 *   (kiracılık altyapısı hariç).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Ayarları konusuna göre kümeler: store · theme · checkout ·
            // shipping · tax · payment · legal
            // Panelde "Kargo ayarları" sekmesi tek sorguyla çekilebilsin diye.
            $table->string('group', 40);

            $table->string('key', 80);

            // Neden jsonb: ayarların tipi tek değil.
            //   "A Markası"                      metin
            //   20                               sayı
            //   true                             mantıksal
            //   {"primary":"#c00"}               nesne
            //   [{"type":"banner"}, ...]         liste
            // jsonb hepsini saklar ve içine sorgu atılmasına izin verir.
            //
            // nullable: "ayar tanımlı ama değeri henüz girilmedi" durumu
            // ile "ayar hiç yok" durumunu ayırabilmek için.
            $table->jsonb('value')->nullable();

            // true ise `value` şifreli saklanır (Laravel `encrypted` cast).
            // Ödeme sağlayıcı anahtarları, kargo API'si, SMTP parolası...
            //
            // ⚠️ Bunlar neden .env'de değil: her markanın KENDİ hesabı var.
            //   .env'e yazılsaydı marka başına ayrı imaj/deploy gerekirdi ve
            //   M-2'nin "tek kod tabanı, tek deploy" kararı çökerdi.
            //
            // ⚠️ Şifreleme APP_KEY ile yapılıyor. Anahtar kaybolursa bu
            //   alanlar bir daha AÇILAMAZ.
            $table->boolean('is_encrypted')->default(false);

            $table->timestampsTz();

            // Aynı grup+anahtar iki kez tanımlanamaz. Tanımlanabilseydi
            // "hangi satır geçerli" sorusu doğar ve okuma sırasına bağlı
            // rastgele davranış ortaya çıkardı.
            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
