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
  curl -s "https://api.github.com/repos/yakupcinar/TikMarka/commits/main/check-runs" \
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
- **Merkez (`routes/web.php`) rotaları `web` grubunda — CSRF var.** Panel/vitrin
  `api` grubunda olduğu için bu tuzak Faz 1'de görünmedi; 3C'de kontrol düzlemi
  `web.php`'ye yazıldı, **bütün testler yeşildi** ama gerçek `curl` isteği
  `CSRF token mismatch` aldı. Sebep: testler `postJson` kullanıyor. Merkez API
  rotaları ayrı dosyada (`routes/platform.php`) ve `api` grubuyla yükleniyor.
  ⚠️ Karar 1A.2'de zaten verilmişti ve unutuldu — yorum yetmiyor, ölçen test
  gerekiyor (`KontrolDuzlemiTest`, rotanın middleware listesine bakıyor).
- **Test veritabanında ŞEMA silinip kiracı KAYDI kalırsa süit çöker.** Belirti:
  `relation "roles" does not exist` ya da `schema … does not exist`. Genelde
  yarıda kesilen bir koşudan kalıyor. Toparlamak için şemaları düşür **ve**
  merkez tabloları boşalt:
  ```
  TRUNCATE tenants, domains, plans, platform_users, personal_access_tokens RESTART IDENTITY CASCADE;
  ```
  ⚠️ İki test süreci aynı test veritabanında paralel koşarsa da aynı belirti
  çıkıyor — arka planda süit koşarken ikinci bir koşu başlatma.
- **Test GERÇEK dosya sistemini siler — `storage/` paylaşılıyor.** 3G'de
  yaşandı: öksüz klasör temizliği testi `--onayla` ile koştu ve geliştirme
  ortamındaki **gerçek marka klasörlerini** sildi (3 ürün görseli gitti, kayıt
  kaldı); `storage/framework` de silinip test süiti çöktü (`Please provide a
  valid cache path`). Veritabanı testte ayrı (`tikmarka_test`) ama **disk
  ayrı değil**. Kural: dosya silen her servis **kök parametresi** almalı ve
  test kendi geçici klasöründe çalışmalı.
- **Adlandırılmış birim, imajdaki karşılığından DOLUYOR — izinler dâhil.**
  Klasör imajda yoksa birim `root:root 755` doğuyor. 4.5D'de Blade
  derleyici geçici dosyasını yazamadı ve belirti yanıltıcıydı: hata "izin
  yok" değil `tempnam(): file created in the system's temporary directory`
  — PHP sessizce sistem geçici klasörüne düşüyor, sonra `rename()` dosya
  sistemleri arasında patlıyor. Düzeltme `Dockerfile`'a yazılır; elle
  `chmod` taze kurulumda kaybolur.
