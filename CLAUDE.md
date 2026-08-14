# CLAUDE.md

TıkMarka — çok kiracılı D2C e-ticaret. Laravel 12 / PHP 8.4 / PostgreSQL 17,
marka başına ayrı **şema** (`tenant_<uuid>`), merkez veriler `public` şemasında.

## Önce bunları oku

| Dosya | İçerik |
|---|---|
| `PLAN.md` | Yol haritası + **şu an neredeyiz**. Her madde gerekçesiyle yazılı. |
| `docs/summary.md` | Tek sayfalık özet — hızlı bağlam için buradan başla. |
| `docs/pre-setup.md` | Mimari kararlar (M-1…M-4) ve **neden** öyle olduğu. |
| `docs/domain-model.md` | Veri modeli, tablo tablo. |

Bir karara katılmıyorsan önce `pre-setup.md`'deki gerekçesini oku; çoğu tuzak
orada zaten yazılı.

## Komutlar — hepsi konteyner içinde

Yerel makinede PHP/Composer **yok**. Günlük işler `Makefile`'da toplu:

```bash
make              # ne var ne yok
make ayaga        # her şeyi başlat (tünel hariç)
make kaldir       # her şeyi başlat + ngrok tüneli
make indir        # her şeyi durdur (tünel dâhil)
make kontrol      # lint + analiz + test — commit öncesi ZORUNLU
make yeniden      # kod değişince: worker + scheduler + caddy
```

Altındaki uzun hâlleri:

```bash
docker compose exec -T app composer lint      # Pint (biçim)
docker compose exec -T app composer analyse   # Larastan seviye 8
docker compose exec -T app composer test      # Pest

docker compose exec -T app composer migrate:landlord   # merkez şema
docker compose exec -T app php artisan tenants:migrate # tüm markalar
docker compose exec -T app php artisan tenant:create "Ad" alan-adi.localhost
```

Adresler: `marka-a.localhost` · `marka-b.localhost` · `localhost` (merkez).
Sertifika uyarısı normal (`tls internal`), `curl -k` kullan.

## Sessiz hataya yol açan kurallar

Bunların hepsi **hata vermeden yanlış sonuç** üretir. Projede en az bir kez yaşandı.

- **Migration klasörü.** `database/migrations/` kökü bilerek **boş**.
  Marka tablosu → `--path=database/migrations/tenant`,
  merkez tablosu → `--path=database/migrations/landlord`.
  Köke düşen dosya kazara merkez şemaya gider.
- **`timestampsTz()`** kullan, `timestamps()` değil. Laravel'in varsayılanı
  saat dilimi taşımayan damga üretiyor (`docs/domain-model.md` §0).
- **Zaman karşılaştırması oturum saat dilimine bağlı.** Laravel `now()`'ı sorguya
  **ofissiz** metin bağlıyor (`'2026-08-11 14:01:38'`); PostgreSQL ofissiz metni
  oturumun `TimeZone`'una göre yorumluyor. Ölçüldü: 15 dk sonra dolacak bir
  rezervasyon, oturum `UTC` iken yaşıyor, `America/New_York` iken **ölmüş**
  sayılıyor — aynı satır, aynı an. WooCommerce'te aynısı yaşandı (#43593),
  Brisbane'de siparişler süre dolmadan iptal ediliyordu. Kapatıldı:
  `config/database.php`'de `'timezone' => 'UTC'` + `tests/Feature/ZamanDilimiTest`.
  Sunucu varsayılanı zaten UTC'ydi — yani **tesadüfen** doğruyduk, artık ayarla.
- **`citext` marka şemasında çalışmıyor** — eklenti `public`'te, marka
  `search_path`'i görmüyor, sessizce düz metin karşılaştırmasına düşüyor.
  E-posta için: modelde küçültme + `CHECK (email = lower(email))`.
- **`$fillable`** = "neyi **asla** dışarıdan almam" listesi. Yetki/sahiplik
  alanları (`is_owner`, `is_system`, `customer_id`) buraya **girmez**.
- **Kod değiştikten sonra** `docker compose restart worker scheduler` —
  kuyruk işçisi kodu belleğe alıyor, bayat kodla çalışmaya devam eder.
- **Marka verisine dokunan zamanlanmış görev** `tenants:run <komut>` ile
  sarılır; doğrudan yazılan görev merkez bağlamda koşar ve hiçbir şey yapmaz.
  ⚠️ Seçenek geçirirken **tırnak içine alma** — `tenants:run "komut --bayrak"`
  "komut tanımlı değil" hatası verir. Doğrusu ayrı seçenek olarak:
  `tenants:run komut --option="bayrak=1"` (argümanlar `--argument=`).
