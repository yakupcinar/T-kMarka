<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `domains.domain` küçük harf garantisi.
 *
 * Alan adları DNS'te büyük/küçük harf duyarsızdır. Bugün küçültme iki yerde
 * elle yapılıyor: `tenant:create` komutunda ve `DomainCheckController`'da.
 * İkisi de PHP tarafında — yani modelden/komuttan geçmeyen her yol açık.
 *
 * ⚠️ Buradaki risk `customers.email`'dekinden AĞIR:
 *   'Marka-A.com' ve 'marka-a.com' iki ayrı satır olarak durabilir ve
 *   FARKLI markalara bağlanabilir. O zaman gelen istek hangisine eşleşirse
 *   o markanın mağazası açılır — yani YANLIŞ MARKA servis edilir.
 *   M-2'nin var olma sebebi olan izolasyon tam da burada delinir.
 *
 * Faz 3'te kontrol düzlemi alan adını web formundan alacak; o controller'ı
 * yazan kişinin küçültmeyi hatırlamasına güvenmek yerine garantiyi
 * veritabanına koyuyoruz. (customers/users ile tutarlı)
 *
 * Not: paketin kendi migration'ına dokunulmuyor — kısıt ayrı dosyada
 * ekleniyor ki paket güncellenince çakışma olmasın.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE domains ADD CONSTRAINT domains_domain_lowercase
             CHECK (domain = lower(domain))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE domains DROP CONSTRAINT domains_domain_lowercase');
    }
};