- **DOĞRULAMAMIZ DIŞ SERVİSTEN GEVŞEK OLAMAZ.** Laravel'in `email` kuralı
  `a@a` ve `a@aa` kabul ediyor; iyzico reddediyor (*"email hatalı format
  ile gönderilmiştir"*). ⚠️ Bedeli ZAMANLAMA: doğrulama geçtiği için
  **sipariş oluşuyor**, stok bağlanıyor ve ödeme ondan SONRA patlıyor —
  bağlı stok 60 dakika kimseye satılamıyor. `App\Rules\DeliverableEmail`
  alan adında nokta + en az iki harflik TLD arıyor. ⚠️ DNS sorgusu
  YAPILMIYOR: ödeme akışında ağa çıkmak isteği yavaşlatır ve ağ
  kesintisinde satışı durdururdu (4.5C'de tek sorgu 24 sn sürmüştü).
- **FORM ALANLARI DOĞRULAMAYLA HİZALI OLMALI — testler bunu GÖREMEZ.**
  4.5D'de adres formuna `title` alanı konmamıştı ama `AddressRequest` onu
  zorunlu tutuyordu: **adres defteri hiç kullanılamıyordu.** Müşteri
  "başlık alanı zorunludur" uyarısı alıyor ama neyi dolduracağını ekranda
  göremiyordu. ⚠️ Testlerin hepsi `ornekAdres()` ile **tam veri**
  gönderdiği için hiçbiri yakalamadı — eksik olan sunucu değil EKRANDI.
  Yeni bir form yazarken doğrulamanın `required` alanlarını tek tek
  ekranla karşılaştır; ölçen test formun HTML'ine bakmalı.
- **Test istemcisi ÇEREZ TAKİP EDİYOR — "oturum kapandı" iddiası bununla
  ölçülemez.** 4.5D'de ölçüldü: çapraz marka denemesinden sonra test, A'nın
  da kapandığını "gösteriyordu"; `curl` ile **eski** çerez elle
  gönderilince A açık kaldı. Test, sunucunun davranışını değil kendi çerez
  takibini ölçüyormuş. Oturum geçersizliğini ölçmek istiyorsan **eski
  çerezi elle** gönder.
- **Testte GERÇEK DNS SORGUSU yapılmaz.** `SystemDnsChecker` ağa çıkıp
  zaman aşımını bekliyor: tek test **24 saniye** sürdü (4.5C). Bundan
  kötüsü test **ağa bağımlı** olur — ağ yoksa kırılır ve ölçtüğü şey bizim
  kodumuz değil internet olur. `FakeDnsChecker` bağla (3H'de bunun için
  yazıldı).
- **`Role::permissions` ÖZELLİK DEĞİL METOT.** `role_permissions` için ayrı
  Eloquent modeli yok (1A.6); `$rol->permissions` yazılırsa Laravel onu
  ilişki sanıyor ve *"must return a relationship instance"* ile **500**
  veriyor. `$rol->permissions()` çağır.
- **Modelin `getRouteKeyName()`'i `uuid` ise arayüz de `uuid` göndermeli.**
  4.5C'de `User` için `id` gönderiliyordu: rota eşleşmiyor ve **404**
  geliyordu — yani "korunuyor" sanılan şey kazaydı. ⚠️ Test de bunu
  ölçüyor sanıyordu; 404 ile 422 arasındaki fark yakalandı.
- **Ödeme formu IFRAME içinde — sağlayıcının HAZIR BETİĞİ kullanılmaz.**
  iyzico hem `checkoutFormContent` (yapıştırılacak `<script>`) hem
  `paymentPageUrl` veriyor. Betik seçilseydi sağlayıcının JavaScript'i
  **bizim kökenimizde** çalışır ve kart alanları bizim DOM'umuzda olurdu —
  PCI kapsamını daraltma amacının tersi. Doğrusu `paymentPageUrl` +
  `&iframe=true` ile ADRESİ gömmek (4.5-K1). ⚠️ Dönüş sayfası
  **çerçeveden çıkmalı** (`window.top`, `window.parent` değil); çıkmazsa
  müşteri "sipariş alındı" ekranını küçük bir çerçevede görür.
- **`assertRedirect()` HEDEFSİZ çağrılırsa yönlendirmenin NEREYE gittiğini
  ölçmez.** 4.5'te iki kez ısırdı: ödeme akışı sağlayıcıya yönlendirmekten
  kendi sayfamıza döndü ve **hiçbir test kırılmadı**; ödeme sayfasındaki
  sözleşme bağlantısı ham JSON'a gidiyordu ve test yalnızca bağlantı
  METNİNİ arıyordu. Hedefi yaz.
- **OTURUM TABANLI KİMLİK ÇOK KİRACILIKTA KENDİLİĞİNDEN GÜVENLİ DEĞİL.**
  Oturum yalnızca kullanıcı `id`'sini tutuyor; guard onu **isteğin
  kiracısının** şemasından çözüyor. İki markada da `id = 1` olan birer
  kullanıcı varsa **A'nın oturum çerezi B'nin panelini açar** — 4H'de
  ölçüldü, 200 dönüyordu. Bugün tarayıcı bunu yapmaz (`SESSION_DOMAIN=null`
  → çerez alan adına bağlı) ama koruma ona bırakılamaz: 3D'deki kayıt
  markalara **alt alan adı** veriyor ve biri `SESSION_DOMAIN`'i
  `.tikmarka.com` yaparsa her marka her paneli açar. Çözüm: girişte
  oturuma marka kimliği damgalanıyor, her istekte doğrulanıyor
  (`EnsureSessionTenant`).
- **LARAVEL MIDDLEWARE'LERİ ÖNCELİK LİSTESİNE GÖRE YENİDEN SIRALIYOR —
  rota grubunda yazdığın sıra SESSİZCE geçersiz olabilir.** 4H'de ısırdı:
  kontrol middleware'i `auth:staff-web`'den önce yazılıydı ama sonra koştu
  (`Authenticate` öncelik listesinde, bizimki değildi). Belirti çok
  yanıltıcı — middleware çalışıyor, uyuşmazlığı doğru görüyor, `logout()`
  işini yapıyor, controller'a `check() === false` ile giriliyor, **ama
  sayfa yine 200 dönüyor**. ⚠️ `prependToPriorityList` denendi, tutmadı.
  Doğrusu: kontrol middleware'i uyuşmazlıkta `$next`'i **çağırmayıp kendi
  cevabını döndürsün** — o zaman zincirin neresinde olduğu fark etmez.
- **`UploadedFile::fake()` MIME TÜRÜNÜ DE UYDURUYOR.** Uzantıdan
  türetiyor; yani "içeriği PHP ama adı .png" senaryosunu **ölçemezsin** —
  doğrulama `image/png` görür ve test yeşil kalır. İçerik tabanlı tür
  kontrolünü sınayan testte **gerçek dosya** yaz ve `new UploadedFile(...)`
  ile gönder (4G'de ölçüldü).
- **SVG LOGO/GÖRSEL KABUL EDİLMEZ.** XML belgesidir ve `<script>`
  taşıyabilir; tarayıcı `<img>` içinde çalıştırmasa da doğrudan açıldığında
  çalıştırır. Marka kendi vitrininde betik çalıştırabilseydi 4-K5'in
  kapattığı kapı yeniden açılırdı.
- **`tenant_asset()` `app/Domain/` İÇİNDEN ÇAĞRILAMAZ** (M-2.7): Domain
  doğrulanmış YOLU döndürür, adresi HTTP katmanı kurar. 4A'da logo yolu
  doğrudan `src`'ye basılıyordu; 4G'de yükleme gelince o hâliyle **kırık
  görsel** çıkardı.
- **VERİ DÖKÜMÜNDE TABLO LİSTESİNİ DARALTMAK YETMEZ — KOLON da temizlenir.**
  4F'de ölçüldü: marka dökümüne `customers.password` üzerinden **bcrypt
  hash'leri** girmişti. Sorun tablonun kendisi değil içindeki kolondu.
  Kimlik bilgisi iş verisi değildir — marka "kim müşterim"i alır,
  "müşterim hangi parolayı kullanıyor"u almaz.
  ⚠️ Şifreli ayar değerleri de çıkarılır: şifreli olması dosyaya
  konabileceği anlamına gelmiyor (dosya `APP_KEY` ile birlikte sızarsa
  çözülür). `TenantDataExport::HASSAS_KOLONLAR`.
- **Merkez rotalarda `route()` HER ZAMAN İLK alan adını üretir.**
  `central_domains` birden çok alan adı içeriyor (`localhost`,
  `127.0.0.1`, ileride gerçek alan adı). 4F'de ısırdı: `localhost`'tan
  giriş yapan yönetici `127.0.0.1`'e savruluyordu ve oturum çerezi orada
  geçerli olmadığı için giriş ekranına geri düşerdi. Merkez yönlendirmelerde
  **göreli yol** kullan (`redirect('/yonetim')`).
- **Inertia middleware'i GLOBAL `web` grubuna eklenmez — rota grubuna eklenir.**
  İki Inertia yüzeyi varsa (marka paneli + kontrol düzlemi) ikisi de `web`
  grubunda çalışır ve **kök görünümü sonuncusu belirler**; yani bir yüzey
  diğerinin kabuğuyla render edilebilir. Her yüzey kendi middleware'ini
  kendi grubunda takar (4F'de daraltıldı).
- **`node_modules` BAĞLI KLASÖRDE DURMAZ — adlandırılmış birime konur.**
  macOS bind mount üzerinden binlerce küçük dosya okumak hem yavaş hem de
  kilitleniyor: Vite derlemesi `Unknown system error -35` ile düştü, üç
  denemede de aynı yerde. ⚠️ Tek dosyayı yeniden yazma çözümü BURADA
  YETMİYOR (kilitlenen dosya `node_modules` içinde ve binlerce tane var).
  `docker-compose.yml` → `- node_modules:/var/www/html/node_modules` (4E).
  Sonuç: `npm ci` konteyner içinde yaşıyor.
- **Test yardımcısı İKİNCİ dosyada kullanılacaksa `tests/Pest.php`'ye taşınır.**
  Tek test dosyasında tanımlı kalırsa diğer dosya **tek başına** koşturulunca
  "tanımsız fonksiyon" verir — tüm süitte görünmeyen, dosya yükleme sırasına
  bağlı sessiz bağımlılık. 4E'de `iadeyeHazirSiparis` ve `inertiaVerisi`
  bu yüzden taşındı.
  ⚠️ **Aynı madalyonun öteki yüzü: ADI ÇAKIŞMASIN.** Test dosyasındaki
  fonksiyonlar **global** — başka bir test dosyasında aynı ad varsa iki dosya
  birlikte yüklenince PHP *"cannot redeclare"* ile ölür. 4.5H'de yaşandı
  (`koleksiyonluMagaza` iki dosyada); **tek dosya koşarken testler yeşildi**,
  gösteren Larastan oldu (`invoked with 0 parameters, 1 required` — imza
  ÖTEKİ dosyadan okunuyordu). Yardımcı yazmadan önce `grep -rn "function ad" tests/`.
- **`sevkiyatlikSiparis()` PARA İADESİNE HAZIR DEĞİL.** Ödemeyi servisten
  yapıyor, **tahsil edilmiş `Payment` kaydı açmıyor**; `RefundService`
  `firstOrFail()` ile onu arıyor ve bulamayınca **404** dönüyor. ⚠️ Belirti
  yanıltıcı: hata mesajı değil Laravel'in 404 sayfası geliyor, yani "rota
  yok" sanılıyor. Para iadesi testinde `iadeyeHazirSiparis()` kullan.
- **Inertia DevTools her isteğe DOSYA YAZIYOR — kapalı tutulmalı.**
  `storage/inertia-devtools/` altına kayıt açıyor ve periyodik damga
  yazıyor; bağlı klasörde `errno=35` ile düşünce panelin **bütün
  sayfaları 500** verdi. ⚠️ Belirti yanıltıcı: hata `file_put_contents`'ten
  geliyor, yığın izinde sayfayı yazan kod hiç görünmüyor. `config/inertia.php`
  → `devtools.enabled = false` (4D).
- **Inertia'da sunucu cevabı EKRANDAKİ METNİ İÇERMEZ.** Sayfa tarayıcıda
  render ediliyor; cevapta yalnızca bileşen adı ve prop'lar var. Panelde
  `assertSee('Henüz ürün yok')` yazmak testi yalancı yapar — `component`
  ve `props` üzerinden iddia kur. ⚠️ Vitrin bunun TERSİ: orada sayfa
  sunucuda render ediliyor (4-K1), metin aramak doğru yöntem.
- **İç içe rota bağlamada Laravel çocuğu EBEVEYNİN İLİŞKİSİNDEN çözüyor.**
  `{urun:uuid}/{varyant:uuid}` için `Product::varyants()` arıyor; ilişkinin
  adı `variants` olduğu için **500** veriyor. Ya parametre adı ilişkiyle
  hizalanır ya da `withoutScopedBindings()` ile kapsama kapatılıp
  doğrulama açıkça yapılır (4D'de ikincisi seçildi: koruma görünür ve
  ölçülebilir olsun diye).
- **Kırma denemesinde DEĞİŞİKLİĞİN UYGULANDIĞINI doğrula.** 4D'de aynı
  kalıp (`izin:product.write`) iki yerde geçiyordu; `replace(..., 1)` ilk
  eşleşmeyi (panel API'sini) bozdu, sayfa izni sağlam kaldı ve test
  "geçti". Yani kırma denemesi **yanlış yeri kırmıştı** ve testin ölçtüğü
  şey hakkında yanlış güven verdi. Kalıp birden çok yerdeyse hedefi
  konumla daralt ve değişikliği `grep` ile gör.
- **`asset_helper_tenancy` AÇIKKEN Vite varlıkları 404 alıyor.** Paket
  `asset()` çağrılarını `/tenancy/assets/...` yoluna çeviriyor; derlenmiş
  panel paketi orada yok. ⚠️ **Bedeli sessiz:** sunucu 200 ve doğru HTML
  döner, testler (`withoutVite()`) yeşil kalır, ama tarayıcı betiği
  indiremediği için panel **boş sayfa** açılır. Kapatıldı (4C) — marka
  dosyaları zaten açıkça `tenant_asset()` kullanıyor.
- **Panel/Vue değişince `make derle` ŞART.** Derlenmemiş bileşen tarayıcıya
  ulaşmaz; belirti yine boş sayfa. Vitrin etkilenmez (sunucuda render
  edilen Blade, 4-K1).
- **Kimliksiz istek `login` ADLI rotaya yönlendiriliyor.** Bizde öyle bir
  rota yok ve `RouteNotFoundException` ile **500** dönüyor. 2E'de API
  tarafında çıkmıştı (`ForceJson` ile çözüldü), 4C'de panel tarafında
  yeniden çıktı — orada doğru cevap JSON değil **giriş sayfasına
  yönlendirme**. `bootstrap/app.php`'de `redirectGuestsTo` ile yola göre
  ayrılıyor.
- **Inertia sayfa verisi ÖZNİTELİKTE DEĞİL `<script>` içinde.** v2
  `<script data-page="app" type="application/json">` kullanıyor. Testte ham
  metinde `&quot;component&quot;` aramak kırılgan; JSON'u çözüp `component`
  alanına bak.
- **`@section('ad', ifade)` KISA BİÇİMİ virgülde kırılıyor.** İfadenin
  içinde virgül varsa (`Str::limit($x, 150)`) Blade argümanları yanlış
  bölüyor ve **görünüm derlenemez** hâle geliyor. ⚠️ Belirti sinsi: sayfa
  çalışıyor görünüyor ama Larastan görünümü bulamıyor (`view-string`
  hatası) — 4B'de yarım saat aldı. Blok biçimini kullan
  (`@section('ad') … @endsection`).
- **`$errors` ve oturuma bağlı görünüm değişkenleri YALNIZCA `web` grubunda
  var.** `ShareErrorsFromSession` `api` grubunda çalışmıyor. Aynı düzeni
  (layout) iki gruptan render eden bir sayfa varsa `isset($errors)` ile
  korunmalı; korunmazsa `Undefined variable $errors` ile **500** döner.
  4B'de ölçüldü: ödeme dönüş ekranı `api` grubunda (sağlayıcı POST ediyor,
  CSRF üretemez) ve müşteri ödemesini bitirdikten sonra hata sayfası
  görüyordu.
- **`ForceJson` `api` grubunda İNSAN EKRANLARINI da eziyor.** Uç `api`
  grubundaysa `Accept` her istekte JSON'a çevriliyor ve `expectsJson()`
  **her zaman doğru** oluyor — HTML dalı yazsan bile hiç çalışmaz.
  İnsanın gördüğü uçlar `ForceJson::HTML_UCLARI` listesine girer.
  ⚠️ Liste **dar** tutulmalı: genişletilirse 2E'de ölçülen 500 geri gelir.
  ⚠️ Testler bunu görmedi, gerçek `curl` gösterdi (4B).
- **Git BOŞ KLASÖR TUTMAZ — takipteki son dosyayı silmek KLASÖRÜ siler.**
  4A'da CI'ı kırdı: derlenmiş Blade dosyaları yanlışlıkla depodaydı,
  takipten çıkarıldı ve `storage/framework/views`'i ayakta tutan tek şey
  gitti. Taze çıkışta klasör hiç oluşmuyor, Blade derleyemiyor, vitrinin
  **bütün sayfaları** düşüyor. ⚠️ Yerelde görünmez: klasör zaten diskte.
  Çalışma zamanı klasörleri Laravel'in yaptığı gibi `.gitignore` yer
  tutucusuyla tutulur (`*` + `!.gitignore`). Ölçmenin yolu:
  `git clone . /tmp/taze && ls /tmp/taze/storage/framework`.
- **CI hata anotasyonu KONUMA göre değil ÖNEME göre seçilmeli.**
  `.github/ci-kontrol.sh` önce `tail -40` yazıyordu; Pest'in yığın izi
  40 satırı tek başına dolduruyor ve asıl mesaj
  (`Failed asserting that 404 is identical to 200`) izin **üstünde**
  kaldığı için anotasyona hiç girmiyordu. Üstüne GitHub bir adımda ~10
  anotasyon gösteriyor — ekranda yalnızca yığın izinin ortası görünüyor
  ve hata **teşhis edilemiyordu**. Artık satırlar kalıba göre seçiliyor.
- **`getJson` ŞİFRELENMEMİŞ ÇEREZİ DÜŞÜRÜYOR — istek çerezsiz gidiyor.**
  Ölçüldü (4A): aynı istek `get()` ile çerezi taşıyor, `getJson()` ile çerez
  torbası **boş** geliyor ve hata yok. Ayrıca iki yardımcı iki farklı şey
  yapıyor: `withCookie()` değeri **şifreliyor**, `withUnencryptedCookie()`
  düz gönderiyor. Çerez okuyan testte `get()` + elle `Accept` başlığı kullan.
  ⚠️ `postJson`'ın `Accept` başlığını sessizce eklemesiyle (2E) aynı aile:
  **test yardımcısı, ölçmek istediğin şeyi ortadan kaldırıyor.**
- **`EncryptCookies` YALNIZCA `web` grubunda çalışıyor.** `api` grubunda çerez
  middleware'i hiç yok. Bir çerez iki grupta da okunacaksa `encryptCookies`
  istisna listesine girmeli; girmezse aynı çerez iki grupta **iki farklı
  değer** olur ve bu hata vermez — sepet sayfada görünür, sepet ucunda
  görünmez (4A). ⚠️ Bunu ölçen test **iki gruba birden** vurmalı: yalnızca
  `api` tarafına vuran test istisna kaldırılınca **yeşil kalıyor** (ölçüldü).
- **Kullanıcının yazdığı Blade RENDER EDİLMEZ — bu RCE'dir.** Blade PHP'dir ve
  kum havuzu yoktur (Twig'in aksine); `Blade::render()`'a dışarıdan gelen metin
  vermek doğrudan uzaktan kod çalıştırmadır. Cachet'te (#4621) tam bu yaşandı.
  ⚠️ **Bizde bedeli tek marka değil:** şema bazlı kiracılıkta sunucuda kod
  çalıştıran biri `search_path`'i değiştirip **bütün markaların** verisine
  ulaşır. Tema bu yüzden **ayar**, şablon değil (4-K5). Marka şablon yazacaksa
  yol Liquid benzeri **kum havuzlu** bir motordur.
- **Inertia SSR AÇILMAZ (4-K2).** Ayrı Node süreci uzun ömürlü ve tüm markalar
  için ortaktır; modül seviyesindeki durum istekler arasında paylaşılır
  (*cross-request state pollution*) — yani **marka sızması**. M-2.4'te
  pgBouncer'ı reddetme gerekçesinin aynısı. ⚠️ Yerelde görünmez: geliştirme
  sunucusu aynı anda tek istek işliyor. Ayrıca SSR bozulunca **sessizce**
  istemci render'ına düşüyor: sayfa çalışır, testler yeşil kalır, **SEO
  sessizce gider**.
- **Yeni marka geliştirmede HTTPS'e çıkmaz.** `docker/caddy/Caddyfile`'da alan
  adları elle sayılı; `tenant:create` başarılı görünür ama site açılmaz.
  Alan adını ekleyip `docker compose restart caddy`. (Faz 3: on-demand TLS.)

- **`firstOrFail()` OKUMA YOLUNDA veri sorununu 404'e ÇEVİRİR.** Laravel
  `ModelNotFoundException`'ı 404'e eşliyor; yani "kuralın gösterdiği kategori
  yok" gibi bir VERİ sorunu ekranda **"sayfa bulunamadı"** diye görünüyor ve
  gerçek sebep hiç anlaşılmıyor. 4.5H.1'de ısırdı: koleksiyon kuralı var
  olmayan kategori slug'ına bakıyordu — vitrinde koleksiyon 404 verdi, üstelik
  panelde üye sayısı aynı sorgudan geldiği için **tek bozuk kural koleksiyon
  listesinin tamamını** düşürdü. Kural: türetilmiş/başvurulan kayıt okuma
  yolunda bulunamıyorsa **istisna değil boş sonuç** üret — ama koşulu
  **sessizce atlama**, hiçbir şeyle eşleştir (atlanırsa `all` eşleşmesi gevşer
  ve fazla kayıt döner).
- **Kullanıcının GÖRDÜĞÜ ad ile sistemin SAKLADIĞI değer aynı değilse, serbest
  metin kutusu koymak hatayı GARANTİ eder.** 4.5H.1: kural `slug` saklıyor,
  marka kategoriyi adıyla tanıyor; kutu boş bırakıldığı için "Giyim" yazdı ve
  kural **geçerli sayılıp kaydedildi**. Doğrusu listeden seçtirmek + yazma
  yolunda varlığı doğrulamak. ⚠️ Varlık kontrolü **biçim doğrulayan** sınıfa
  konmaz (o sınıf okuma yolunda da çalışıyor ve veritabanına bakmıyor);
  yazma yoluna ait.

- **VARSAYILAN GUARD SAYFA KATMANINDA YANLIŞ.** `config/auth.php`'de
  varsayılan `customer` — yani **sanctum, token**. Sayfalarda kimlik
  OTURUMDA (`customer-web`); `$istek->user()` yazılırsa sanctum sorulur,
  `null` döner ve **giriş yapmış müşteri misafir sayılır**. 4.5I'de
  ölçüldü: sepet müşteriye bağlanmıyor, sipariş `customer_id = null`
  doğuyordu — geliştirme markasında **24 siparişin hepsi**, ödenmişler
  dâhil, sahipsizdi ve "Siparişlerim" sayfası hiçbir zaman dolamazdı.
  ⚠️ API katmanında (`api/*`) varsayılan guard **DOĞRU** — düzeltme tüm
  vitrine değil sayfa katmanına uygulanır. Ölçen test:
  `PanelKapsamTest` sayfa dosyalarında `->user()` arıyor.
- **`actingAs()` VARSAYILAN GUARD'I DA DEĞİŞTİRİYOR — guard hatasını
  GİZLER.** 4.5I'de iki kez ısırdı: (1) kök sebebi ölçmek için yazdığım
  test `actingAs` ile **hatalı kodla yeşil geçti**; gerçek `/giris`
  POST'uyla ölçünce düştü. (2) `GomuluOdemeTest`'te bir güvenlik testi
  `actingAs($musteri, 'customer')` (TOKEN) kullanıyordu ama ölçtüğü şey
  bir SAYFA rotasıydı — yıllardır yanlış şeyi ölçüyormuş, düzeltme onu
  ortaya çıkardı. Kural: kimliğin **hangi guard'dan** çözüldüğünü ölçen
  testte `actingAs` KULLANILMAZ, gerçek giriş isteği atılır.
  (`postJson`'ın `Accept` eklemesi ve `getJson`'ın çerezi düşürmesiyle
  aynı aile: **test yardımcısı ölçmek istediğin şeyi ortadan kaldırıyor**.)

- **`ConvertEmptyStringsToNull` BOŞ METNİ NULL YAPAR — `string` kuralı
  null'da DÜŞER.** 4.5I.1'de ısırdı: ödeme formunda gizli alanlar boş
  gönderiliyor (**gizlemek göndermemek değildir**), middleware onları
  null'a çeviriyor ve müşteri *"shipping.full_name metin olmalıdır"*
  uyarısıyla ödemeye hiç gidemiyordu. Koşullu zorunlulukta sıra:
  `['nullable', 'required_without:...', 'string']`. ⚠️ `nullable`
  zorunluluğu GEVŞETMEZ (`required_*` örtük kurallar, null'da da koşar)
  ama bu **ölçülmeli**. ⚠️ Anahtarı HİÇ göndermeyen test bunu göremez —
  middleware'in dönüştüreceği değer olmuyor; testin gövdesi **tarayıcının
  gönderdiğiyle birebir** olmalı.

- **INERTIA AYNI BİLEŞENE GİDERKEN ÖRNEĞİ YENİDEN KURMAZ — `setup()` bir
  daha koşmaz.** Oluşturma ve düzenleme aynı bileşense (`Urunler/Form`),
  setup'ta hesaplanan düz değişken (`const yeniMi = props.urun === null`)
  yönlendirmeden sonra **eski değerinde donar**. 4.5L'de ısırdı: ürün
  oluşturuluyor, yönlendirme doğru, prop'lar doğru geliyor ama varyant ve
  görsel bölümü **hiç görünmüyordu**; sayfa değiştirip geri gelince
  düzeliyordu. ⚠️ Sunucu tarafında ölçüm bunu GÖREMEZ — 4.5G'de
  "yönlendirme çalışıyor" diye kapatılmıştı, ölçülen şey ekran değildi.
  Prop'tan türeyen her şey `computed`; `useForm` başlangıç değerleri de
  `watch` ile yeniden tohumlanmalı, yoksa kutularda **eski kaydın verisi**
  kalır ve kaydedilir.
- **VERİTABANI KISITI TEK BAŞINA ARAYÜZ DEĞİLDİR.** `(product_id, options)`
  benzersizliği doğruydu ama yakalanmayınca panelde ham **500**
  (*"duplicate key value violates unique constraint"*) görünüyordu. 4.5L'de
  ısırdı ve en kötü yerinden: eksen tanımlama ekranı olmadığı için her
  varyantın `options` alanı `[]` oluyordu, yani **her ürünün ikinci
  varyantı** bu hataya düşüyordu. Kural: kısıtı kaldırma (yarış durumuna
  karşı son savunma), Domain'e **aynı adı taşıyan bir kontrol** koy ve
  panelde `CatalogRuleException`'ı **oturum hatasına** çevir — genel
  işleyici JSON döndürüyor ve o yalnızca `api/*` için doğru.

- **KİMLİĞİ OKUMAK İLE VERİYİ ÇÖZMEK AYRI ŞEYLER — ikisi de tek kapıdan
  geçmeli.** 4B'de "sepet kimliğini yalnızca `CartToken` okur" kuralı
  kondu ve ölçüldü; ama sepeti **çözen** yol serbest kaldı.
  `StorefrontViewData` (üst bardaki rozet) doğrudan `misafirSepetiBul()`
  çağırıyordu, sayfa ise `CartResolver` kullanıyordu. 4.5J'de ısırdı ve
  **iki yönü de sessizdi**: bayat misafir çerezi varken rozet dolu / sepet
  boş; giriş yapmış müşterinin dolu sepetinde rozet **hiç çıkmıyor**.
  ⚠️ Yapısal testi yazarken kapsamı **dar** tut: ilk hâli girişteki meşru
  birleştirmeyi (misafir token'ını bilerek okur) ve **kendi yorum
  metnini** ihlal sayıyordu — eşleşme çağrının kendisinde olmalı
  (`->metot(`), ham metinde değil.

- **SUNUCUDA RENDER EDİLEN YÜZEY SAATİ KENDİ ÇEVİRMELİ.** `app.timezone`
  UTC (ve öyle KALMALI); Blade `format()` onu olduğu gibi basıyor, yani
  vitrin müşteriye **üç saat geride** saat gösteriyordu. Panel Inertia
  olduğu için tesadüfen doğruydu (`new Date(iso).toLocaleString()`
  tarayıcıda çeviriyor) ve iki yüzeyin farkı "sipariş panele yanlış
  saatle düşmüş" gibi göründü (4.5M). ⚠️ Çözüm `config/app.php`'yi
  değiştirmek **DEĞİL**: `now()` sorguya ofissiz metin bağlanıyor ve
  rezervasyon süreleri kayıyor — kırma denemesiyle ölçüldü, `ZamanDilimiTest`
  düştü. Doğrusu **gösterim** saat dilimi ayarı + `setTimezone()`; değer
  beyaz listeden geçmeli, yoksa geçersiz ayar sayfayı 500'e düşürür.

- **İSTİSNA İŞLEYİCİSİ İÇİNDE `route()` ÇAĞIRMAK İŞLEYİCİYİ PATLATABİLİR.**
  Genel işleyiciler merkez bağlamında da koşuyor ve orada vitrin rotaları
  **tanımlı değil**; `route('vitrin.sepet')` doğrudan çağrılsaydı hatayı
  işlemeye çalışan kodun kendisi `RouteNotFoundException` fırlatırdı
  (4.5O). `Route::has()` ile koru, yoksa JSON dalına düş.
- **"TARAYICIYA HTML, API'YE JSON" AYRIMINI BİR UÇTA DÜZELTMEK YETMİYOR —
  AİLENİN TAMAMINI DÜZELT.** Aynı hata dört kez çıktı: 4A kapalı mağaza ·
  4B ödeme dönüşü · 4.5G ödeme başlatma · 4.5O sepet/stok istisnaları.
  Her seferinde "bu uç gözden kaçmıştı" denildi. Bir istisna için
  `expectsJson()` dalı yazarken **aynı ekrandan tetiklenebilecek diğer
  istisnaları da** aynı anda tara.

- **ÇERÇEVEDEN ÇIKIŞ BETİĞİ, İÇİNDE BULUNDUĞU ADRESE GERİ GİDEMEZ.**
  Sağlayıcı dönüşü `POST` ve referans **gövdede**; `window.top.location =
  window.location.href` üst pencereyi **referanssız bir GET**'e götürüyor
  ve müşteri, ödemesi başarılı olmasına rağmen **404** görüyor (4.5R).
  Doğrusu POST'u **303 ile GET'lenebilir bir sonuç adresine**
  yönlendirmek; o adres imzalı olmalı, yoksa uuid'i ele geçiren başkasının
  ödeme durumunu okur. ⚠️ **Sahte sağlayıcı bunu İKİ KEZ gizledi**
  (1E.7.3 · 4.5R): referansı adres çubuğuna koyduğu için testler `?ref=`
  ile koşuyor ve betik çalışıyordu. Dönüş akışını sınayan test
  **sağlayıcının gerçek şekliyle** (POST + gövde) da koşmalı.

- **AYNI ADRESLİ İKİ ROTADA SON KAYIT KAZANIR — kırma denemesi bunu
  bilmezse yanlış yeri kırar.** 4.6S'de görüntüleme grubuna ikinci bir
  `/urunler/yeni` eklendi ve test **geçmeye devam etti**: yazma grubundaki
  aynı adresli rota onu eziyordu. Deneme, rotayı eski grubundan
  **silecek** biçimde kurulunca düştü. ⚠️ Ayrıca desen çakışması:
  `/urunler/yeni` ile `/urunler/{urun:uuid}` aynı gruptayken sıra sayesinde
  **tesadüfen** çalışıyordu; gruplar bölününce form 403 yerine **404**
  vermeye başladı. `whereUuid` ile sıraya bağımlılık kaldırıldı.

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