- **Kolon varsayılanı modele ULAŞMAZ.** `->default(true)` yalnızca diske
  yazarken uygulanır; `create()`'ten dönen nesnede alan hiç yoktur ve `null`
  okunur. Üç kez ısırdı: `accepts_marketing` (1A.2) · `is_system` (1A.6) ·
  `is_active` (1B.3). Çözüm modelde `protected $attributes = [...]`;
  `refresh()` de işe yarar ama ek sorgu ve her çağrı yerinde hatırlanmalı.
- **Sürümlenmesi gereken şey `settings`'e konmaz.** Ayar "şu an geçerli
  değer"dir, geçmişi yoktur. Yasal metinler bu yüzden
  `legal_document_versions`'ta ve o tablo **salt-ekleme** — `UPDATE`/`DELETE`/
  `TRUNCATE` veritabanı tetiğiyle reddediliyor. Yayınlamak = yeni satır.
- **Yerel `lint` yeşil ≠ CI yeşil. Otorite CI.** Bir kez `lint:check`
  yerelde geçti, CI'da düştü (`class_attributes_separation`); fark 20 koşu
  boyunca fark edilmedi. Sebep kesinleşmedi — muhtemelen Pint'in geçici
  klasördeki önbelleğinde bayat kayıt. Gönderimden sonra durumu gör:
  ```
  curl -s "https://api.github.com/repos/yakupcinar/T-kMarka/commits/main/check-runs" \
    | python3 -c "import sys,json;[print(c['name'],c['conclusion']) for c in json.load(sys.stdin)['check_runs']]"
  ```
  Hata ayrıntısı **anotasyonlarda** (günlükler yönetici yetkisi ister);
  `.github/ci-kontrol.sh` çıktıyı oraya basıyor.
- **Bağlı yapılandırma dosyası değişince `restart` gerekir; `up -d` YETMEZ.**
  Compose tanımı değişmediyse `up -d <servis>` konteyneri yeniden
  oluşturmuyor ve `:ro` bağlı dosya (Caddyfile, nginx.conf…) **bayat**
  kalıyor. 1E.7.3'te yarım saat kaybettirdi: Caddyfile'a arka arkaya üç
  düzeltme yazıldı, üçü de "işe yaramadı" sanıldı — hiçbiri
  **yüklenmemişti**. Doğrusu `docker compose restart caddy`.
  ⚠️ Ölçüm bayat yapılandırmaya karşı yapılırsa çıkan sonuç da bayattır;
  "denedim olmadı" demeden önce değişikliğin **yüklendiğini** doğrula.
- **Dış servisin "başarılı" demesi, İSTEDİĞİNİ yaptığı anlamına gelmez.**
  iyzico iadesinde `status: success` döndü ama `price` istenenden düşüktü
  (249,90 istendi, 200 döndü; sebep kesinleşmedi). Kayıtta tam iade
  yazarken müşteriye eksik para gitmiş olurdu. Kural: cevabın **durumuna
  değil sonucuna** bak — tutar, adet, kimlik neyse onu karşılaştır.
- **"Çağrı başarısız" ile "işlem başarısız" AYRI ŞEYLERDİR.** Dış servisler
  ikisini de aynı alanla bildirebiliyor. iyzico yetersiz bakiyede servis
  düzeyinde de `status: failure` döndürüyor; ama `paymentStatus` alanı
  cevapta VAR — yani çağrı başarılı, ödeme başarısız. Ayrım yapılmayınca
  başarısız ödemenin bildirimi 502 aldı: sipariş `pending` kaldı, bağlı
  stok 60 dakika kimseye satılamadı ve müşteri neden reddedildiğini
  öğrenemedi. Kural: cevapta **işlemin kendi durumu** varsa o bir
  *sonuçtur*, hata değil.
- **`SoftDeletes` + `firstOrFail()` = gecikmeli patlama.** Varsayılan sorgu
  silinmişleri görmüyor; kayıt "yok" sayılıp istisna fırlıyor. 1E.6'da
  ısırdı: marka, ödemesi yolda olan siparişin varyantını katalogdan
  kaldırınca `StockService::kilitle()` patladı — webhook 404 döndü,
  sağlayıcı üç kez denedi, üçü de düştü ve **tahsilat hiç kaydedilmedi.**
  Kural: bir kaydı **kapatan** yol (kesinleştirme, iptal, iade) silinmişi
  de görmeli (`withTrashed()`); **açan** yol görmemeli.
