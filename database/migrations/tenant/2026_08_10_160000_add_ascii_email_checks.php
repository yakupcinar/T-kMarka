<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * E-posta adreslerinde ASCII dışı karakter yasak — veritabanı seviyesinde.
 *
 * ⚠️ Neden: adreste uluslararası karakter desteği (RFC 6531 / SMTPUTF8)
 * pratikte yok — alan adlarının ~%10'unda çalışıyor. Türkçe karakterli bir
 * adrese posta çoğu sunucudan teslim edilemez; kabul etmek, kullanıcıya
 * "kaydoldun" deyip hiçbir e-postayı alamayacağı bir hesap açmak olur.
 *
 * ⚠️ Neden doğrulama katmanı yetmiyor: `App\Rules\AsciiEmail` yalnızca HTTP
 * isteklerini kapsıyor. Bir artisan komutu, içe aktarma işi ya da
 * tohumlayıcı doğrudan `Customer::create()` çağırırsa doğrulamayı atlar.
 * 1A'da `email = lower(email)` için kurduğumuz emniyetin aynısı.
 *
 * ⚠️ Bu kısıt, `EmailNormalizer`'ın 'İ' → 'i' düzeltmesinden SONRA devreye
 * giriyor. Türkçe klavyede Caps Lock'la yazılan `İSMAIL@…` reddedilmiyor,
 * düzeltilip kabul ediliyor; reddedilen yalnızca gerçekten ASCII dışı
 * kalan adresler.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE customers ADD CONSTRAINT customers_email_ascii
                CHECK (email IS NULL OR email ~ '^[\x21-\x7E]+$')
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE users ADD CONSTRAINT users_email_ascii
                CHECK (email ~ '^[\x21-\x7E]+$')
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_email_ascii');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_ascii');
    }
};