- **Uçtan uca testte kimlik MODELDEN okunmaz.** İsteğin gövdesine giren her
  kimlik (uuid, sürüm no, satır id) bir önceki **uçtan** gelmeli. `$varyant->uuid`
  yazmak testi yeşil tutar ama "istemci bu değeri nereden bulacak" sorusunu
  sormaz. 1D.6'da iki ölü uç bu yüzden 232 testin altından geçti: vitrin varyant
  `uuid`'sini döndürmüyordu ve vitrinde yasal metin ucu hiç yoktu — yani gerçek
  müşteri sipariş **veremiyordu**. İki kiracıda gerçek HTTP koşusu yakaladı.
- **Türetilmiş metne DEĞİŞKEN SAYIDA parça konmaz.** Benzerlik puanı metnin
  uzunluğuna duyarlı; parça sayısı veriye göre değişince eşik kayar ve kayıt
  **sessizce aranamaz** olur. 2C'de ısırdı: `search_text`'e varyant SKU'ları da
  yazılıyordu; testte 1, gerçek üründe **9** varyant vardı, skor 0,33'ten
  0,286'ya düştü ve ürün *varyant sayısı arttığı için* bulunamaz oldu. Test
  yeşildi, iki kiracıda gerçek HTTP koşusu yakaladı. SKU tam-token eşleşmesine
  (FTS vektörü) taşındı.
- **Kolon sonradan eklendiyse GERİYE DÖNÜK DOLDURMA gerekir.** Türetilmiş kolon
  yalnızca kayıt *değiştiğinde* yazılır; migration'dan önceki satırlar boş kalır
  ve bu **hata vermez**. 2C'de arama, mevcut hiçbir ürünü bulmuyordu — vitrin
  çalıştığı için fark edilmesi zordu. `php artisan tenants:run "search:reindex"`.
- **Her cevap JSON — `Accept` başlığı OLMAYAN istemci 500 alıyordu.** Laravel
  kimliksiz HTML isteğini `login` rotasına yönlendirmeye çalışıyor; arayüz
  olmadığı için (M-3) öyle bir rota yok. **425 testin hiçbiri yakalamadı**:
  `postJson`/`getJson` başlığı otomatik ekliyor, gerçek `curl` koşusu ortaya
  çıkardı. Çözüm `app/Http/Middleware/ForceJson.php` (istek düzeyinde başlık).
  ⚠️ `shouldRenderJsonWhen` ve `$exceptions->render(AuthenticationException)`
  ikisi de denendi, **ikisi de çözmedi** — Laravel bu istisnayı kullanıcı geri
  çağırmalarından önce eşliyor. Test: `tests/Tenancy/JsonCevapTest.php`, ve o
  dosyada `postJson` KULLANILMAZ (kullanılırsa hiçbir şey ölçmez).
- **Aynı kilit `.git` dosyalarında da oluyor — belirtisi FARKLI.**
  `fatal: unable to access '.git/config': Operation not permitted` ve
  `warning: unable to access '.git/info/exclude'`. Dosyanın izinleri normal,
  `head` ile okunuyor, ama git erişemiyor. Çözüm aynı: **sil ve yeniden yaz**.
  ⚠️ `.git/config` silinmeden önce içeriği okunmalı — remote adresi orada.
- **Docker Desktop bir dosyayı konteynerde OKUNAMAZ hâle getirebiliyor.**
  Belirti: `hash_file(): … errno=35 Resource deadlock avoided` — phpstan
  başlamadan düşüyor, hangi dosya olduğunu söylemiyor. Host'ta dosya
  sorunsuz okunuyor. Bulmak için konteyner içinden tara:
  ```
  docker compose exec -T app php -r '$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator("/var/www/html/app")); foreach($it as $f){ if($f->isFile() && @hash_file("sha256",$f->getPathname())===false) echo $f->getPathname(),PHP_EOL; }'
  ```
  Çözüm: dosyayı **sil ve yeniden yaz** (inode değişsin). `touch` ve konteyner
  yeniden başlatma yetmiyor — ikisi de denendi.
- **`<>` ile `IS DISTINCT FROM` aynı şey DEĞİL.** SQL'de `null <> null` sonucu
  `null`'dur — yani "farklı" sayılmaz ve satır `WHERE`/`HAVING`'den sessizce
  düşer. 2E'de denetim sorgusunda ısırdı: yorumu olmayan ürünlerdeki sayaç
  bozukluğu (`rating_avg` dolu ama olması gereken `null`) denetimden tamamen
  kaçıyordu. Karşılaştırılan iki taraftan biri `null` olabiliyorsa
  `IS DISTINCT FROM` kullan.
- **Yeni PostgreSQL uzantısı İKİ yere yazılır.** `docker/postgres/init.sql`
  (yerel) **ve** `.github/workflows/ci.yml` (CI servis konteynerinde init.sql
  yok). 2C'de ikincisi unutuldu: yerelde 396 test yeşil, CI kırmızı — uzantı
  yerelde vardı. "Otorite CI" kuralının ikinci örneği.
- **Uzantılar `public`'te, marka `search_path`'i onları GÖRMEZ.** Üç kez ısırdı:
  `citext` (1A) · `ltree` (1B) · `pg_trgm` (2C). Hepsi nitelikli yazılmalı —
  `public.similarity`, `public.gin_trgm_ops`, `OPERATOR(public.<%)`. (Türkçe FTS
  sözlüğü `pg_catalog`'ta olduğu için görünüyor, o istisna.)
- **`tenants` tablosuna kolon eklemek YETMEZ — `getCustomColumns()`'a da yazılır.**
  Paketin varsayılanı `['id']`; geri kalan HER alan `data` json'ına gidiyor.
  3B'de ölçüldü: kolon `NULL`, veri json'da, ama `$tenant->name` **doğru**
  değeri veriyor — yani kod çalışıyor gibi görünüyor. Kırılan tek şey SORGU:
  `where('trial_ends_at', '<=', now())` hiçbir şey bulmaz, hata da vermez.
  ⚠️ Alan iki yerde birden durursa **`data` kazanıyor** (ölçüldü) — bu yüzden
  kolona taşırken `data`'dan `- 'anahtar'` ile SİLİNMELİ.
- **PostgreSQL'in jsonb `?` operatörü PDO'da YAZILAMAZ.** `data ? 'name'`
  sorgusu `syntax error at or near "$1"` veriyor: PDO `?` işaretini parametre
  yer tutucusu sanıyor. Fonksiyon biçimi kullan: `jsonb_exists(data, 'name')`.
- **Yeni marka geliştirmede HTTPS'e çıkmaz.** `docker/caddy/Caddyfile`'da alan
  adları elle sayılı; `tenant:create` başarılı görünür ama site açılmaz.
  Alan adını ekleyip `docker compose restart caddy`. (Faz 3: on-demand TLS.)

## Yapı

```
app/Platform/   merkez şema (Tenant)          app/Models/    marka şeması modelleri
app/Tenancy/    kiracılık KOMUTLARI           app/Http/      Platform · (Panel · Storefront)
app/Domain/     iş mantığı — kiracıdan habersiz
```

⚠️ Kiracılık **tek klasörde toplanmıyor** — `app/Tenancy/` yalnızca komutları
tutuyor (142 satır). Kiracılığa dokunan yerlerin tamamı:
`config/tenancy.php` (paket ayarı, tohumlayıcı sınıfı) · `routes/tenant.php`
(kapı görevlisi middleware zinciri) · `bootstrap/app.php` (takma adlar,
istisna eşlemeleri) · `tests/Pest.php` (kiracı kurulumu ve temizlik).
Bir kiracılık davranışı ararken bu beşine bak.

`app/Domain/` içindeki hiçbir dosya `Tenancy` sınıflarını import etmez ve
"hangi kiracıdayım" diye sormaz (M-2.7). **Ölçüldü:** `app/Domain/` içinde
`App\Tenancy`, `tenant(`, `tenancy(` geçişi sıfır.

**İş kuralı controller'a yazılmaz.** Kural: bir kontrol, HTTP dışından
(artisan komutu · kuyruk işi · tohumlayıcı) atlanabiliyorsa `app/Domain/`'e
girer. Controller yalnızca çevirir: isteği al, servisi çağır, cevabı biçimle.

Testler: `tests/Feature/` → `RefreshDatabase` var. `tests/Tenancy/` → **yok**
(transaction, şema oluşturmayı bozuyor); temizlik `tests/Pest.php`'de.

## Çalışma biçimi

- Belgeler ve kod yorumları **Türkçe**, tanımlayıcılar İngilizce.
- Bir madde bitince: `lint` + `analyse` + `test` üçü de yeşil olmadan commit yok.
- Plan canlıdır: gerçek planla çelişirse **plan güncellenir**, gerekçesiyle.
- Commit mesajlarına co-author/imza satırı **eklenmez**.
