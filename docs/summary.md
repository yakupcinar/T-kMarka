# TıkMarka — Özet

> Tek bakışta proje. Ayrıntı: `PLAN.md` · `pre-setup.md` · `domain-model.md`

## Ne

Tek markanın kendi müşterisine sattığı e-ticaret (D2C). Çok kiracılı SaaS —
**aynı kod N markaya hizmet eder**, her marka kendi alan adında.

## Servisler

```
tarayıcı
   │ https
   ▼
 caddy ────────▶ app (php-fpm 8.4) ──┬──▶ postgres 17   tek db, N şema
 TLS · public/   Laravel 12          ├──▶ redis         cache + kuyruk
                                     └──▶ mailpit       yerel mail
                 worker ─────────────┘
                 queue:work · aynı imaj
```

## Akış

```
istek → public/index.php → middleware: KİRACI ÇÖZ (host → şema)
      → route → controller (ince) → app/Domain (iş mantığı) → model → db
      → cevap
```

## Kod katmanları

```
app/Platform   merkez db: kiracı, alan adı, abonelik
app/Tenancy    kiracılık KOMUTLARI (kiracılık 5 yere yayılı, aşağı bak)
app/Domain     iş mantığı — kiracıdan HABERSİZ (ölçüldü: sıfır geçiş)
app/Http       Storefront · Panel · Platform — yalnızca ÇEVİRİR
```

⚠ Kiracılığa dokunan yerler: `app/Tenancy` (komutlar) · `config/tenancy.php` ·
`routes/tenant.php` (kapı görevlisi) · `bootstrap/app.php` · `tests/Pest.php`

⚠ İş kuralı controller'a yazılmaz: HTTP dışından (artisan · kuyruk ·
tohumlayıcı) atlanabilen kontrol `app/Domain/`'e girer.

## Kararlar

```
M-1  abonelik SaaS · biz barındırıyoruz · kaynak kod teslimi yok
M-2  marka başına ayrı PostgreSQL şeması · tenant_id kolonu YOK
M-3  arayüz Faz 4'e ertelendi · iş mantığı servis katmanında kalacak
M-4  ters vekil Caddy · sebep: on-demand TLS (özel alan adı)
```

## Kurallar

```
para        numeric(12,2) + bcmath · float YASAK
sipariş     fotoğraftır — fiyat/KDV/başlık kopyalanır
ürün liste  tek kapı: ProductQuery (forStorefront / forPanel)
kiracılık   5 tuzak: kuyruk · cache · dosya · zamanlanmış iş · search_path
test        arayüz yok → testler gözümüz
```

## Fazlar

```
0 temel + kiracılık   1 çekirdek mağaza   2 olgunlaşma
3 satılabilirlik      4 arayüz            5 entegrasyon   6 dağıtım
```

---

## Yapılanlar

```
0.1  ✅  git · Laravel 12 · PHP 8.4 sabitlendi · .gitignore · .env.example
0.2  ✅  docker: caddy app worker postgres(citext) redis mailpit
         caddy→app→PHP 200 ✓ · worker kuyruğu tüketiyor ✓ · storage ortak ✓
         host portu 5433 (5432 doluydu)
0.3  ✅  Pint (biçim) + Larastan (analiz) · komutlar: lint · analyse · test
         Larastan SEVİYE 8 — plan 5 diyordu, kod boşken 8 bedava
         seviye 8 = null erişimini yakalar ($user->name, $user null olabilir)
         sail kaldırıldı · anlamsız ExampleTest silindi
         üçü de yeşil: lint 25 dosya · analyse 0 hata · test 1 geçti
0.4  ✅  Pest kuruldu · test db AYRI: tikmarka_test
         testler PostgreSQL'de — SQLite'ta şema/citext/jsonb/FOR UPDATE yok
         RefreshDatabase: her test transaction + rollback → izole
         TUZAK: docker env_file → $_SERVER → phpunit ezemiyor
                → app/worker'dan env_file kaldırıldı, Laravel .env'i dosyadan okuyor
         5 test yeşil
0.4b ✅  ALIŞTIRMA — Note: migration + model + test, sonra silindi
         migration = yapı (DDL) · model/Eloquent = veri (DML, ORM budur)
         konvansiyon: Note sınıfı → notes tablosu, kayıt gerekmiyor
         migrate --pretend → SQL'i çalıştırmadan gösterir
         test bilerek kırmızıya düşürüldü — 4 testten 1'i kırıldı
         BULGU: timestamps() → timestamptz DEĞİL, 1A.1'e uyarı yazıldı
0.5  ✅  kiracılık zemini — stancl/tenancy 3.10, ŞEMA bazlı
         landlord/ + tenant/ migration ayrımı · kök bilerek boş
         kapı görevlisi: host → domains → search_path (routes/tenant.php)
         BEŞ TUZAK ölçülerek doğrulandı; belgedeki 3 tarif yanlıştı, düzeltildi
           search_path: bağlantı purge · cache: TAG (Redis şart)
           dosya: storage/tenant<id>/ · kuyruk: tenant_id iş gövdesinde
           zamanlanmış: tenants:run + scheduler servisi (ikisi de yoktu)
         GERÇEK SIZINTI: bayat worker → işler merkez klasöre yazdı, hata yok
         tenant:create komutu · Caddy domain-check ucu
         tests/Tenancy/ ayrı paket (RefreshDatabase transaction'ı şemayı bozuyor)
         20 test yeşil · kırmızı görüldü (bootstrapper kapatınca 1 test kırıldı)
0.6  ✅  CI — GitHub Actions (.github/workflows/ci.yml)
         her push + PR: lint:check · analyse · test
         postgres 17 + redis servisleri · citext elle kuruluyor
         phpunit.xml'de DB_HOST force KALDIRILDI (yerel: postgres, CI: 127.0.0.1)
         if: always() → üç kontrol de çalışır, biçim hatası testleri gizlemesin
         KIRMIZI GÖRÜLDÜ: ✅→❌→❌→✅
           Pint ✗ biçim · Larastan ✓ tip doğru · Pest ✗ mantık yanlış
           → statik analiz iş kuralı hatasını göremez, yalnızca test yakalar
0.7  ✅  README (0.4b'den önce yazılmıştı) + CI rozeti

════ FAZ 0 BİTTİ ════  iş mantığı hâlâ SIFIR

1A.1 ✅  marka şeması tabloları + modeller + factory'ler + enum
         customers  email NULL olabilir → misafir sipariş
         users      personel · is_owner emniyet kilidi
         roles + role_user + role_permissions  ilk FK'ler, pivot
         settings   anahtar-değer + jsonb · is_encrypted (ödeme anahtarları)
         addresses  DEFTER — sipariş adresi değil, siparişe KOPYALANIR
         BULGU: citext marka şemasında çalışmıyor (eklenti public'te,
                search_path görmüyor, sessizce düz metne düşüyor)
                → sınırda küçültme + CHECK (email = lower(email))
         domains.domain'e de aynı CHECK eklendi (tutarlılık)
         tenant:create yarıda kalırsa artık arkasını topluyor
         DESEN: $fillable = "neyi ASLA dışarıdan almam" listesi
                Address.customer_id · User.is_owner · Role.is_system
1A.2 ✅  kimlik doğrulama — 16 test
         Sanctum · personal_access_tokens MARKA şemasında
         iki guard: customer (Customer) · staff (User)
         KANIT: müşteri token'ı staff guard'ından REDDEDİLİYOR
                (Guard.php:145 → $tokenable instanceof $model)
         uçlar: /api/{register,login,logout,me} · /panel/{login,logout,me}
                panelde KAYIT UCU YOK — personel davetle gelir
         'api' middleware grubu, 'web' değil (CSRF token istemcisini kırardı)
         yanlış parola = olmayan hesap → AYNI mesaj (hesap sayımı engeli)
         hız sınırı: giris 5/dk (e-posta+IP) · kayit 10/saat (IP)
         BULGU 1: accepts_marketing API'de null dönüyordu → refresh()
         BULGU 2: doğrulama mesajları "validation.required" görünüyordu
                  (APP_LOCALE=tr, fallback de tr, Türkçe dosya yok)
                  → lang/tr/validation.php
         TEST YAPAYLIĞI: testte guard önbelleği istekler arası sızıyor,
                  gerçek HTTP'de sorun yok (curl ile doğrulandı)
                  → guardOnbelleginiTemizle()
         EK TEST: A'nın müşterisi B'de giriş yapamıyor · A'nın token'ı B'de geçersiz

1A.3 ✅  izin sistemi ve personel yönetimi — 13 test
         Permission enum: 9 izin, kodda SABİT liste
         User::hasPermission() tek kapı · izinler rollerden, istek başına önbellek
         ⚠ SAHİP her izne otomatik sahip — olmasaydı kendi rolünden
           staff.manage'i kaldırınca markasına kilitlenirdi
         3 sistem rolü: Yönetici · Katalog · Sipariş & Destek
           "Sahip" ROL DEĞİL → users.is_owner bayrağı
           Sipariş & Destek'te İADE izni yok (depocu örneği)
           ⚠ staff.manage HİÇBİR rolde yok → pratikte yalnızca sahipte
             (personel davet = yetki yükseltmeye en yakın işlem)
         izin: middleware — Laravel'in can:/Gate'i KULLANILMADI
               (Gate varsayılan guard'a bakıyor, bizde varsayılan customer)
         /panel/staff (GET·POST·DELETE) · URL'de uuid · roller İSİMLE
         3 EMNİYET KİLİDİ: is_owner $fillable dışında · sahip çıkarılamaz ·
                           kimse kendini çıkaramaz
         çıkarılan personelin token'ları da iptal ediliyor
         tenant:create artık rol + sahip kullanıcı da kuruyor
         KIRMIZI: sahip muafiyeti kaldırılınca 6 test kırıldı

1A.4 ✅  mağaza ayarları · yasal metinler · yayın durumu — 24 test
         SettingsService: grup bazlı okuma/yazma + grup bazlı önbellek
         ⚠ şifreli ayar YAZILIR ama OKUNMAZ → panele {"is_set":true}
           (anahtarı okumaya gerek yok; düz metin dönseydi tarayıcı geçmişi,
            log, ekran görüntüsü hepsi sızdıran kanal olurdu)
         önbellek marka bilgisi TAŞIMIYOR — 0.5'in etiketli cache'i bedava

         YASAL METİNLER settings'ten ÇIKTI → sürümlü kendi tablosuna
           gerekçe: ayar "şu an geçerli değer"dir, geçmişi yok
                    yasal metnin geçmişi olmak ZORUNDA
                    15 Mart siparişi 20 Mart'ta değişen metne bağlanamaz
           legal_document_drafts    değişken, yarım kalabilir, dışarı çıkmaz
           legal_document_versions  yalnızca INSERT · yayınla = YENİ SATIR
           DEĞİŞMEZLİK VERİTABANINDA: UPDATE/DELETE/TRUNCATE tetikle yasak
           BULGU: satır tetiği TRUNCATE'i GÖRMÜYOR → ayrı tetik eklendi
           published_by FK YOK — olsaydı personel çıkarınca ON DELETE
             SET NULL satırı UPDATE etmeye çalışır, tetik çökertirdi

         YAYIN DURUMU (planda yoktu, eklendi): marka KAPALI doğuyor
           KAPALI --yayinla(denetim)--> YAYINDA --kapat()--> KAPALI
           model: "önce kapat, sonra düzenle"
             alternatifi (yayındayken tek tek engelle) alanı BOŞALTMAYI
             yasaklar ama YANLIŞ YAZMAYI yasaklayamaz
           kilit sınırı: "bu değer sözleşmenin içine giriyor mu?"
             kilitli  unvan · vergi no/dairesi · adres · telefon · eposta
             serbest  KDV (kanunla değişir) · kargo (kampanya) · vitrin adı
           taslağa YAZMAK serbest (görünmüyor) · YAYINLAMAK 409
           409 seçildi: 403 değil (yetki var), 422 değil (veri geçerli) — ZAMAN
           vitrin kapısı 503 + Retry-After (çıplak 503'ü arama motoru
             kalıcı bozukluk sayabilir) · panele TAKILMIYOR

         ★ YER TUTUCULAR YAYIN ANINDA DOLDURULUYOR
           iskelet metinler {{unvan}} {{vergi_no}} … ile doğuyor
           yayınlarken mağaza bilgilerinden dolduruluyor
           biri eksik kalırsa 422, SÜRÜM OLUŞMUYOR
           → müşteri hiçbir koşulda süslü parantez göremez
           yan fayda: metin o günkü bilgilerle DONUYOR (sipariş fotoğrafı)

         tenant:create son TODO kapandı — varsayılan KDV/kargo/misafir
         contact_email BİLEREK BOŞ (sahibin kişisel adresi sözleşmeye basılmasın)
         settings.write 1A.3'ten beri boş etiketti, ilk kez kapı bekliyor
         KIRMIZI: yer tutucu + kilit denetimi bozuldu → 4 test kırıldı

1A.5 ✅  adres defteri — /api/addresses (GET·POST·PUT·DELETE) — 10 test
         ★ DESEN: sahiplik kontrolü ayrı bir "if" DEĞİL, SORGUNUN KENDİSİ
             $musteri->addresses()->where('uuid',$u)->firstOrFail()
           yükle-sonra-kontrol olsaydı satır belleğe gelirdi ve kontrolü
           yazmayı unutan uç başkasının adresini döndürürdü, hatasız
           search_path ilkesinin aynısı: ayıklamak değil ERİŞİLEMEZ kılmak
           → 1B ürün · 1C sepet · 1D sipariş · Faz 2 iade hep bunu kullanacak

         404 seçildi (plan 403 diyordu) — 403 "var ama senin değil" demek,
           varlık bilgisi sızdırır; daraltılmış sorgunun doğal sonucu da 404

         uuid EKLENDİ (planda yoktu, UUIDv7): ardışık id ile müşteri komşu
           numaraları tarayıp mağazadaki adres SAYISINI çıkarabiliyordu
           id içeride kaldı, uuid dışarı açılan kimlik (customers/users deseni)
           migration 3 adım: nullable → PHP'de backfill → not null+unique
           (backfill PHP'de: gen_random_uuid() v4 üretir, karışık kolon olmasın)

         HATA DÜZELTİLDİ: önce örtük rota bağlaması (Address $adres) yazmıştım
           o uuid'yi TÜM tabloda arıyor → başkasının satırı belleğe geliyor
           "hiç yükleme" ilkesinin tersi; rota artık düz uuid alıyor

         customer_id $fillable dışında + ilişki üzerinden create → kütle atama yok
         yumuşak silme: sipariş adresi zaten KOPYALIYOR, defter geri gelebilir
         KIRMIZI: sahiplik daraltması kaldırıldı → 2 test kırıldı

1A.6 ✅  blok kapanışı — rol yönetimi · tohumlayıcı · doğrulama — 15 test
         ROL YÖNETİMİ /panel/roles — kapı `sahip` middleware'i, İZİN DEĞİL
           role.manage izni olsaydı sahibi kendine settings.write'lı rol
           kurup atardı → "yetki dağıtan işlem yetkiyle dağıtılmaz"
           marka kendi rolünü kurabiliyor: katı liste güvenlik değil
             AŞIRI YETKİ üretir ("sadece finans" yoksa Yönetici verilir)
           sınırlar: izinler enum'dan · is_system yazılamaz ·
             sistem rolü silinemez ama DÜZENLENEBİLİR ·
             üzerinde personel olan rol silinemez (409 + sayı)
           BULGU: yeni rolde is_system null dönüyordu — değer DB
             varsayılanından geliyor, bellekteki nesnede yok → refresh()
             (1A.2'deki accepts_marketing tuzağının aynısı)

         TOHUMLAYICI — merkez/marka AYRILDI
           Laravel'in DatabaseSeeder'ı User::factory() çağırıp MERKEZDE
             koşuyordu; users merkezde YOK. tenants:seed de aynı sınıfı
             çağırıyordu → "hangi şemadayım" belirsiz
           DatabaseSeeder (merkez, veri yok) · TenantDemoSeeder (marka)
           rol+sahip tohumda YOK — onlar tenant:create'in işi
           3 savunma: canlı reddi · bağlam yoksa hata · rol yoksa hata

         İKİ KİRACIDA DOĞRULAMA (gerçek HTTP, 6 başlık) — hepsi geçti
           A token'ı B'de 401 · aynı e-posta iki markada ayrı kişi ·
           A'nın adres uuid'si B'de 404 · katalogcu 403 sahip 200 ·
           kargo A 11.11 B 99.99 · A yayında B kapalı

         ★★ CI 20 KOŞUDUR KIRMIZIYMIŞ — 1A.2/1'den beri, fark edilmeden
            sebep: Customer.php class_attributes_separation (1 boş satır)
            DERS 1: yerel kapı yalan söyledi — lint:check yerelde PASS,
              CI'da FAIL, AYNI içerikte. Dosya tek başına denetlenince
              yerelde de FAIL, tüm projede PASS. Sebep kesinleşmedi
              (paralellik değil); Pint önbelleği tahmini, kanıt değil
            DERS 2: kural vardı, kimse bakmadı — rozet + plan kuralı
              dururken 19 commit kırmızı üstüne atıldı
              KURAL, BAKILMADIĞI SÜRECE KURAL DEĞİLDİR
            günlükler yönetici yetkisi istiyor → .github/ci-kontrol.sh
              hatayı ANOTASYONA basıyor (anotasyonlar herkese açık)

         BULGU: eski markalar varsayılanları ALMIYOR — tenant:create yeni
           markaya kuruyor ama önceden açılmışlara kimse gitmiyor
           → Faz 3'e geri-doldurma komutu maddesi

════ FAZ 1A BİTTİ · 98 test · lint · analyse · CI hepsi yeşil ════

1A'NIN BIRAKTIĞI DESENLER (sonraki bloklar kullanacak)
  $fillable = "asla dışarıdan almam" listesi          1A.1
  daraltılmış sorgu = sahiplik kontrolü               1A.5 → 1B·1C·1D
  sürümlü + değişmez kayıt (tetikle zorlanan)         1A.4 → 1E
  kayıt bir fotoğraftır (kopyala, bağlama)            1A.1·1A.4 → 1D
  yetki dağıtan işlem yetkiyle dağıtılmaz             1A.3·1A.6
  emniyeti bozup kırmızı görmeden yeşile güvenme      0.4b'den beri

1A-inceleme ✅  geriye dönük mimari gözden geçirme — ölçülerek
         TUTAN: app/Domain'de Tenancy/tenant() geçişi SIFIR (M-2.7 ayakta)
         TUTMAYAN: iş mantığı yeri tutarsızdı — roller controller'daydı
           "sistem rolü silinemez" gibi kurallar HTTP katmanında dururken
           artisan/kuyruk/tohumlayıcı onları ATLAYABİLİRDİ, hatasız
           → RoleService yazıldı; 2 yeni test HTTP'siz doğruluyor
           AddressController bilerek servissiz: oradaki tek kural ilişkinin
             kendisi ($musteri->addresses()), unutulabilir bir kontrol değil
         YENİ KURAL: HTTP dışından atlanabilen kontrol app/Domain'e girer
         test yardımcıları Pest.php'de toplandı (3 dosyadaki kopya bitti)
         CLAUDE.md "app/Tenancy = kiracılığın TAMAMI" yanlıştı — kiracılık
           5 yere yayılı (config · routes/tenant · bootstrap · Pest.php)
         ExampleTest → MerkezTest (merkez adres · /up · tanımsız alan 404)

════ TOPLAM: 102 test · lint · analyse · CI hepsi yeşil ════

SIRADAKİ: 1B katalog — 10 karar alındı (PLAN.md 1B), araştırmayla doğrulandı

  1B KARARLARI ÖZET
    her ürünün en az 1 varyantı — istisna yok (istisna = her yerde if)
    fiyat/stok VARYANTTA, KDV/metin ÜRÜNDE → ürün fiyatı TÜRETİLİR (en düşük)
    eksenler (Renk/Beden) MAĞAZA seviyesinde — Magento modeli
      ürüne ait olsaydı 200 üründe 200 ayrı "Renk", filtre çalışmaz
      Shopify bile serbest alandan tanım tablosuna geçti (2024)
    sınırlar DOĞRULAMADA: 3 eksen · 200 varyant (DB'ye koymak migration'a çevirir)
    UNIQUE(product_id, options) — jsonb anahtar sırasını normalize ediyor, ölçülecek
    kategori: parent_id + path("/1/5/12/" ID zinciri) + level
      ⚠ ltree KULLANILMIYOR — İKİNCİ CITEXT, ölçüldü: marka şemasında
        operatör bulunamadı (citext sessizdi, bu gürültülü patlıyor)
      slug zinciri değil id zinciri: slug değişince alt ağaç yeniden yazılmaz
      indeks: text_pattern_ops şart, yoksa LIKE 'x/%' tam tarama yapar
    ürün↔kategori TEK · çoklu üyelik = koleksiyon (Faz 2, manuel + KURALLI)
    satılamayan ürün vitrinde YOK, doğrudan bağlantı da 404
      "tükendi" SAKLANMAZ, türetilir (is_published sakladık çünkü KARAR;
       bu HESAP) · 1D rezervasyonu için kural TEK YERDE yazılacak
      "yakında gelecek" Faz 2'ye: bayrak değil AKIŞ (işaretle→haber ver→e-posta)
    ürün adresi DÜZ /urun/{slug} — Shopify canonical'ı da düz olana işaret ediyor
    ProductQuery TEK KAPI: cost_price ve taslak sızıntısı ikisi de sessiz olurdu

──── 1A DÜZELTMESİ (1B ölçümü sırasında bulundu) ────────────────────────
     ★ TÜRKÇE BÜYÜK İ TUZAĞI — iki ayrı hesap doğuruyordu
       mb_strtolower('İSMAIL@x') → 'i̇smail@x'  (i + AYRI birleşen nokta)
       PostgreSQL lower()        → 'ismail@x'   (düz i)
       CHECK kısıtı ikisini de "küçük harf" sayıyor, unique de yakalamıyor
       → ismail@x ile İSMAIL@x İKİ AYRI MÜŞTERİ; üstelik küçük harfle
         kayıt olan büyük yazınca "parola yanlış" alıp kilitleniyordu
       kural 10 yerde tekrarlıyordu → EmailNormalizer, tek kapı
       TESTİN YAKALADIĞI FAZLA DÜZELTME: 'ı' da ASCII'ye çevrilmişti,
         PostgreSQL 'ı'yı bırakıyor → uyum bozuluyordu. Bozuk olan TEK
         harf 'İ'. Artık bir test PHP=PostgreSQL çıktısını koruyor.
     ASCII DIŞI E-POSTA YASAKLANDI (araştırma: RFC 6531/SMTPUTF8 desteği
       alan adlarının ~%10'unda; Türkçe karakterli adrese posta teslim
       edilemiyor). İki katman: App\Rules\AsciiEmail + CHECK kısıtı.
       ⚠ SIRA: önce normalleştir (İ→i), sonra ASCII denetle — tersi olsa
         Caps Lock'la yazan kullanıcı kendi geçerli adresini reddedilmiş
         görürdü. Ölçüldü: Laravel'in 'email' kuralı bunu elemiyor.

1B.1 ✅  varyant eksenleri — options + option_values · 7 uç · 10 test
         ★ BENZERSİZLİK ANAHTARI SLUG, küçük harf DEĞİL
           'Kırmızı'→'kırmızı' ama 'KIRMIZI'→'kirmizi' → iki ayrı eksen
           Str::slug hepsini 'kirmizi'de birleştiriyor; filtre adresi de o
         slug/option_id $fillable dışında · boş slug reddediliyor ("★")
         benzersizlik değerlerde EKSEN İÇİNDE ("Standart" hem Beden hem Boy)
         swatch (renk kodu) DEĞERDE — eksen mağaza seviyesinde, bir kez yazılıyor
         product.write izni 1A.3'ten beri boştu, ilk kez kapı bekliyor
         TESTİN KENDİ ZAYIFLIĞI BULUNDU: ilk Türkçe testi 'Renk'/'RENK'
           kullanıyordu, o adda I yok → tuzağı HİÇ denemiyordu. 'İncelik'
           ile değiştirildi. Yeşil test, doğru şeyi test ettiği anlamına gelmiyor.

1B.2 ✅  kategori ağacı — parent_id + path + level · 5 uç · 11 test
         taşıma ALT AĞACIN TAMAMINI tek sorguda günceller (transaction)
           yalnızca taşınan güncellenseydi torunlar eski yolu gösterir,
           "Erkek'in altındaki her şey" onları BULAMAZDI — hatasız
         döngü engeli: hedefin path'i taşınanın path'iyle başlıyorsa reddet
         ★ BULGU: PostgreSQL'de İKİ substring var
           substring(text,int) konumdan kes · substring(text,text) REGEX
           parametre metin gidince regex seçildi, NULL döndü
           path NOT NULL olduğu için PATLADI — nullable olsaydı alt ağacın
           TAMAMI sessizce NULL olur, kategoriler ağaçtan düşerdi → ?::int
         ÖLÇÜM: text_pattern_ops iddiası doğrulandı (3000 kategori)
           text_pattern_ops → Bitmap Heap Scan 46.31
           düz btree        → Seq Scan         77.50
         alt kategorisi olan silinemez (409) · ad değişince path DEĞİŞMEZ
         ekmek kırıntısı path'ten çıkıyor, ek sorgu yok

1B.3 ✅  ürün · eksen bağlama · varyant — 11 uç · 15 test
         ★ ÖLÇÜM: jsonb mi json mu
           jsonb {"renk":"K","beden":"M"} = {"beden":"M","renk":"K"} → TRUE
           json  aynı karşılaştırma                                  → FALSE
           json seçseydik UNIQUE kısıtı sıra değişen kopyayı YAKALAMAZDI
           jsonb büyük/küçük duyarlı → varyantta DEĞER SLUG'I saklanıyor
         ★ VARYANT DOĞRULAMASI — üç hata, tek sonuç
           eksik anahtar · fazla anahtar · tanımsız değer
           üçü de müşterinin SEÇEMEYECEĞİ bir varyant üretirdi, hatasız
         ★ satinAlinabilirMi() TEK KAPI — 1D'de `stock - rezerve > 0`
           olacak ve YALNIZCA orası değişecek (aşırı satış riski)
         ürün TASLAK doğar, satışa almak varyant ister (1A.4 asimetrisi)
         KDV boşsa mağaza ayarından DOLDURULUYOR, kolon varsayılanına değil
         varyant varken eksen DEĞİŞTİRİLEMEZ (409)
         başlık değişince slug DEĞİŞMEZ · aynı başlık SONEK alıyor (tisort-2)
         üç TODO kapandı: kullanımdaki eksen/değer + içinde ürün olan kategori
           değer kontrolünün DB karşılığı YOK (jsonb içinde, FK kurulamıyor)
         TOPARLAMA: katalog istisnaları iki taban sınıfa bağlandı
           (Conflict→409, Rule→422) — 1A incelemesindeki notun uygulaması
         BULGU (3. KEZ): kolon varsayılanı modele ULAŞMIYOR
           is_active null okundu → satinAlinabilirMi() false döndü
           bu sefer refresh() değil modelde $attributes → kaynağında bitti
           CLAUDE.md'ye kural yazıldı

1B.4 ✅  ürün görselleri + kiracı DOSYA izolasyonu — 3 uç · 9 test
         ★ ÖLÇÜM: Storage::url() SESSİZCE YANLIŞ ADRES ÜRETİYOR
           disk kökü çevriliyor (storage/tenant<id>/app/public/)
           ama URL çevrilmiyor: iki markada da http://localhost/storage/...
           yanlış alan adı + merkez yol, üstelik public/storage bağı YOK
           → paketin tenant_asset() yardımcısı, izolasyon ADRES üzerinden
           bedeli: görselleri PHP sunuyor → Faz 6'da S3/Caddy kuralı
         GERÇEK HTTP: sahibi 200 · yabancı 404 · merkez 404 · ../.env 404
         tür DOSYA İÇERİĞİNDEN · ad ve uzantı İSTEMCİDEN ALINMIYOR
         putFileAs (put+get() değil: get() false dönüp BOŞ dosya yazardı)
         silme dosyayı da kaldırıyor
         İKİ TEST YAPAYLIĞI belgelendi: paket test modunda bilerek 500
           fırlatıyor (üretimde 404) · fake dosya mime'ı UZANTIDAN tahmin
           ediyor, "doğru uzantı yanlış içerik" senaryosu kurulamıyor
         BULGU: testler 158 kiracı klasörü biriktirmiş (tenant:delete
           boşluğunun test yansıması) → Pest.php'ye temizlik

1B.5 ✅  ProductQuery TEK KAPI + İLK VİTRİN UÇLARI — 9 test
         /api/products · /api/products/{slug} · /api/categories
         ★ cost_price sorguda HİÇ SEÇİLMİYOR (VITRIN_VARYANT_KOLONLARI)
           sunumda gizlemek yetmezdi: biri modeli JSON'a çevirse sızardı
         ★ taslak/arşiv listede yok, doğrudan bağlantıyla da 404
           detay AYNI forStorefront sorgusundan geçiyor; ayrı yazılsaydı
           liste ile detay farklı davranırdı
         ★ magaza-acik kapısı İLK KEZ gerçek rotada (1A.4'te yazılmıştı)
           vitrin 503 + Retry-After · PANEL kapının DIŞINDA
         ★ AYNI KURAL İKİ DİLDE — ve bir test onları bağlıyor
           satinAlinabilirMi() PHP · scopeSatinAlinabilir() SQL
           tek uygulama mümkün değil (liste sorgusu DB'de çözmek zorunda)
           4 stok/aktiflik kombinasyonunda aynı cevabı verdikleri test edildi
           1D'de ikisi birden değişecek; biri unutulursa test kırılır
         kategori filtresi ALT AĞACI kapsıyor · kırıntı path'ten
         TESTİMİN HATASI: eksensiz üründe options={} ve UNIQUE(product_id,
           options) ikinci varyantı reddediyor → kısıt "tek seçenekli üründe
           tek varyant" kuralını KENDİLİĞİNDEN zorluyormuş

1B.6 ✅  blok kapanışı — tohumlayıcıya katalog + iki kiracıda doğrulama
         tohum: kategori ağacı · 2 eksen (renk kodlu) · 9 varyantlı ürün ·
           tek varyantlı ürün · BİR TASLAK ürün (1C'de "taslak sepete
           eklenebiliyor mu" sınavı için) · GD ile gerçek görsel
         İKİ KİRACIDA GERÇEK HTTP — 7 başlık, hepsi geçti:
           vitrin 200 (kimlik yok) · taslak 404 · cost_price hiç geçmiyor
           from_price tükenmişi atlıyor (99.90 değil 249.90)
           kategori alt ağacı (giyim 2, tisort 1)
           görsel sahibinden 200 yabancıdan 404
           mağaza kapanınca vitrin 503+Retry-After, PANEL 200, B etkilenmiyor

════ FAZ 1B BİTTİ · 161 test · lint · analyse · CI hepsi yeşil ════

1B'NİN ÖLÇEREK ÖĞRETTİKLERİ (hiçbiri tahmin değil)
  Türkçe küçük harf   Kırmızı→kırmızı ama KIRMIZI→kirmizi
  jsonb vs json       sıra normalize ediliyor / edilmiyor
  ltree marka şeması  operatör bulunamıyor (ikinci citext, ama gürültülü)
  substring(text,?)   metin parametre → REGEX sürümü seçiliyor, NULL
  text_pattern_ops    Bitmap Heap Scan 46 · Seq Scan 77
  Storage::url()      iki markada AYNI adres

1C   ✅  sepet — misafir sepeti · birleştirme — 4 uç · 15 test
         ★ 1C-K5 ARAŞTIRMADAN ÇIKTI: birleştirmeden SONRA stok kontrolü
           Magento TOPLUYOR: setQty(mevcut + gelen) → magento2 #26981
             "guest cart assignToCustomer stok/uygunluk kontrolü YAPMADAN
              birleştiriyor" — kayıtlı hata
           WooCommerce birleştirmeyi bir ara TAMAMEN KALDIRMIŞ, topluluk
             baskısıyla geri koymuş
           BİZ: topla değil BÜYÜĞÜ AL + sonrasında stok kontrolü
           test Magento davranışını taklit edince kırılıyor
         ★ misafir kimliği X-Cart-Token BAŞLIĞI (64 karakter kripto rastgele)
           Shopify farklı: cart id = <token>?key=<secret>, iki parçalı ve
             birinci taraf ÇEREZDE; key "alıcının özel verisini koruyor"
             çünkü token adreste görünebiliyor
           bizde bölmeye gerek yok (token yalnızca başlıkta)
           çerez de seçilmedi: vitrin Faz 4'te, teknolojisi belli değil —
             çerez API'yi henüz var olmayan istemciye bağlardı
         ★ SAHİPLİK VERİTABANINDA: CHECK (customer_id IS NOT NULL)
             <> (session_token IS NOT NULL)  ← XOR
           uygulamaya bırakılsaydı ikisi de boş sepet oluşur, kime ait
             olduğu bilinemezdi
           müşteri başına tek aktif sepet: KISMİ indeks (status='active')
             düz unique olsaydı geçmiş converted sepetler çakışır,
             müşteri ikinci kez alışveriş yapamazdı
         ölü satır SİLİNMİYOR işaretleniyor · ödeme adımı ona kilitli
         stok EKLERKEN yumuşak (kırpar) · ÖDEMEDE sert
         sepette FİYAT YOK, canlı okunuyor (test: fiyat değişince toplam da)
         quantity > 0 CHECK · para bcmath (float YASAK)
         birleştirme GİRİŞ ANINDA (AuthController) — sepet ucunda olsaydı
           giriş yapıp sepete uğramayanın misafir sepeti ortada kalırdı
         BULGU: Cart::$fillable boş olunca update([...]) de kapanıyor —
           kendi kuralımızın beklenmedik ama doğru sonucu

════ TOPLAM: 176 test · lint · analyse · CI hepsi yeşil ════

1D   ✅  stok + sipariş + sevkiyat — EN ZOR BLOK

1D.1 ✅  stock (fiziksel) + committed (bağlanmış) — İKİ KOLON
         satılabilir = stock − committed; kural İKİ YERDE yazılı ve
           İKİZ TESTİ ikisini birbirine bağlıyor:
             PHP  satinAlinabilirMi()      tekil karar
             SQL  scopeSatinAlinabilir()   liste sorgusu (DB'de çözülmek
                                           zorunda, tek uygulama imkânsız)
         committed $fillable DIŞINDA — sayacı yalnızca StockService yazar

1D.2 ✅  StockService — eşzamanlılığın kalbi
         SELECT … FOR UPDATE: kilit PHP belleğinde değil satırın kendisinde,
           kaç konteyner olursa olsun hepsi aynı PostgreSQL satırında sıraya
           giriyor → dağıtık kilide (Redis/2PC) GEREK YOK
         SABİT KİLİT SIRASI (id'ye göre): iki sipariş aynı iki ürünü ters
           sırada kilitlerse deadlock; sıra sabitlenince imkânsız
         SET LOCAL lock_timeout = '3s' → 503 + Retry-After
           sonsuz bekleme tek takılan işlemle tüm mağazayı kilitlerdi
           NOWAIT ise meşgul anlarda müşteriyi boşuna reddederdi
         ★ KIRMIZI KONTROL: lockForUpdate() silindi → HİÇBİR TEST KIRILMADI
           çözüm: üretilen SQL'de "for update" arayan YAPISAL test

1D.3 ✅  OrderTotals + CheckoutService — sipariş doğuyor
         ★ BİR KURUŞ HATASI: bcdiv KESİYOR, yuvarlamıyor
           formül tutar × oran / (100 + oran), yuvarlama elle (yarım yukarı)
         vergi DÂHİL: tax_total toplama EKLENMİYOR, grand_total'ın İÇİNDE
           eklenseydi müşteriden ikinci kez KDV alınırdı
         sipariş bir FOTOĞRAF: adres ve fiyat KOPYALANIYOR, bağlanmıyor
         sipariş no TM-2026-000123 — nextval('order_number_seq'),
           marka içinde artan (şemalar ayrı olduğu için markalar çakışmaz)
         ödeme TRANSACTION'IN DIŞINDA: dış servis yavaşlarsa satırlar
           dakikalarca kilitli kalır, tüm mağaza donardı

1D.4 ✅  FulfillmentService — kısmi sevkiyat
         TEK doğrulama kuralı: bir satırın sevk toplamı sipariş adedini
           GEÇEMEZ — dağıtılsaydı biri unutulur, aynı ürün iki kez giderdi
         fulfillment_status TÜRETİLİYOR (unfulfilled/partial/fulfilled),
           elle yazılsaydı üçüncü pakette gerçekle uyuşmayan durum kalırdı
         iptal edilen paket kalemleri SİLİNMİYOR — denetim izi kalıyor,
           adetler "sevk edilmiş" sayılmıyor, satır yeniden sevk edilebilir

1D.5 ✅  zamanlanmış görevler
         rezervasyon 15 dk · her 5 dk temizlik · her gece 03:30 sayaç
           denetimi (committed == aktif rezervasyon toplamı mı?)
         ★ DENETİM ONARMIYOR — bilerek. Onarsaydı sayacı hangi kod yolunun
           bozduğu hiç görünmez, her gece sessizce örtülürdü
         ikisi de tenants:run ile sarılı + komutlar bağlam yoksa REDDEDİYOR
           sarılmasaydı merkez bağlamda "başarılı" döner, hiçbir şey yapmaz
         withoutOverlapping() = birden çok düğüm için dağıtık kilit
         ZAMANLAMANIN KENDİSİNİ koruyan test var (tenants:run öneki arıyor)

1D.6 ✅  uçlar · uçtan uca test · iki kiracıda gerçek HTTP
         vitrin POST /api/checkout · panel sipariş+sevkiyat uçları
           (order.view / order.fulfill izinleri ilk kez kapı bekliyor)
         7 yeni istisna→HTTP eşlemesi (409 zaman/durum · 422 veri ·
           503 geçici kilit)
         uçtan uca: misafir katalog → sepet → sipariş → ödeme →
           panel kısmi sevk → partial → fulfilled → kargo → teslim

         ★★ İKİ KİRACIDA GERÇEK HTTP, 232 TESTİN GÖRMEDİĞİ İKİ ÖLÜ UÇ:
            vitrin ürün detayı varyant uuid'sini DÖNDÜRMÜYOR
              ama /cart/items onu ZORUNLU istiyor
            vitrinde yasal metin ucu HİÇ YOK
              ama /checkout legal_version_id ZORUNLU istiyor
            → gerçek müşteri için sipariş vermek İMKÂNSIZDI

            NEDEN KAÇTI: testler uca gidiyordu ama uca verdiği kimliği
              MODELDEN okuyordu ($varyant->uuid). "İstemci bu değeri
              nereden bulacak?" sorusu hiç sorulmamıştı.

            KURAL: uçtan uca testte isteğe giren her kimlik bir önceki
              UÇTAN gelmeli. Modelden okunan kimlik testi yeşil tutar,
              akışı doğrulamaz.

            düzeltme: variants[].uuid vitrine açıldı (id DEĞİL — sıralı
              sayı katalog büyüklüğünü sızdırır) · GET /api/legal[/{tur}]
              eklendi (yalnız yayınlanmış sürüm, taslak çıkmaz, yoksa 404)

         iki markada da TM-2026-000001 üretildi (sıralar ayrı şemalarda)
         her panel yalnızca kendi siparişini gördü

════ TOPLAM: 233 test · lint · analyse hepsi yeşil ════

1E   ✅  ödeme

     ★ 1E'NİN ASIL ŞEKLİ: ödeme BİZİM sürecimizde değil.
       3D Secure zorunlu, müşteri ortada bizden ÇIKIYOR.
       Geri dönüşte İKİ haber geliyor:
         ① tarayıcı döndü    → sahte üretilebilir, KANIT DEĞİL
         ② webhook geldi     → imzalı, sunucudan, GERÇEK BU
       iyzico kendi belgesinde: "callback güvenilir gösterge değildir,
         kullanıcı o ekrana hiç ulaşmayabilir"

1E.1 ✅  PaymentProvider arayüzü · FakePaymentProvider · payments tablosu
         arayüzde tek adımlı tahsilEt() BİLEREK YOK — "çağır, cevabı al"
           yanılsaması üretirdi; cevap o çağrıdan dönmüyor
         payments'ta İKİ UNIQUE, iki AYRI problem:
           (provider, provider_ref)     gelen taraf: aynı webhook üç kez
           (order_id, idempotency_key)  giden taraf: çift tıklama
         sahte sağlayıcı GERÇEK akışı taklit ediyor: yönlendirme,
           HMAC-SHA256 imza, aynı bildirimi defalarca üretebilme
         ★ BULGU: hash_hmac boş anahtarla da GEÇERLİ imza üretiyor —
           doğrulama "çalışır" görünür ama hiçbir şey korumaz.
           Artık gürültülü patlıyor.
         ★ BULGU: test markaları DefaultSettings'i hiç çalıştırmıyordu,
           yani testler canlıda olmayan bir marka biçimini sınıyordu.
           Düzeltilince kargo ücreti göründü, iki test gerçeğe uydu.

1E.2 ✅  rezervasyona ödeme aşaması (1D-K3 güncellendi)
         held 15 dk (süreç bizde) → paying 60 dk (süreç dışarıda)
         gerekçe: iyzico bildirimi 15 dk arayla 3 kez tekrar ediyor,
           ikinci deneme rezervasyonun öldüğü dakikaya denk geliyordu
         süreyi TOPLUCA 60 yapmak yanlıştı: terk edilmiş sepet stoğu
           bir saat rehin tutardı
         ★ "aktif durum" listesi enum'a kondu — beş yerde kullanılıyor;
           biri unutulsa o yol sessizce hiçbir rezervasyon bulamaz,
           ödeme başarılı olur ve STOK HİÇ DÜŞMEZDİ

1E.3 ✅  ödeme başlatma ucu — POST /api/orders/{uuid}/pay
         plandan sapma: {no} değil {uuid}. Numara tahmin edilebilir
           (1D-K4) ve o karar "görüntülemek kimlik doğrulaması ister"
           varsayımına dayanıyordu; misafir siparişinde öyle bir şey yok
         ÜÇÜ DE SUNUCUDA: tutar (grand_total) · anahtar (sipariş no) ·
           dönüş adresi (markanın alan adı)
         dönüş adresi istekten alınsaydı → AÇIK YÖNLENDİRME: saldırgan
           kendi sitesini yazar, müşteri sahte "başarılı" ekranı görürdü

1E.4 ✅  webhook — siparişi ve stoğu değiştiren TEK yer
         uçta api öneki YOK, magaza-acik kapısı YOK, kimlik YOK
           kapı olsaydı: marka mağazayı kapatınca başlamış ödemelerin
           bildirimi 503 alır, para çekilmiş sipariş pending kalırdı
         ÜÇ KAPI:
           imza     401, kayıt bile açılmaz
           eşleşme  404, sağlayıcı TEKRAR DENESİN diye (200 dese aramaz)
           tekrar   200 already_processed — hata DEĞİL
         tutar imzaya RAĞMEN ayrıca karşılaştırılıyor, bccomp ile:
           '549.7' ile '549.70' aynı tutar, düz !== farklı görürdü
         plandan sapma: "kuyruğa at" yapılmadı — iş birkaç satır
           güncellemesi; kuyruk kiracı bağlamı taşıma zorunluluğu,
           görünmez hata ve "işlendi dedik ama iş düştü" riski eklerdi

1E.5 ✅  dönüş ekranı — HİÇBİR ŞEY YAZMIYOR
         SİPARİŞTEN okuyor, istekten değil: ?status=success yazan
           müşteri kendine "ödendi" ekranı gösteremiyor
         sağlayıcıya da SORMUYOR — ikinci bir doğruluk kaynağı olurdu
         pending = "bildirim HENÜZ GELMEDİ", başarısız DEĞİL
           (iyzico ilk bildirimi 10-15 sn sonra atıyor; müşteri ekrana
            3 saniyede varabilir)
         GET ve POST birlikte — iyzico dönüşü POST ile yapıyor

1E.6 ✅  stok açığı işareti · uçtan uca ödemeli akış · iki kiracı
         ★ 1E-K5 KAPATILDI: rezervasyonu ölmüş siparişe ödeme gelirse
           sipariş KABUL ediliyor ama orders.stock_shortfall işaretleniyor
           ve panel listesinde EN ÜSTTE görünüyor
           sıralama tarihe göre olsaydı yoğun günde uyarı üçüncü sayfaya
             düşer, pratikte görünmez olurdu
           Shopify'ın uyarısı zaten: sorun eksi stoğa izin vermek değil,
             HABER VERMEDEN izin vermek

         ★★ TEST GERÇEK BİR HATA BULDU: varyant SoftDeletes kullanıyor;
            marka ödemesi yolda olan bir siparişin varyantını katalogdan
            kaldırınca kilit sorgusu firstOrFail() ile patlıyordu.
            webhook 404 → sağlayıcı 3 kez dener → üçü de düşer →
            TAHSİLAT HİÇ KAYDEDİLMEZ. Para çekilmiş, sistemde iz yok.
            Kapanış yolları artık silinmiş varyantı da kilitliyor;
            rezervasyon AÇMA yolu sıkı kalıyor.
            Katalogdan kaldırmak bir VİTRİN kararı; yolda olan siparişin
            muhasebesini bozmamalı.

════ TOPLAM: 290 test · lint · analyse hepsi yeşil ════

1E.7 ✅  iyzico — GERÇEK sağlayıcı (Faz 5'ten öne çekildi)

     K7  kart verisi BİZE DEĞMİYOR — barındırılan ödeme formu
         formu iyzico çiziyor; bedeli görünüm denetimi, karşılığı
         PCI kapsamının en dar hâli
     K8  eşleşme anahtarı payments.uuid — sipariş numarası DEĞİL
         numara tahmin edilebilir + bir siparişin çok denemesi olabilir
     K9  tutar AYRI ÇAĞRIYLA soruluyor: iyzico bildiriminde tutar YOK
     K10 sandbox localhost'a webhook atamaz → ngrok tüneli
         ⚠️ tünel adresi KİRACI ALAN ADI olarak kayıtlı olmak zorunda
     K11 sağlayıcı anahtarları PANELDEN giriliyor; her sağlayıcı
         ihtiyacı olan anahtarları KENDİSİ bildiriyor, tanımsız anahtar
         422 — yoksa `iyzico_api` yazan marka hata almaz, ödeme
         "ayarlandı" görünür ve ilk gerçek müşteride patlar
     K12 ★ İMZASIZ BİLDİRİM: gövdesine güvenme, SAĞLAYICIYA SOR
         ölçüldü: iyzico X-Iyz-Signature başlığını BOŞ gönderiyor
           (imza özelliği hesapta ayrıca aktive ediliyor)
         güven modeli değişti:
           ÖNCE   mesaja güven   "imza tutuyorsa içindekine inanırım"
           ŞİMDİ  KAYNAĞA güven  "referansı al, ne olduğunu SOR"
         bildirim artık KAPI ZİLİ — gövdesindeki status'e BAKILMIYOR
         sahte bildirim işe yaramıyor: saldırganın yapabileceği tek şey
           bize ZATEN BİZDE OLAN bir referansı hatırlatmak
         ⚠️ genel gevşetme DEĞİL: QueryablePaymentProvider arayüzü,
           sağlayıcı başına beyan. Sahte sağlayıcı imzalıyor ve imzasız
           bildirimi REDDEDİYOR
         ⚠️ A+B birlikte: imza gelirse yine doğrulanıyor, bozuk imza 401

     ★★ GERÇEK SANDBOX, TAKLİDİN GİZLEDİĞİ BEŞ ŞEYİ BULDU:

        callback token'ı POST GÖVDESİNDE yolluyor (?ref= değil)
          → müşteri ödemeden sonra 404 görüyordu
          → sahte sağlayıcı adresi KENDİSİ üretiyordu, yani test kendi
            koyduğu değeri geri okuyordu
        imza başlığı BOŞ ve ESKİ ADLI (X-Iyz-Signature, belge V3 diyor)
          → hiçbir ödeme işlenemezdi (401 → tekrar → 401)
        SoftDeletes + firstOrFail: varyant katalogdan kaldırılınca
          kilit sorgusu patlıyordu → webhook 404 → sağlayıcı 3 kez
          dener → TAHSİLAT HİÇ KAYDEDİLMEZ. Para çekilmiş, iz yok.
          kapanış yolları artık silinmişi de kilitliyor; AÇMA yolu sıkı
        "çağrı hatası" ≠ "ödeme hatası": iyzico yetersiz bakiyede servis
          düzeyinde de status:failure döndürüyor, paidPrice YOK ama
          paymentStatus VAR → başarısız ödeme işlenemiyordu, bağlı stok
          60 dakika kimseye satılamıyordu
        vekil arkasında şema: Caddy trusted_proxies olmadan
          X-Forwarded-Proto'yu kendi şemasıyla eziyordu; iyzico callback
          adresinin SSL olmasını zorunlu tuttuğu için sessizce engellerdi

        ★ ORTAK DERS: TAKLİT, PROTOKOLÜN AYRINTISINI UYDURAMAZ.
          Sahte sağlayıcı gerçek AKIŞI taklit edecek kadar iyiydi (K6)
          ama biçimi uyduramazdı.

     ölçüldü: başarısız ödemede bile paidPrice DOĞRU dönüyor —
       tutara bakıp "ödendi" demek yanlış olurdu; ölçüt paymentStatus

1F   ✅  olay kaydı — beş tip, kuyruk üzerinden
         K1 olay DOMAIN'de doğar; tek istisna product_viewed (iş kuralı
            yok, saf görüntüleme → controller'da kalıyor)
         K2 misafir kimliği ŞİMDİLİK BOŞ: anon_id kolonu açık, dolmuyor
            çerez API'yi henüz seçilmemiş vitrine bağlardı (M-3)
         K3 olay kaydı İŞİ BOZMAZ; tekilleştirme YOK — tekrar bir fazla
            satır demek, parayı bozmuyor (ödemedeki UNIQUE'in aksine)
         K4 payload'da KİŞİSEL VERİ YOK — Faz 2'deki KVKK anonimleştirmesi
            bu tabloyu taramak zorunda kalmasın diye
         K5 ★ olay TRANSACTION BİTTİKTEN SONRA kuyruğa giriyor
            (afterCommit). CheckoutService siparişi transaction içinde
            oluşturuyor; olay oracıkta atılsaydı ve geri sarılsaydı
            sipariş HİÇ VAR OLMAZ ama olay Redis'e girerdi

         ★ KIRMIZI KONTROL TESTİ İKİ KEZ ÇÜRÜTTÜ:
           1. Queue::fake() — sahte kuyruk afterCommit'i ATLIYOR
           2. veritabanına bak — sync sürücüsünde iş transaction İÇİNDE
              koşup satırla birlikte geri sarılıyor, yani afterCommit
              kaldırılınca da test YEŞİL kalıyordu
           3. GERÇEK kuyruğa bak: iş Redis'e girdi mi ✓
           canlıda iş oradan alınıp AYRI süreçte koşuyor — ölçülmesi
           gereken buydu

════════════ ✅ FAZ 1 TAMAMLANDI ════════════
326 test · lint · analyse · CI hepsi yeşil

Bir müşteri gerçekten sipariş verebiliyor — gerçek bir ödeme
sağlayıcısıyla, iki kiracıda, verileri karışmadan.

FAZ 1'İN TAŞIYICI DERSİ, altı blokta da aynı çıktı:

  SESSİZ HATA, GÜRÜLTÜLÜ HATADAN TEHLİKELİDİR
    kolon varsayılanı modele ulaşmıyor (4 kez)
    citext/ltree marka şemasında sessizce düşüyor
    Storage::url() iki markada aynı adresi veriyor
    tenants:run'sız görev merkez bağlamda "başarılı" dönüyor

  TEST GEÇİYOR ≠ TEST DOĞRU ŞEYİ ÖLÇÜYOR
    1D.6  uca giden kimlik MODELDEN okunuyordu → iki ölü uç
    1E.7  sahte cevapta token yoktu → status kontrolü sınanmıyordu
    1F    Queue::fake ve sync sürücüsü afterCommit'i atlıyordu
    → kırılmanın GERÇEKTEN uygulandığını doğrula

  UNUTMAYI İMKÂNSIZ KIL
    UNIQUE kısıtı > "acaba işledim mi" kontrolü
    veritabanı tetiği > "yasal metni UPDATE etmeyi unutma"
    sabit kilit sırası > "deadlock'a dikkat et"

FAZ 2 — 32 karar plana yazıldı, hepsi araştırmayla

  sıra: 2H bildirim → 2G kvkk → 2B iade → 2A kupon → 2C arama
        → 2D koleksiyon → 2E yorum → 2F terk edilmiş ödeme

  2H  ⚠️ FAZ 1'İN GÖRÜLMEMİŞ EKSİĞİ: sipariş onay maili bile yok.
      İade bildirimi, hatırlatma, veri indirme — hepsi buna bağlı.
      mail kuyrukta gider · düşerse iş bozulmaz · şablon kodda

  2G  SİLME DEĞİL ANONİMLEŞTİRME. Magento ve WooCommerce de böyle:
      sipariş muhasebe için kalır, kişisel alanlar tanınmaz olur.
      ⚠️ ASIL İŞ orders'taki KOPYA adreslerde — sipariş bir fotoğraf,
        yalnızca customers temizlense veri siparişlerde kalırdı
      anonimleşen sipariş MİSAFİR siparişine dönüşüyor

  2B  ★ EN ZOR. İade talebi ≠ para iadesi (Magento'da da ayrı kutu).
      14 gün TESLİM gününden (mevzuat: taşıyıcıya teslim başlatmaz)
        → bizde fulfillments.delivered_at, kısmi sevkte paket paket
      satır bazlı iade · vergi yeniden hesaplanmaz, satırınki döner
      ⚠️ ÖNERİ ARAŞTIRMAYLA DEĞİŞTİ: tam caymada KARGO DA GERİ —
        mevzuat teslim masrafları dâhil tüm ödemelerin iadesini
        zorunlu tutuyor. "Kısmi iadede kargo geri verilmez" yanlıştı.
      stok otomatik geri girmez · iade çağrısı idempotanslık taşır

  2A  kargo eşiği İNDİRİMDEN SONRAKİ tutara bakar (WooCommerce de
        böyle — ama ayar yapmış, iki hata kaydı açılmış; biz de ayar)
      tek kupon · kullanım sınırı SATIR KİLİDİYLE (1D-K5 tekrarı)
      kupon kodu siparişe KOPYALANIR (fotoğraf ilkesi)

  2C  ✅ BİTTİ — PostgreSQL'in kendisi, dış servis yok
      ⚠️ pg_trgm `public`'te, marka görmüyor — citext/ltree ÜÇÜNCÜ KEZ
        (Türkçe FTS sözlüğü `pg_catalog`'ta, o görünüyor)
      similarity() DEĞİL word_similarity, fonksiyon DEĞİL `<%` operatörü
        (fonksiyon biçimi GIN indeksini kullanmıyor — plan ölçüldü)
      ★ FTS kolu SİLİNDİ, hiçbir test kırılmadı → trigram zaten buluyormuş
        → karar değişti: FTS'in işi bulmak değil SIRALAMAK (ts_rank, A/B/C)
      ★ test yeşil, gerçek marka BOŞ: SKU'lar search_text'i uzatıyordu,
        9 varyantlı ürün skoru 0,33→0,286, yani VARYANT SAYISI YÜZÜNDEN
        aranamaz oldu → SKU tam-token eşleşmesine (FTS) taşındı
      eşik 0,3 ölçülerek: cuzdn 0,67 · gomlek 1,00 (gürültüsü 0,286)
        ⚠️ sınır dürüst: "tsiort" 0,286 → BULUNMUYOR, test bunu da ölçüyor
      `tenants:run "search:reindex"` — kolon sonradan eklendi, eski
        ürünlerin alanı boştu ve bu hata VERMİYORDU

  2D  ✅ BİTTİ — kural SORGU ANINDA, üyelik hiçbir yere yazılmıyor
      gerçek veride kanıtlandı: fiyat değişti, koleksiyona DOKUNULMADI,
        liste kendiliğinden güncellendi (1 ürün → 0 → başka ürün)
      kural şeması KAPALI LİSTE: brand · title · category · price
        ⚠️ açık olsaydı {"field":"cost_price"} maliyeti sızdırırdı
        ⚠️ bilinmeyen alan SESSİZCE ATLANMIYOR — atlansaydı koleksiyon
          fazla ürün gösterir, kimse fark etmezdi
      boş kural YASAK = tüm katalog demek olurdu
      kayıtlı kural çalıştırılmadan önce TEKRAR doğrulanıyor
        (elle/seed/eski sürümle bozuk kural girmiş olabilir)
      manuel ↔ kurallı KARIŞMIYOR: kurallıya elle eklenemez (422),
        manuele dönerken kural silinir
      sınıf adı ProductCollection — Laravel'in Collection'ıyla çakışmasın
      ★ beş kırma denemesi, beşi de doğru testi düşürdü (2C'nin dersi)

  2E  ✅ BİTTİ — satın alan yazar · onay bekler · sayaç GECE DENETLENİR
      "satın aldı" DEĞİL "TESLİM ALDI" — ödeme yetmez, kargodaki ürün
        hakkında yorum deneyim değil BEKLENTİ olurdu
        teslim tespiti WithdrawalWindow'dan, kopya yazılmadı (1D.4 inceliği)
      iade edilmiş sipariş SAYILIYOR — memnun olmayan susturulmasın
      misafir yazamaz: kimlik yok, bu bir SINIR, gizlenmiyor
      ürün başına TEK yorum, SİLİNMİŞİ de sayılarak
        (sayılmasaydı sil-yaz ile kota sonsuz, kısıt 500 verirdi)
      puan aralığı VERİTABANINDA da kısıtlı (CHECK 1..5)
      vitrinde ad kısaltılıyor "Ahmet Y." · moderation_note hiç yok
      sayaç artırma DEĞİL yeniden hesaplama · onayda VE reddetmede
      ⚠️ IS DISTINCT FROM, <> değil — null<>null null döner, yorumsuz
        üründeki bozukluk sessizce denetimden kaçardı
      ★ kırma denemesi bir testin YALANINI ortaya çıkardı: "onaysız
        ortalamaya girmiyor" testi aslında hiçbir şey ölçmüyordu
      ★★ 2E'nin EN BÜYÜK bulgusu 2E'yle ilgili değil: HER CEVAP JSON
        DEĞİLMİŞ. Accept başlığı olmayan istemci korumalı uçta 500
        alıyordu (Laravel login rotasına yönlendiriyor, arayüz yok).
        425 testin hiçbiri yakalamadı — hepsi postJson kullanıyor,
        başlığı otomatik ekliyor. Gerçek curl koşusu ortaya çıkardı.
        shouldRenderJsonWhen ve istisna eşlemesi denendi, İKİSİ DE
        çözmedi → app/Http/Middleware/ForceJson.php
        test: tests/Tenancy/JsonCevapTest.php (postJson KULLANMIYOR)
  2F  ✅ BİTTİ — sepet değil "terk edilmiş ÖDEME"
      pending sipariş daha güçlü sinyal: e-posta zaten dolu (1D)
      pencere: 60 dk (rezervasyon dolsun) … 72 saat (üst sınır)
      ★★ ÜST SINIR EN ÖNEMLİ KORUMA: kolon sonradan eklendi, geçmişteki
        TÜM pending siparişler "hatırlatılmamış" görünüyor. Sınır
        olmasaydı İLK KOŞU aylar öncesine kadar herkese mail atardı —
        2C'de aynı sınıf hata sessiz EKSİKLİKTİ, burada sessiz SALDIRI
      mail STOK SÖZÜ VERMİYOR — rezervasyon zaten düşmüş (1E-K5)
      failed'a gitmiyor: o PaymentFailedMail aldı, çelişkili iki mail
      işaretleme gönderimden ÖNCE + koşullu güncelleme (1D-K5 tekrarı)
      ⚠️ 2F-K2 GERÇEKLE ÇELİŞTİ, plan düzeltildi: olay tüketimi burada
        zorlama olurdu, her şey zaten orders tablosunda
      ⚠️ ölü savunma bulundu: whereNotNull('email') — kolon zaten NOT
        NULL, test null yazmayı deneyince veritabanı reddetti
      ★ kırma denemesi ÜÇÜNCÜ kez bir testin yalanını ortaya çıkardı

════════════ ✅ FAZ 2 TAMAMLANDI ════════════
440 test · lint · analyse · CI hepsi yeşil     (Faz 1 sonu: 326)

Mağaza artık yalnızca satmıyor:
  konuşuyor (mail) · yanlış giderse geri veriyor (iade) ·
  bulunabiliyor (arama) · kendini düzenliyor (koleksiyon) ·
  güven üretiyor (yorum) · kaçanı geri çağırıyor (hatırlatma) ·
  ve müşterinin verisini silmeden unutabiliyor (KVKK)

FAZ 2'NİN TAŞIYICI DERSİ — Faz 1'inkinin ÜSTÜNE:

  ★ KIRMA DENEMESİ ARTIK BİR YÖNTEM
    Faz 1'de tesadüfen fark ediliyordu; Faz 2'de her blokta
    sistematik yapıldı ve ÜÇ KEZ testin yalanını ortaya çıkardı:
      2C  FTS kolu SİLİNDİ → hiçbir test kırılmadı
          (trigram zaten buluyormuş → FTS'in rolü SIRALAMA
           olarak yeniden tanımlandı; tasarımı ölçüm değiştirdi)
      2E  onaysız yorum sayaç testi — sayaç zaten 0'dı,
          test hiçbir şey ölçmüyordu
      2F  yarış testi — bekleyenler() zaten işaretlileri eliyor,
          koşullu güncelleme hiç sınanmıyordu
    → yeşil testi de kırmayı dene; kırılmıyorsa test yalan söylüyor

  ★ GERÇEK HTTP, TESTİN GÖRMEDİĞİNİ GÖSTERDİ — İKİ KEZ
    2C  "tsiort" testte yeşil, GERÇEK markada 0 sonuç
        (test verisinde 1 varyant, gerçekte 9 → metin uzadı,
         skor 0,33'ten 0,286'ya düştü, ürün aranamaz oldu)
    2E  Accept başlığı OLMAYAN istemci HER korumalı uçta 500
        425 testin hiçbiri yakalamadı — postJson başlığı
        otomatik ekliyor, gerçek curl ortaya çıkardı
    → iki kiracıda gerçek koşu, süitin yerine geçmez ama
      süitin göremediği yeri gösterir

  ★ SONRADAN EKLENEN KOLON İKİ KEZ ISIRDI
    2C  geriye dönük doldurma unutuldu → arama hiçbir eski ürünü
        bulmuyordu                              sessiz EKSİKLİK
    2F  geçmişteki TÜM pending siparişler "hatırlatılmamış"
        görünüyor → üst sınır konmasaydı ilk koşu aylar
        öncesine kadar herkese mail atardı       sessiz SALDIRI
    → türetilmiş kolon eklendiğinde iki soru: kim dolduracak,
      ve boş hâli ne yapar

  ★ PLAN GERÇEKLE ÇELİŞTİ, PLAN GÜNCELLENDİ — ÜÇ KEZ
    2B    kargo iadesi: araştırma BENİM ÖNERİMİ yanlışladı
          (tam caymada teslim masrafları da geri veriliyor)
    2C    FTS'in rolü: bulmak DEĞİL sıralamak
    2F-K2 "olayları ilk tüketen iş" — tüketmedi, gerekmiyordu

  ★ MATERYALLEŞTİRİLMİŞ SAYACIN BEDELİ DENETİM — ÜÇ OLDU
    committed (1D) · used_count (2A) · rating_avg (2E)
    üçü de gecelik denetleniyor, ÜÇÜ DE ONARMIYOR:
    kendiliğinden düzeltilseydi sayacı bozan kod yolu hiç görünmezdi

  ★ ÖLÜ SAVUNMA DA BİR HATA
    2F  whereNotNull('email') — kolon zaten NOT NULL, test null
        yazmayı deneyince veritabanı reddetti. Savunma hiçbir şey
        yapmıyormuş; kaldırıldı, yerine gerçek risk (boş metin) kondu

FAZ 2'DE TEKRARLAYAN ESKİ TUZAKLAR (Faz 1 dersleri hâlâ geçerli)

  uzantı public'te, marka görmüyor    citext · ltree · pg_trgm  (3.)
  Türkçe küçük harf tuzağı           e-posta · kupon · arama    (3.)
  kolon varsayılanı modele ulaşmaz   koleksiyon · yorum         (5.)
  yarışı kontrol değil KİLİT çözer   kupon · hatırlatma         (3.)
  yerel yeşil ≠ CI yeşil             pg_trgm CI'a eklenmemişti  (2.)

FAZ 3 SIRADA — satılabilirlik
  kontrol düzlemi · abonelik ve planlar · marka açma akışının tamamı
  gerçek on-demand TLS
  devredilenler: tenants:backfill komutu · sahip varsayılan parolası

FAZ 3 AÇIK — 9 karar plana yazıldı, hepsi araştırmayla
  (iyzico · ikas · Shopify · Let's Encrypt · KVKK/TTK)

  sıra: 3A backfill → 3B merkez tablo → 3C kontrol düzlemi
        → 3D marka açma → 3E abonelik → 3F kota
        → 3G yaşam döngüsü → 3H özel alan adı

  3-K1  SINIR KAPASİTEYE: ürün + personel + özellik
        ⚠️ ARAŞTIRMA ÖNERİMİ ELEDİ — "aylık sipariş" düşünülmüştü;
          ikas da Shopify da kullanmıyor. Sipariş sınırı markanın EN
          İYİ GÜNÜNDE sistemi ona kapatır.

  3-K2  abonelik iyzico'nun kendi sistemiyle
        ürün → ödeme planı → abonelik · her ödemede webhook
        ⚠️ kart bizim sistemimize HİÇ girmiyor

  3-K3  deneme BİZDE (14 gün, kartsız), abonelik SONRA
        ⚠️ teknik kısıt: iyzico'da abonelik başlatmak kart istiyor,
          tutar 0 olsa bile → kartsız deneme orada yapılamıyor
        ⚠️ sonradan değiştirmesi pahalı

  3-K4  başarısız ödeme KADEMELİ:
        0-7 gün her şey açık → 7-14 panel salt-okunur → 14+ askı
        ★ VİTRİN AÇIK KALIYOR — Shopify'dan bilinçli ayrılma:
          vitrini kapatmak markayı değil MÜŞTERİLERİNİ vuruyor
          (siparişini takip edemeyen, iade açamayan insan)

  3-K5  WILDCARD YOK — 50/hafta yeterli
        bedeli ölçüldü: DNS API anahtarı sunucuda · anahtar çalınırsa
          TÜM markalar · yenileme bozulursa hepsi birden
        ⚠️ ŞART: tavan SESSİZ olmayacak — sayaç + 50'de açmayı reddet
          (kırık marka üretmektense açıkça hayır)

  3-K6  özel alan adı VAR, Faz 3'ün sonunda
        DNS'i MARKA ekler, BİZ kontrol ederiz
        ⚠️ "URL'de görünen değişsin, aslı aynı kalsın" YAPILAMAZ —
          tarayıcının en temel sözü; iframe SEO'yu ve 3DS'i öldürüyor
        ⚠️ asıl sebep görüntü değil: Google bakıyor → taşınabilirlik
        ✓ ask ucu + domains tablosu 0.5'te zaten yazılmış

  3-K7  kapanan marka: 1 YIL dokunulmadan, sonra silinir
        + kapanışta VERİ İNDİRME (2G'nin dışa aktarması yerine oturdu)
        ⚠️ yasal iki kural: sipariş/fatura TTK+VUK 10 YIL saklanmalı,
          AMA yükümlülük MARKANIN (veri sorumlusu), bizim değil
          (veri işleyen) — sözleşme bitince işleyen SİLMELİ
          KVKK Kurulu 2021/1258'de tam bu durumda ceza var
        ⚠️ şartı: 1 yıl süresi SÖZLEŞMEDE AÇIKÇA yazılı olacak

  3-K8  platform yöneticisi ÜÇÜNCÜ GUARD (customer · staff · platform)

  3-K9  merkez tablo düzeltilecek — kendi kuralımızı ihlal ediyor:
        tenants.created_at timestamp WITHOUT time zone (CLAUDE.md 2. kural)
        tenants.data json (jsonb değil)
        ⚠️ abonelik alanları data json'a KONMAYACAK, gerçek kolon:
          "denemesi bugün biten markalar" sorgusu yazılamazdı

  3A  ✅ BİTTİ — eksik varsayılanları tamamlama (Faz 1'den devredilen borç)
      tenants:run marka:eksikleri-tamamla [--option="kuru=1"]
      ★ NAİF ÇÖZÜM FELAKET OLURDU: "mevcut markada DefaultSettings::kur()
        çalıştır" — o metot var olanı EZİYOR. Kırma denemesi tek satırla
        DÖRT testi düşürdü:
          is_published→false : AÇIK MAĞAZA KAPANIR, bütün markalarda
          fake_secret yenilenir: yoldaki bildirimlerin imzası geçersiz
          yasal taslak      : markanın yazdığı sözleşme metni silinir
          vergi/kargo       : değiştirilmiş değerler varsayılana döner
      → komut eksiği EKLER, var olana HİÇ dokunmaz
      ölçüm: iki gerçek markada shipping.threshold_after_discount eksikti
        (2A'da eklenmişti) — zararsızdı çünkü okuyan kod `?? true` yazmış,
        yani ŞANS ESERİ doğruyduk. 1E.4'te aynı boşluk fake_secret'ta
        çıkıp gerçek koşuyu durdurmuştu
      fake_secret eksikse RASTGELE üretilir (marka başına ayrı, 1E.1)
      is_published eksikse KAPALI · store.name merkez kayıttan
      kuru çalışma ayrı bayrak — geri dönüşü olmayan, TÜM markalara
        dokunan iş; önce göster sonra yap
      doğrulandı: öncesi/sonrası bit bit aynı, yalnızca eksik ayar eklendi
      ⚠️ iki düzeltme: Setting'de @property notu eksikti (casts() enum'u
        statik analize göstermiyor, 3. örnek) · tenants:run'a seçenek
        "komut --bayrak" diye geçilmiyor, --option="bayrak=1" olacak

  3B  ✅ BİTTİ — merkez tablo düzeltmesi + abonelik alanları
      timestamps→timestamptz · json→jsonb · plans tablosu
      ⚠️ ilk ikisi PAKETİN migration'ından geliyordu: marka şemalarında
        timestampsTz disiplinini uyguladık, merkez tabloyu hiç açmamışız
      ★★ EN ÖNEMLİ: KOLON EKLEMEK TEK BAŞINA İŞE YARAMIYOR
        paketin getCustomColumns() varsayılanı ['id'], geri kalan her alan
        data json'ına gidiyor. ÖLÇÜLDÜ:
          kolon name=NULL       ← boş
          data  {"name":"X"}    ← veri burada
          $tenant->name → 'X'   ← model DOĞRU okuyor (!)
        sinsi olan son satır: kod çalışıyor GİBİ görünüyor, kırılan tek
        şey SORGU — "denemesi biten markalar" hep boş döner, hata vermez
      ★ İKİNCİSİ: kopyalamak yetmiyor, data'dan SİLMEK gerek
        iki yerde duran alanda MODEL DATA'YI OKUYOR → panel adı değiştirir,
        model eskisini okumaya devam eder, hiçbir yerde hata yok
      status varsayılanı YOK (bilinçli): default('active') olsaydı durum
        vermeyi unutan her yol sessizce "ödeyen müşteri" üretirdi
      test yardımcısı gerçek komutla HİZALANDI (1E.4'ün tekrarı olmasın)
      4 kırma denemesi, 4'ü de yakalandı
      ⚠️ kırma denemesi bir TEST KIRILGANLIĞI da buldu: hata veren test
        merkez tabloda kalıntı bıraktı, sonraki koşular gerçek sebepten
        değil kalıntıdan kırmızı kaldı
      yeni iki kural: getCustomColumns · jsonb `?` PDO'da yazılamaz

  3C  ✅ BİTTİ — kontrol düzlemi, ÜÇÜNCÜ kimlik alanı
      customer(marka şeması) · staff(marka şeması) · platform(MERKEZ)
      ⚠️ platform yetkisi BÜTÜN markalara uzanıyor — en tehlikeli yetki
      KAYIT UCU YOK: yalnızca `platform:kullanici` komutuyla açılıyor
      ⚠️ personal_access_tokens MERKEZ şemada da açıldı (yoktu, ölçüldü)
      durum geçişleri KAPALI LİSTE — kapatılmış marka trial'a DÖNEMEZ
        (dönebilseydi kapat-aç ile sonsuz ücretsiz kullanım)
      durum ve tarih BİRLİKTE yazılıyor; aynı duruma geçişte TAZELENMİYOR
        (tazelenseydi 1 yıllık silme sayacı hiç dolmazdı)
      askıda PANEL kapalı VİTRİN AÇIK — logout/me kapının dışında
      ★★ GERÇEK HTTP BİR HATA YAKALADI, 16 test yeşilken:
        rotalar web.php'deydi → CSRF token mismatch
        sebep: testler postJson kullanıyor, web grubu CSRF istiyor
        ⚠️ karar 1A.2'de VERİLMİŞTİ ve unutuldu → yorum yetmiyor,
          artık middleware listesini ÖLÇEN test var
      4 kırma denemesi; biri bir testin SINIRINI gösterdi: "personel
        merkeze giremiyor" testi yanlış guard'la bile yeşil kaldı
        (koruma çift katmanlı: guard + ayrı şema) → dürüstçe yazıldı
      doğrulandı (gerçek HTTPS): askıya al → panel 403, vitrin 200,
        geçersiz geçiş 409, geri açma çalışıyor

  3D  ✅ BİTTİ — self-servis marka açma
      ⚠️ PLANIN TAHMİNİ ÖLÇÜMLE YANLIŞLANDI: "şema açma uzun, kuyruğa al"
        deniyordu. ölçüldü → şema+28 migration 240ms, varsayılanlar 39ms
        → SENKRON. kuyrukta olsaydı kayıt biter, mağaza henüz olmazdı
      komut ve kayıt ucu AYNI YOLU kullanıyor (TenantProvisioning)
        ⚠️ ayrışsalardı sessiz olurdu — 1E.4'te tam bu yaşanmıştı
        yapısal test: komut kaynağında DefaultRoles/DefaultSettings YOK
      ayrılmış alt alan adları: panel/admin/api (adresimizi kaybetmeyelim)
        + www/mail/secure/odeme (oltalama zemini olmasın)
        ⚠️ adı gerçekten "Panel" olan marka REDDEDİLMİYOR, sonek alıyor
      sahip KENDİ parolasını belirliyor — 123 varsayılanı self-serviste YOK
      haftalık tavan 45 (LE sınırı 50) → 503 + Retry-After
        ⚠️ olmasaydı marka açılır, panel çalışır, SİTE AÇILMAZDI
      türkçe slug ölçüldü: Ayşe'nin Butiği → aysenin-butigi ✓
        ama Işıl ve İsil aynı slug'a düşüyor → çakışma soneki ZORUNLU
      ★ kırma denemesi BEŞİNCİ kez bir testin yalanını ortaya çıkardı:
        "yarıda kalırsa temizlenir" testi boş alan adıyla yazılmıştı,
        doğrulamada yakalanıyordu → marka HİÇ oluşmuyordu. artık 260
        karakterlik ad kullanılıyor: satır+şema oluştuktan SONRA patlıyor
      doğrulandı (gerçek HTTPS): kayıt → sahip kendi parolasıyla panele
        girdi (200), eski 123 reddedildi (422), vitrin kapalı (503)

  3E  ✅ BİTTİ — abonelik (plan · deneme · nezaket · iptal · denetim)
      ⚠️⚠️ 1E İLE KARIŞTIRILMAMALI, ZIT YÖNLER:
        1E marka → KENDİ müşterisinden tahsil · anahtar MARKA settings'de
        3E BİZ  → MARKADAN tahsil          · anahtar MERKEZDE, tek
        birleştirilseydi markanın parası bize, bizimki markaya giderdi
      trial(14g kartsız) → kart → active ⇄ past_due(7g) → suspended
      iptal SAĞLAYICIDA da yapılıyor — en pahalı sessiz hata olurdu:
        marka ayrıldığını sanarken iyzico her ay çekmeye devam ederdi
      tekrarlayan başarısızlık nezaket süresini UZATMIYOR
      bilinmeyen referansta 200 (404 olsaydı webhook zinciri kırılırdı)
      denetim: sağlayıcı ile kendi kaydımızı karşılaştırıyor (3. sayaç)
      ★★ GERÇEK HTTP İKİ HATA YAKALADI, 18 test yeşilken:
        ikinci abonelik 500 (409 olmalı) — istisna eşlenmemişti; testler
          servisi doğrudan çağırıyordu, uçtan geçmiyordu
        imzasız webhook 400 (401 olmalı) — imza anahtarı boş ve hata
          "senin gönderdiğin bozuk" diyordu, oysa sorun BİZDE
          → ayrı istisna + 500 + Log::critical
      ★ İKİ ÖLÜ SAVUNMA bulundu (2F dersinin tekrarı):
        serviste "zaten past_due ise dokunma" → kaldırıldı, asıl koruyan
          TenantLifecycle::gecir(), test oraya taşındı
        deneme denetiminde subscription_ref şartı → tutuldu ama artık
          durumu elle tutarsız kuran gerçek test var
      ⚠️ 3D'nin bir testi kırılgandı: tüm tenant_% şemalarını sayıyordu,
        tek başına yeşil tam süitte kırmızı → önce/sonra farkına çevrildi
      ⚠️ gerçek iyzico sağlayıcısı YAZILMADI — 1E deseni: sahte ile akış,
        gerçek sağlayıcı + sandbox ayrı adım

  3F  ✅ BİTTİ — plan kotaları (sınır UYGULANMAZSA plan anlamsız)
      ★ BAĞIMLILIK TERS ÇEVRİLDİ: arayüz app/Domain/Quota'da, uygulama
        app/Platform'da → M-2.7 ölçümü hâlâ SIFIR
        ⚠️ ölçüm bir kez KENDİ YORUMUMDAN kirlendi (tarama yorumları da
          sayıyor) → belge ölçümü bozmamalı
      kontrol SERVİSTE: controller'da olsaydı tohumlayıcı/artisan atlardı
      kota YENİ eklemeyi engelliyor, VAR OLANI silmiyor
        (plan düşürmek veri kaybı olmamalı)
      tanımsız özellik KAPALI (açık olsaydı eski planlar sessizce kazanırdı)
      denemede plan atanmış olsa bile DENEME sınırları geçerli
      ★★ İKİ HATA TESTLERLE ÇIKTI:
        1) "kiracı yok" ile "plan yok" AYNI null'a biniyordu → merkez
           bağlamdaki bakım komutları deneme sınırına takılıyordu
        2) DENEME_PERSONEL=1 deneme markasını felç ediyordu: marka 14 gün
           boyunca personel davetini HİÇ deneyemezdi → 3 oldu
      deneme sınırları: 100 ürün · 3 personel · tüm özellikler açık
      4 kırma denemesi, 4'ü de yakalandı
      ⚠️ test kalıntısı düzeltildi: firstOrCreate → updateOrCreate (3B'nin
        kalıntı sorununun ikincisi)
      doğrulandı (gerçek HTTPS): sınır 5'e çekildi → 402 + quota/limit

  3G  ✅ BİTTİ — yaşam döngüsünün sonu: askı → kapatma → 1 yıl → silme
      ★ HER İŞLEM GERİ ALINAMAZ → varsayılan HİÇBİR ŞEY YAPMAMAK
        komut onaysız yalnızca GÖSTERİR, --onayla ile siler
        ⚠️ 3A'da kuru çalışma ayrı bayraktı (yazma geri alınabilirdi);
          burada tersine çevrildi
      üç şart: status=closed · closed_at NOT NULL · closed_at <= sınır
      silme = şema + dosyalar + merkez kayıt, TEK yoldan
        ⚠️ iki ayrı yol olsaydı biri dosyaları unuturdu — ÖLÇÜLDÜ:
          diskte 40 klasör, 2 gerçek marka = 38 ÖKSÜZ (1A'nın borcu)
      marka silme ZAMANLANMIYOR (geri alınamaz iş gece koşmamalı);
        yalnızca öksüz dosya temizliği haftalık
      ⚠️ whereNotNull('closed_at') BUGÜN ÖLÜ (SQL: NULL<=tarih → NULL) ama
        TUTULDU — 2F/3E'den bilinçli sapma: orada senaryo imkânsızdı ya da
        başka yer koruyordu; burada senaryo mümkün, koruma dolaylı
      ★★ BU BLOK GERÇEK HASAR VERDİ:
        test --onayla ile koştu ve GELİŞTİRME ortamındaki gerçek marka
        klasörlerini sildi (3 ürün görseli), storage/framework de gitti
        ve süit çöktü. veritabanı testte ayrı ama DİSK AYRI DEĞİL
        → dosya silen servis artık KÖK PARAMETRESİ alıyor, test kendi
          geçici klasöründe çalışıyor · framework onarıldı · dosyasız
          görsel kayıtları temizlendi · kural CLAUDE.md'ye yazıldı
      4 kırma denemesi (biri ölü savunmayı ortaya çıkardı)
      ⚠️ YAPILMAYAN: marka verisinin dışa aktarılması (7. kararın parçası)

  3H  ✅ BİTTİ — özel alan adı + on-demand TLS
      akış: marka yazar → biz TALİMAT veririz (CNAME/A/TXT) → marka kendi
        DNS panelinde ekler → "kontrol et" → doğrulanınca ask ucu 200
      ★★ ASIL İŞ: ask ucunu KAPATMAK. Uç 0.5'te yazılmıştı ama
        DOĞRULANMAMIŞ alan adına da 200 diyordu — on-demand TLS o hâlde
        açılsaydı panele google.com yazan biri yüzünden ACME denenir,
        düşer ve LE kotamız yanardı (haftada 50)
        ⚠️ uç TLS el sıkışmasının KRİTİK yolunda: yalnızca veritabanı,
          DNS sorgusu YOK (yapsaydı her bağlantı ağ turu beklerdi)
      üç yoldan biri yeterli (bazı sağlayıcılar kökte CNAME'e izin vermiyor)
      belirteç alan adı başına RASTGELE · başarısız kontrol 200 döner
      merkez alan adlarımız ve ayrılmış adlar ALINAMIYOR
      son alan adı silinemiyor (marka kendini dışarıda bırakmasın)
      ★★ İKİ HATA TESTLERLE ÇIKTI:
        1) kolon eklemek yetmedi, CAST YOK → verified_at metin geliyordu
           (3B'nin getCustomColumns dersinin kardeşi) → kendi Domain modeli
        2) YENİ açılan markaların alan adı doğrulanmamış doğuyordu —
           migration mevcutları doldurdu ama İLERİYE dönük yolu düzeltmedi
      4 kırma denemesi; İKİSİ testin zayıflığını gösterdi:
        merkez kontrolü testi 'localhost' kullanıyordu (nokta yok diye
        zaten eleniyordu) · tarih tazeleme testi aynı saniyedeydi
      ⚠️ GELİŞTİRMEDE SINANAMAZ: .localhost'a LE sertifika vermiyor;
        on_demand_tls yazıldı ve Caddy yükledi ama gerçek sertifika akışı
        ancak gerçek alan adında sınanabilir — dürüstçe kaydedildi
```

════════════ ✅ FAZ 3 TAMAMLANDI ════════════
549 test · lint · analyse · CI hepsi yeşil     (Faz 2 sonu: 440 → +109)

Faz 2'de mağaza satabiliyordu ama ÜRÜNÜ BİZ AÇIYORDUK (elle
tenant:create). Faz 3'ten sonra ürün KENDİ KENDİNİ satıyor:

  ziyaretçi gelir → mağazasını kendi kurar (3D)
  14 gün kartsız dener                      (3E)
  kartını girer, aboneliği başlar           (3E)
  planının sınırına dayanır, üst plana geçer (3F)
  ödemesi düşerse KADEMELİ kısıtlanır       (3C + 3E)
  isterse kendi alan adını bağlar           (3H)
  ayrılırsa bir yıl sonra izi silinir       (3G)

FAZ 3'ÜN TAŞIYICI DERSİ — "yeşil test" yetmiyor, "yeşil kod" da yetmiyor:

  ★ KOD ÇALIŞIYOR GİBİ GÖRÜNÜR, KIRILAN ŞEY SORGUDUR
    Faz 3'ün en sinsi hatası. 3B'de ölçüldü:
      $tenant->name          → DOĞRU değeri veriyor    ✅
      kolon veritabanında    → NULL                     ⚠️
      veri nerede            → data json'ında
      where('trial_ends_at') → hiçbir şey bulmuyor, HATA DA VERMİYOR
    Okuma yolu sağlam olduğu için hiçbir belirti yok; kırılan tek
    şey SORGU — yani "denemesi bitenleri bul" sessizce boş dönerdi.
    → paketin varsayılanını (getCustomColumns = ['id']) ezmek gerekti
    ⚠️ alan iki yerde birden durursa `data` KAZANIYOR (ölçüldü)

  ★ KIRMA DENEMESİ ARTIK RUTİN — ve verimi ARTTI
    Faz 2'de 3 blokta yalan test buldu; Faz 3'te ALTI blokta:
      3B  kolon/data ayrımı                3E  iki ölü savunma
      3D  temizlik testi hiçbir şey ölçmüyordu (boş alan adı zaten
          doğrulamadan dönüyordu → 260 karakterle gerçek hata)
      3F  test artığı: firstOrCreate eski satırı buluyordu
      3G  whereNotNull ölü — ama BİLEREK bırakıldı (aşağıda)
      3H  merkez kontrolü testi aslında BİÇİM kontrolünü ölçüyordu
    → yeşil testi kırmayı dene; kırılmıyorsa test yalan söylüyor

  ★ TESTİN KENDİSİ GERÇEK HASAR VERDİ — YENİ DERS SINIFI
    3G'de test `--onayla` ile komutu çalıştırdı ve GELİŞTİRME
    ortamındaki gerçek marka klasörlerini SİLDİ (3 ürün görseli) —
    üstelik storage/framework de gitti, süit "valid cache path" ile
    tamamen çöktü. Sebep: test ile uygulama AYNI storage/ klasörünü
    paylaşıyor; RefreshDatabase veritabanını izole eder, DİSKİ ETMEZ.
    → geri alınamaz işlem yapan koda kök dizin PARAMETRE olarak girer

  ★ ÖLÜ SAVUNMANIN İSTİSNASI YAZILDI
    2F'de ölü savunma kaldırılmıştı; 3G'de ölçüldü, ölü çıktı ve
    yine de BIRAKILDI. Fark gerekçeye yazıldı:
      2F  kolon NOT NULL     → senaryo İMKÂNSIZ        → kaldır
      3E  başka yer koruyor  → gerçek koruma orada     → kaldır
      3G  senaryo MÜMKÜN, koruma DOLAYLI (SQL'in NULL
          semantiğine bağlı) ve işlem GERİ ALINAMAZ    → BIRAK
    → "ölü savunma kaldırılır" mutlak değil; ölçüt senaryonun
      mümkün olup olmadığı ve hatanın geri alınabilirliği

  ★ `null` İKİ FARKLI ŞEY DEMEK OLABİLİR
    3F'de kota kontrolü "kiracı yok" ile "planı yok" durumlarının
    ikisini de null görüyordu → merkez bakım komutları deneme
    sınırına takılıyordu. kotaDisi() ile ayrıldı.

  ★ GERÇEK HTTP YİNE SÜİTİN GÖRMEDİĞİNİ GÖSTERDİ
    3C  merkez rotalar web.php'deydi → CSRF; BÜTÜN testler yeşildi
        çünkü postJson kullanıyorlar. Gerçek curl "token mismatch".
        ⚠️ karar 1A.2'de ZATEN VERİLMİŞTİ ve unutuldu
        → yorum yetmiyor; middleware listesine bakan test yazıldı
    3E  ikinci abonelik 500 (istisna eşlenmemiş) → 409
        imzasız webhook 401 yerine 400 (gizli anahtar boş)

  ★ PLAN GERÇEKLE ÇELİŞTİ, PLAN GÜNCELLENDİ
    3F  DENEME_PERSONEL 1 → 3: sınır 1 iken IzinTest kırıldı, yani
        deneme markası personel davetini HİÇ deneyemiyordu — satın
        almaya ikna edecek özelliği göremezdi
    3H  verified_at cast'ı: paketin Domain modeli bizim kolonumuzu
        bilmiyor → kendi modelimiz yazıldı

FAZ 3'TE TEKRARLAYAN ESKİ TUZAKLAR

  sonradan eklenen kolon geriye doldurulmalı  3B · 3H       (4. ve 5.)
  kırma denemesi testin yalanını gösterir     6 blokta      (9.…14.)
  gerçek HTTP süitin göremediğini görür       3C · 3E       (5. ve 6.)
  test artığı sonraki koşuyu kırar            3C · 3F       (2. ve 3.)

⚠️ FAZ 3 BİTİŞ ÖLÇÜTÜ — TAM KARŞILANMADI (dürüst kayıt)

  Ölçüt şöyleydi: "…ayrılırsa VERİSİNİ İNDİRİR ve bir yıl sonra
  izi silinir." İkinci yarı 3G'de yapıldı, BİRİNCİSİ YAPILMADI.
  Marka geneli veri dışa aktarma yok. 2G'deki DataExporter MÜŞTERİ
  verisi için; marka geneline genişletmek ayrı bir iş.
  → KVKK açısından bugün eksik değil (silme yükümlülüğü karşılanıyor)
    ama SÖZ VERİLEN ölçüt bu; Faz 4 planına borç olarak giriyor

DEVREDİLEN BORÇLARIN DURUMU (ölçüldü, varsayılmadı)

  ✅ tenants:backfill komutu (3A'dan)          → kapandı
  ⚠️ sahip varsayılan parolası `123`           → DARALDI, kapanmadı
       gerçek internet akışı (3D) min:8 gerçek parola istiyor;
       kalan yalnızca tenant:create artisan komutunun varsayılanı
       — geliştirme aracı, internetten erişilmiyor
  ⚠️ Caddyfile'a elle alan adı                 → ÜRETİMDE kapandı
       on_demand_tls + ask ucu çalışıyor; geliştirmede .localhost
       zorunlu olarak elle kalıyor (LE .localhost'a sertifika vermez)

AÇIK BORÇLAR — FAZ 4'E GİDİYOR

  IyzicoSubscriptionProvider   gerçek sağlayıcı + sandbox doğrulaması
                               (1E → 1E.7 deseni: önce sahte, sonra gerçek)
  marka geneli veri dışa aktarma   bitiş ölçütünün eksik parçası
  wildcard sertifika               haftalık kayıt sayacı tetikleyince
  declare(strict_types=1)          tek Pint kuralı, 0.3'ten devrediyor

FAZ 4 SIRADA — arayüz
  üç panel: müşteri (vitrin) · marka · yönetim
  ayrım rota/URL ile, kimlik ayrımı Sanctum guard'larıyla ZATEN hazır
  (customer · staff · platform — 3C'de üçüncüsü eklendi)

════════════ FAZ 4 AÇILDI — M-3 KARARI VERİLDİ ════════════

Değerlendirilen öneri: Inertia + Vue/React + Vite SSR, tek proje,
"harici API katmanına ihtiyaç duymadan". Ana akım ve sağlam — ama
üç ölçüm onu OLDUĞU GİBİ almayı engelledi.

4-K1 ✅  YIĞIN YÜZEYE GÖRE BÖLÜNDÜ, tek yığın değil
         marka alan adı  /         vitrin      Blade
                         /yonetim  panel       Inertia+Vue
         merkez alan adı /yonetim  kontrol     Inertia+Vue
         ★ "üç paneli neyle ayıracağız": ALAN ADI bizi markadan,
           YOL vitrini yönetimden ayırıyor — yeni mekanizma yok
         gerekçe: üç yüzeyin ihtiyacı ZIT (SEO · tema · etkileşim)
         Shopify · Spree · Saleor hepsi aynı yerden ayrılmış
         ⚠️ bedeli: iki yığın öğrenilecek — bilerek kabul edildi

4-K2 ⛔  SSR AÇILMIYOR — ve bu M-2.4'ün AYNISI
         Inertia SSR ayrı Node süreci (:13714), UZUN ÖMÜRLÜ ve
         TÜM MARKALAR İÇİN ORTAK. Vue'nun kendi belgesi buna
         "cross-request state pollution" diyor: modül seviyesi
         durum istekler arasında paylaşılıyor → MARKA SIZMASI
         ★ pgBouncer'ı reddetme gerekçemizin birebir aynısı:
           paylaşılan uzun ömürlü şey kiracı durumunu taşıyor
         ⚠️ "yerelde yakalayamazsın, dev sunucu tek istek işliyor"
         İKİNCİ GEREKÇE — SSR SESSİZ BOZULUYOR:
           bozuldu → sayfa çalışıyor ✅ testler yeşil ✅
                   → Google boş sayfa görüyor, SEO sessizce gitti
         SEO'dan vazgeçmiyoruz: vitrin ZATEN Blade, zaten sunucuda
         kazanç: Node yok · sızma yok · sessiz düşüş yok ·
                 Faz 6'da bir dağıtım parçası eksik ·
                 inertia-laravel#730 (çoklu örnek) hiç doğmuyor

4-K3 ✅  API KALIYOR — arayüz onu değil Domain'i çağırır
         öneri "API'ye gerek kalmaz" diyordu; bizde tersine dönüyor
         ÖLÇÜLDÜ: 119+15 rota · 36 controller · 3.932 satır ·
                  token Sanctum · 549 test bu uçlara vuruyor
         Inertia bu API'yi KULLANMAZ (prop döndürür, oturum kimliği)
         → katman kaldırmıyor, İKİNCİ sunum katmanı ekliyor
         kabul edilebilir ÇÜNKÜ iş mantığı Domain'de, controller ince
         kural: Inertia controller → Domain servisi  ✅
                Inertia controller → API controller  ❌ ASLA
         API atılamaz: mobil · marka entegrasyonları · Faz 5

4-K4 ✅  İKİ KAPI: panel OTURUM, API TOKEN — aynı yetkiler
         panel web grubunda, CSRF İSTENİYOR (3C'nin doğru tarafı)

4-K5 ⛔  TEMA = AYAR, ŞABLON DEĞİL. Marka Blade YAZAMAZ.
         ⚠️ Blade PHP'dir ve KUM HAVUZU YOKTUR — kullanıcının
           yazdığı Blade'i render etmek doğrudan RCE'dir
           (Laravel belgesi uyarıyor; Cachet #4621'de yaşandı)
         ★ bizde bedeli TEK MARKA DEĞİL: şema bazlı kiracılıkta
           sunucuda kod çalıştıran biri search_path'i değiştirip
           BÜTÜN markaların verisine ulaşır
         Shopify'ın Liquid'i tam bu yüzden kum havuzlu
         karar: marka AYAR seçer (renk·logo·yazı tipi·blok sırası)
                şablon BİZDE, sürümlü, markaya kapalı
         SettingGroup::Theme FAZ 1'DEN BERİ VAR, yorumunda "(Faz 4)"
         ileriye kapı: Liquid benzeri KUM HAVUZLU motor — Blade değil

⚠️ Diskte duran commit edilmemiş keşif (3 Blade + web.php, 120 satır)
   ATILDI: yığın kararı onu kısmen geçersiz kılıyordu ve yarım bir
   başlangıcı taşımak kararı ona uydurma baskısı yaratırdı

BLOKLAR  4A vitrin iskeleti → 4B vitrin akışı → 4C panel iskeleti
         4D katalog yönetimi (ÜRÜN EKLEME buradan görünür oluyor)
         4E sipariş ekranları → 4F kontrol düzlemi → 4G tema
         4H kapanış (iki markada gerçek tarayıcı koşusu)

BİTİŞ ÖLÇÜTÜ  marka HİÇ curl kullanmadan mağazasını kurar; müşteri
              tarayıcıdan alışveriş yapar; marka siparişi panelden
              görür — üçü de kendi yüzeyinden, kimse diğerini görmeden

4A ✅  VİTRİN İSKELETİ — 15 test (toplam 564)
      `/` artık JSON değil HTML; Blade düzeni tema ayarını okuyor

      ★ İŞİN ASLI ÜÇ ENGELİ KALDIRMAKTI — üçü de "arayüz yokken
        verilmiş, şimdi vadesi gelen" karar:
        1 ForceJson GLOBAL'di — kendi yorumu "arayüz olmadığı için
          login rotası yok" diyordu; artık arayüz VAR. Global kalsaydı
          her sayfa "JSON istiyorum" sayılır, form hataları 422 dönerdi
          → `api` grubuna DARALTILDI (kaldırılmadı: 2E hatası gerçek)
        2 sepet kimliği yalnızca X-Cart-Token BAŞLIĞINDA'ydı —
          CartController yorumu "çerez değil, çünkü M-3 seçilmedi"
          → tarayıcı düz gezinmede özel başlık GÖNDEREMEZ; çerez eklendi,
            başlık KALDI (mobil/API için) ve başlık çerezi EZİYOR
        3 `/` api grubundaydı → HTML sayfası `web` grubunda olmalı
          (oturum · çerez · ilerde CSRF) — 3C dersinin TERS tarafı

      4A-K1 ★★ TEMA AYARI DA BİR GİRİŞ KAPISI — 4-K5 tek başına yetmiyor
        "marka şablon yazamaz" kapıyı kapatıyor, ayar PENCERE:
        renk doğrudan <style> bloğuna giriyor. Marka şunu kaydetseydi
          red; } body { background: url(https://baskasi.example/x)
        sayfa markanın yazmadığı CSS'i çalıştırırdı
        → okuma yolu BEYAZ LİSTE: renk #rrggbb kalıbı, yazı tipi ve
          düzen sabit liste; uymayan VARSAYILANA düşüyor
        ⚠️ doğrulama YAZMA'da değil OKUMA'da: ayar tohumlayıcı/artisan/
          elle SQL ile de girebiliyor

      4A-K2 ★★ ÇEREZ ŞİFRELENMİYOR — sinsi bir tuzağı kapatıyor
        EncryptCookies YALNIZCA `web` grubunda çalışıyor:
          sayfa (web) → çözülmüş token → sepet BULUNUR
          uç    (api) → şifreli metin  → sepet BULUNMAZ
        hata vermezdi: müşteri sepetini sayfada görür, eklemeye
        çalışınca yeni boş sepet açılırdı

      ★★ 4 KIRMA DENEMESİ — BİRİ TESTİN YALANINI GÖSTERDİ
         renk beyaz listesi kalktı        → enjeksiyon testi düştü  ✅
         çerez dalı kalktı                → iki sepet testi düştü   ✅
         ForceJson tekrar global          → kapalı-mağaza testi düştü ✅
         şifreleme istisnası kalktı       → ⚠️ test YEŞİL KALDI
           sebep: test yalnızca `api` grubuna vuruyordu ve orada
           EncryptCookies zaten yok — istisna o yolda rol oynamıyor
           → yeniden yazıldı: TEK çerezle İKİ GRUBA birden vuruyor

      ★ TEST YARDIMCISI ÖLÇÜLECEK ŞEYİ YOK EDİYORDU (2E'nin akrabası)
        withCookie()            değeri ŞİFRELİYOR
        withUnencryptedCookie() düz gönderiyor              ✓
        getJson()               şifresiz çerezi SESSİZCE DÜŞÜRÜYOR
        → çerez testi getJson ile yazılsaydı istek çerezsiz giderdi

      ★ 3A'NIN BORCU KENDİLİĞİNDEN İŞLEDİ: tema ayarları sonradan
        eklendiği için mevcut markalarda yoktu; araç üç markada da
        4 eksiği buldu ve tamamladı. Vitrin ayarsız da çalışıyordu
        (okuma yolu varsayılana düşüyor) — backfill onları panelden
        DÜZENLENEBİLİR yapıyor

      ★ AlanAdiTest yeniden yazıldı, ÖLÇTÜĞÜ ŞEY GÜÇLENDİ: eskiden
        `/` hata ayıklama ucuydu ve tenant('id') basıyordu — test
        şemadan tek satır okumuyordu. Artık markanın kendi ayarındaki
        mağaza adı aranıyor, yani search_path gerçekten sınanıyor

      DOĞRULANDI (iki markada gerçek HTTPS): ikisi de 200 + text/html
        kendi adlarıyla · A'ya #0ea5e9 girdi · B'ye ENJEKSİYON yazıldı,
        varsayılana düştü, kotu.example sayfada YOK · sepete ekleme
        Set-Cookie (düz·httponly·lax) döndü, çerezle "Sepet 2",
        çerezsiz boş · B kapatıldı → tarayıcı HTML 503, API JSON 503
      ⚠️ YAPILMAYAN: ürün detay · sepet sayfası · ödeme akışı → 4B

4B ✅  VİTRİN AKIŞI — 12 test + 1 yapısal (toplam 577)
      ürün detay · sepet · ödeme · dönüş ekranı — hepsi Blade

      4B-K1 FORMLAR JAVASCRIPT'SİZ ÇALIŞIYOR
        her işlem <form method="post">, cevabı yönlendirme (PRG)
        ⚠️ PRG zorunlu: doğrudan HTML dönseydi sayfayı yenileyen
          müşteri aynı ürünü tekrar sepete eklerdi

      ★★ 4A'DAN KAÇAN HATA: düzeltme SINIRA değil TEK YERE yapılmıştı
        4A'da çerez desteği yalnızca CartController'a eklenmişti;
        üç yer başlığı doğrudan okumaya devam etti, SONUÇLARI SESSİZ:
          CouponController    tarayıcıdan kupon → "sepet bulunamadı"
          CheckoutController  tarayıcıdan ödeme → "sepet bulunamadı"
          AuthController      giriş → misafir sepeti BİRLEŞMİYOR
                              → müşterinin sepeti GİDİYOR
        hiçbiri hata vermiyordu, hepsi "sepetin yok" diyordu
        → CartResolver + YAPISAL TEST (SepetKimligiTest): CartToken
          dışında hiçbir dosya başlığı okuyamaz
        ⚠️ 3C dersinin aynısı: yorum korumuyor, ÖLÇEN test gerekiyor

      ★★ GERÇEK KOŞU İKİ HATA DAHA GÖSTERDİ — ikisi de ÖDEME DÖNÜŞÜNDE
        ham JSON        uç `api` grubunda, ForceJson Accept'i eziyor,
                        yazdığım HTML dalı HİÇ çalışmıyordu
        500 hatası      düzen $errors bekliyor; onu paylaşan middleware
                        yalnızca `web` grubunda
        ikisi de MÜŞTERİ ÖDEMESİNİ BİTİRDİKTEN SONRA görünüyordu
        uç `web`'e taşınamıyor: sağlayıcı POST ediyor, CSRF üretemez
        → ForceJson'a DAR istisna listesi + isset($errors) koruması

      ★ İKİ TESTİM YANLIŞ VARSAYIMLA YAZILMIŞTI — KOD HAKLI ÇIKTI
        "eski sözleşme reddedilmeli" sandım → karar REDDETMEK değil
          GÖRÜLENİ KAYDETMEK (1A.4 · 1D-K2): sipariş müşterinin
          ekranındaki sürümü taşıyor
        stok 0'da "Stok yetersiz" bekledim → 1C-K2 stok bitmesini
          "artık satın alınamaz" sayıyor; iki mesaj AYRI dallardan,
          ikisi de artık ölçülüyor

      ★ BLADE TUZAĞI: @section('ad', Str::limit($x, 150)) kısa biçimi
        virgülde kırılıyor, GÖRÜNÜM DERLENEMEZ oluyor; belirti sinsi

      DOĞRULANDI (iki markada gerçek tarayıcı akışı, curl + çerez kavanozu)
        ana sayfa → ürün → sepete ekle 302 → "Sepet 2" → ödeme sayfası
        → sipariş 302 → sandbox-cpp.iyzipay.com
        sipariş TM-2026-000015 · 699,80 TL · pending · sözleşme kayıtlı
        dönüş: tarayıcıya HTML "Ödemeniz işleniyor", API'ye JSON
        B markasında aynı akış çalıştı, A'nın sepeti B'de GÖRÜNMEDİ
      ⚠️ YAPILMAYAN: müşteri girişi/kayıt · adres defteri · sipariş
        geçmişi · yorum yazma ekranı (uçları var, sayfaları yok)

4C ✅  PANEL İSKELETİ — 12 test (toplam 588)
      ilk kez Node/Vite/Vue projeye girdi; /yonetim ayakta

      4C-K1 NODE APP İMAJINA girdi, ayrı servise değil
        kural: "yerel makinede PHP yok, her şey Makefile'da"
        Node dışarıda kalsaydı geliştiricinin makinesine bağımlılık
        imaj ~60 MB büyüdü — bilinçli takas

      4C-K2 TESTLER JS DERLEMESİNE BAĞLI DEĞİL, ama CI DERLİYOR
        withoutVite(): Inertia'nın sunucu iddiaları JS'siz ölçülüyor
        ⚠️ CI ayrı adımda derliyor — olmasaydı bozuk bir Vue bileşeni
          bütün testler yeşilken geçerdi

      4C-K3 PANEL OTURUM, API TOKEN — aynı tablo, iki kapı
        staff (token) · staff-web (session)
        ⚠️ 1A.0'daki "oturum kullanmıyoruz, panel ayrı alt alan adına
          taşınabilir" gerekçesi ARTIK GEÇERSİZ: M-3 paneli markanın
          kendi alan adında /yonetim'e koydu. Karar gerekçesiyle değişti

      4C-K4 DÜĞMEYİ GİZLEMEK YETKİ DEĞİLDİR
        izinler prop olarak gidiyor ama SADECE menüyü şekillendirmek için
        gerçek koruma sunucuda `izin:` middleware'inde

      ★★ GERÇEK TARAYICI: PANEL BOŞ SAYFA AÇILIYORDU
        asset_helper_tenancy `asset()`'i /tenancy/assets/'e çeviriyor:
          /tenancy/assets/build/...js  → 404
          /build/...js                 → 200
        ⚠️ BEDELİ TAMAMEN SESSİZ: sunucu 200, HTML doğru, Inertia verisi
          doğru, testler yeşil (withoutVite) — tarayıcı betiği indiremiyor
        kapatıldı; marka dosyaları zaten tenant_asset() kullanıyor

      ★ 2E'NİN HATASI PANEL TARAFINDA YENİDEN ÇIKTI
        kimliksiz istek `login` adlı rotaya gidiyor → rota yok → 500
        2E'de cevap "her şey JSON"dı; Faz 4'te doğru cevap GİRİŞ
        SAYFASINA YÖNLENDİRME → redirectGuestsTo, yola göre ayırıyor

      ★ ÜÇ KÜÇÜK TUZAK
        composer require errno=35'e takıldı (optimize-autoloader taraması);
          kurulum tamamlanmıştı, package:discover elle çalıştırıldı
        ReservedSubdomains.php okunamaz oldu → sil-yeniden yaz
        Vue menüsüne order.read yazmıştım, enum'da order.view — test yakaladı

      ★★ 5 KIRMA DENEMESİ, BEŞİ DE TESTLERİ DÜŞÜRDÜ
        sahip kısa devresi · model olduğu gibi paylaşımı · panele
        magaza-acik · kimliksiz yönlendirme · asset_helper_tenancy

      DOĞRULANDI (iki markada gerçek tarayıcı)
        kimliksiz /yonetim → 302 giriş · Giris bileşeni + noindex
        gerçek POST girişi → 302 pano · Panosu, "A Markası Sahibi",
        9 izin, marka adı doğru, PAROLA SIZMIYOR · panel betiği 200
        A'nın oturumu B'nin panelini AÇMIYOR
      ⚠️ YAPILMAYAN: ürün/sipariş/ayar ekranları (4D-4F). Panoda sahte
        sayaç YOK — çalışıyor gibi görünen boş pano, eksik olduğu belli
        olandan kötüdür

4D ✅  PANEL KATALOG YÖNETİMİ — 10 test (toplam 599)
      markanın ÜRÜN EKLEDİĞİ ekran; zincir panel → vitrin tamamlandı

      ★★ 4C-K4'ÜN İKİNCİ YARISI ARTIK ÖLÇÜLÜYOR
        4C'de "düğmeyi gizlemek yetki değildir" denmişti ama izin:
        korumalı bir panel SAYFASI yoktu — iddianın yarısı ölçülemiyordu
          menüde "Ürünler" gizli      ← KOLAYLIK
          /yonetim/urunler → 403      ← KORUMA
        ⚠️ sayfa ile API AYNI izni istiyor: farklı isteselerdi birinden
          kapatılan diğerinden açık kalırdı

      4D-K1 panelde forPanel(), vitrinde forStorefront()
        marka kendi TASLAĞINI görmeli — göremezse düzenleyemez
        aynı sebeple panel araması arama motorunu (2C) KULLANMIYOR:
        o vitrin sorgusundan geçiyor ve taslakları elerdi

      4D-K2 yeni ürün TASLAK doğuyor, DÜZENLEME sayfasına gidiliyor
        varyantsız ürün satılamaz; "varyant yok — satılamaz" uyarısı
        gizlenmiyor, yazılıyor

      4D-K3 varyant ÜRÜNE DARALTILMIŞ doğrulamadan geçiyor (1A.5)
        iç içe rota kapsaması BİLİNÇLİ kapatıldı (withoutScopedBindings):
        Laravel çocuğu ebeveynin ilişkisinden çözüyor (Product::varyants()
        arıyor, ilişki `variants` → 500). Pakete bıraksaydık açık kontrol
        ÖLÜ savunma olur ve kimse ölçemezdi

      ★★ KIRMA DENEMESİ YANLIŞ YERİ KIRDI — VE BU BİR DERS
        izin:product.write kalıbı İKİ yerde; replace(...,1) ilk eşleşmeyi
        (API'yi) bozdu, sayfa izni sağlam kaldı, test "geçti"
        → yani deneme "test ölçmüyor" değil "BEN YANLIŞ YERİ KIRDIM"
          diyordu; fark görülmeseydi testin sağlamlığı hakkında YANLIŞ
          GÜVEN doğardı
        KIRMA DENEMESİNİN KENDİSİ DE DOĞRULANMALI (grep ile)

      ★ GERÇEK KOŞU: PANELİN BÜTÜN SAYFALARI 500 VERİYORDU
        Inertia DevTools her isteğe storage'a dosya yazıyor, errno=35
        ⚠️ belirti yanıltıcı: hata file_put_contents'ten, yığın izinde
          sayfayı yazan kod hiç yok
        kapatıldı (config/inertia.php) — aynı dosyaya SSR'ın neden kapalı
        olduğu da yazıldı

      ★ INERTIA'DA SUNUCU CEVABI EKRANDAKİ METNİ İÇERMİYOR
        assertSee('Henüz ürün yok') yazdım, düştü: o yazı Vue şablonunda
        iddia component + props üzerinden kuruldu
        ⚠️ VİTRİN BUNUN TERSİ (sunucuda render, metin aramak doğru)
          aynı projede İKİ FARKLI test yöntemi; karıştırmak testi
          yalancı yapıyor

      DOĞRULANDI (gerçek tarayıcı, oturum + CSRF)
        ürün listesi 200, 4 ürün doğru durum/varyant/stok ile
        panelden ürün oluştur → TASLAK, vitrinde YOK
        varyant ekle → yayına al → VİTRİNDE GÖRÜNDÜ (89,90 TL)
        panelden sil → vitrinden düştü
      ⚠️ YAPILMAYAN: görsel yükleme · seçenek/kombinasyon üretici ·
        kategori yönetimi · toplu işlemler (uçları var, ekranları yok)

4E ✅  PANEL SİPARİŞ VE İADE EKRANLARI — 15 test (toplam 614)
      marka artık siparişi görüp KARGOLUYOR ve iadeyi yönetiyor

      ★★ İDDİA: YETKİ ÜÇ KATMANLI, ARAYÜZ ONU BOZMUYOR
        order.view     görebilir
        order.fulfill  kargolayabilir
        order.refund   para iadesi yapabilir
        ⚠️ tek izne indirgemek DEPO PERSONELİNE PARA İADESİ YETKİSİ
          vermek demekti
        üç kırma denemesi de düştü (fulfill kapısı · refund kapısı ×2)

      4E-K1 SORUNLU SİPARİŞLER LİSTENİN BAŞINDA
        stok açığı olan sipariş tarihe bakılmaksızın önce
        tarihe göre sıralansaydı yoğun günde uyarı 3. sayfaya düşer,
        pratikte GÖRÜNMEZ olurdu

      4E-K2 paket SİPARİŞE DARALTILMIŞ doğrulanıyor (1A.5)
      4E-K3 aşırı sevkiyat kuralı controller'da TEKRARLANMIYOR (1D'de)

      ★★ KIRMA DENEMESİ ÖLÇÜLMEYEN BİR DAVRANIŞ BULDU
        stok açığı sıralamasını kaldırdım, HİÇBİR TEST DÜŞMEDİ
        → davranış yorumda yazılıydı ama ölçülmüyordu; test yazıldı
        ★ önceki bloklarda kırma denemesi YALAN TESTLERİ buluyordu;
          bu kez HİÇ YAZILMAMIŞ testi buldu — aynı soru: bu davranışı
          gerçekten ölçüyor muyuz?

      ★ node_modules BAĞLI KLASÖRDEN ÇIKARILDI
        Vite derlemesi Unknown system error -35 ile düştü (errno=35 ailesi)
        ⚠️ "dosyayı sil-yeniden yaz" çözümü BURADA YETMİYOR: kilitlenen
          dosya node_modules içinde ve binlerce tane var
        adlandırılmış Docker birimine taşındı — kilit gitti, derleme hızlandı

      ★ İKİ TEST YARDIMCISI Pest.php'ye TAŞINDI
        iadeyeHazirSiparis · inertiaVerisi
        ikinci dosya kullanmaya başladı; tek dosyada kalsalardı o dosya
        TEK BAŞINA koşturulunca "tanımsız fonksiyon" verirdi

      ★ sevkiyatlikSiparis() PARA İADESİNE HAZIR DEĞİLMİŞ
        ödemeyi servisten yapıyor, TAHSİL EDİLMİŞ Payment kaydı açmıyor
        RefundService onu firstOrFail() ile arıyor → 404
        ⚠️ belirti yanıltıcı: hata mesajı değil Laravel'in 404 SAYFASI
          geliyor, yani "rota yok" sanılıyor

      DOĞRULANDI (gerçek tarayıcı, oturum + CSRF)
        sipariş listesi 200, 15 sipariş; stok açığı olan ikisi GERÇEKTEN
        BAŞTA · ayrıntı adresi, satırları ve ONAYLANAN SÖZLEŞME SÜRÜMÜNÜ
        gösteriyor · paket → kargoya ver → teslim zinciri çalıştı,
        sipariş `fulfilled`, satır 1/1 sevk edildi
      ⚠️ YAPILMAYAN: kısmi iade tutarı ekranda hesaplanmıyor · kargo
        firması entegrasyonu (Faz 5) · sipariş arama yalnızca kargo durumu

4F ✅  KONTROL DÜZLEMİ ARAYÜZÜ — 15 test (toplam 629)
      merkez alan adında /yonetim; ★ FAZ 3'ÜN BORCU KAPANDI

      ★★ MARKA VERİSİ DIŞA AKTARMA (Faz 3 bitiş ölçütünün eksik parçası)
        Faz 3 kapanışında "yapılmadı" diye yazılmıştı
        KVKK: veri işleyen sözleşme bitince veriyi İADE EDİP siler
        silme 3G'de vardı, İADE yoktu — yükümlülüğün yarısı eksikti
        artık 21 tablo JSON olarak iniyor

      4F-K1 İKİ YÜZEY AYRI: guard · kök görünüm · JS PAKETİ
        staff-web     marka şemasındaki users
        platform-web  merkez şemadaki platform_users
        ⚠️ tek guard olsaydı bir markanın sahibi BÜTÜN MARKALARA uzanan
          yetkiyi ele geçirirdi (3C)
        ⚠️ ayrı paket de bilinçli: tek paket olsaydı marka personelinin
          tarayıcısına kontrol düzleminin ekran kodu inerdi

      4F-K2 dışa aktarım tablo listesi AÇIK YAZILI, otomatik tarama değil
        otomatik tarama yeni tabloyu da dökerdi (sayaçlar, kuyruk, jetonlar)

      ★★ GERÇEK KOŞU BİR AÇIK GÖSTERDİ — VE AÇIK BENİMDİ
        dökümün içine bakınca customers.password'te BCRYPT HASH'LERİ vardı
        ⚠️ TABLO LİSTESİNİ DARALTMAK YETMİYORDU: sorun tablo değil
          İÇİNDEKİ KOLONDU
        kimlik bilgisi İŞ VERİSİ DEĞİLDİR: marka "kim müşterim"i alır,
        "müşterim hangi parolayı kullanıyor"u almaz
        aynı temizlik şifreli ayar değerlerine de uygulandı (dosya
        APP_KEY ile birlikte sızarsa çözülür)

      ★ İKİNCİ KOD HATASI: route() merkezde YANLIŞ ALAN ADI üretiyordu
        central_domains birden çok alan adı içeriyor, route() İLKİNİ üretir
        localhost'tan giren yönetici 127.0.0.1'e savruluyor, oturum çerezi
        orada geçersiz olduğu için GİRİŞ EKRANINA GERİ DÜŞÜYORDU
        → göreli yola çevrildi

      ★ INERTIA MIDDLEWARE'İ GLOBAL'DEN ROTA GRUBUNA DARALTILDI
        4C'de bütün `web` grubuna ekleniyordu; ikinci yüzey gelince
        ÇAKIŞIRDI ve kök görünümü SONUNCUSU belirlerdi

      ★★ 4 KIRMA DENEMESİ, DÖRDÜ DE DÜŞTÜ
        tek guard · tenancy()->end() kaldırma · jeton tablosunu ekleme ·
        kök görünüm ayrımını kaldırma

      DOĞRULANDI (gerçek tarayıcı)
        giriş LOCALHOST'TA KALDI (düzeltilen hata) · pano 3 marka
        (1 deneme + 2 aktif) · dışa aktarım 99 KB, 21 tablo, attachment
        BCRYPT İZİ YOK, 3 şifreli ayarın hiçbiri değer taşımıyor
        doğrulama için açılan geçici merkez hesabı SİLİNDİ
      ⚠️ YAPILMAYAN: abonelik başlatma/iptal ekranı · marka silme ekranı
        (geri alınamaz işlem için bilinçli) · merkez kullanıcı yönetimi

4G ✅  TEMA EKRANI — 13 test (toplam 642)
      4-K5'in arayüzü: marka SEÇER, ŞABLON YAZMAZ

      ekran bilerek KISITLI: renk kutusu · sabit yazı tipi listesi ·
      sabit düzen listesi · logo yükleme
      SERBEST METİN ALANI (özel CSS/HTML) YOK
      ⚠️ doğrulama İKİ YERDE, farklı iş: panelde ANLAŞILIR HATA için,
        okuma yolunda GÜVENLİK için (ayar artisan/SQL ile de girebiliyor)

      4G-K1 İKİNCİ DÜZEN GELDİ: `vitrinli`
        4A'da liste tek elemanlıydı; gerekçesi "sonradan eklemek, kavramı
        sonradan icat etmekten kolay"dı — DOĞRU ÇIKTI: bir klasör + bir satır
        ⚠️ düzen yalnızca GÖZ ALICI sayfaları değiştiriyor (ana sayfa, ürün)
          sepet/ödeme/dönüş kopyası YOK: kopyalansalardı iki dosya arasında
          bir gün fark oluşur ve müşteri SEÇTİĞİ DÜZENE GÖRE FARKLI BİR
          ÖDEME AKIŞI yaşayabilirdi

      4G-K2 logo, ürün görselleriyle AYNI güvenlik seviyesinde
        tür DOSYANIN İÇERİĞİNDEN · ad/uzantı istemciden alınmıyor ·
        eski logo yenisi gelince siliniyor
        ⚠️ SVG KABUL EDİLMİYOR: XML belgesidir, <script> taşıyabilir

      ★★ KIRMA DENEMESİ İKİNCİ SAVUNMANIN ÖLÇÜLMEDİĞİNİ GÖSTERDİ
        servisin tür kontrolünü kaldırdım, panel testi DÜŞMEDİ —
        Laravel'in mimes: kuralı zaten yakalıyordu
        ⚠️ savunma ÖLÜ DEĞİLDİ, TESTİ EKSİKTİ — fark önemli: servis
          artisan'dan da çağrılabilir, orada Laravel doğrulaması yok
        2F/3E'de ölü savunmalar KALDIRILMIŞTI; bu ÖLÇÜLDÜ

      ★ TEST YARDIMCISI YİNE ÖLÇÜLECEK ŞEYİ YOK EDİYORDU
        UploadedFile::fake() MIME TÜRÜNÜ DE UYDURUYOR (uzantıdan)
        "içeriği PHP ama adı .png" senaryosu hiç ölçülmüyordu, test yeşildi
        → gerçek dosya yazıldı
        ⚠️ 2E (postJson başlığı) ve 4A (getJson çerezi) ile AYNI AİLE

      ★ 4A'DAN KALAN SESSİZ HATA KAPANDI
        logo yolu doğrudan src'ye basılıyordu; 4A'da yükleme olmadığı için
        görünmüyordu, 4G'de KIRIK GÖRSEL çıkardı
        adres artık HTTP katmanında tenant_asset() ile (Domain kiracılığı
        bilemez, M-2.7)

      ★ ROTA YİNE YANLIŞ GRUBA DÜŞTÜ
        izin:order.refund kalıbı iki yerde; ilk eşleşme API grubuydu ve
        tema rotaları panel/tema olarak kaydoldu → route:list ile yakalandı
        4D'nin dersi tekrarlandı

      DOĞRULANDI (gerçek tarayıcı)
        tema sayfası seçeneklerle açıldı · vitrinli düzenine geçildi →
        KARŞILAMA BÖLÜMÜ + mor renk + serif göründü · geçersiz renk
        REDDEDİLDİ, enjeksiyon izi yok · logo yüklendi, kiracıya özel
        adresten 200 · deneme değişiklikleri GERİ ALINDI
      ⚠️ YAPILMAYAN: canlı önizleme · ana sayfa blok sırası · marka başına
        özel yazı tipi yükleme

4H ✅  MAĞAZA YAYINA ALMA + KAPANIŞ — 6 test

      ★ BİTİŞ ÖLÇÜTÜNÜN EKSİK HALKASI BULUNDU
        kapanışa başlarken ölçütü madde madde denetledim:
        4C-4G giriş/ürün/sipariş/temayı getirmişti ama MAĞAZAYI YAYINA
        ALMA EKRANI YOKTU — marka curl olmadan mağazasını AÇAMIYORDU
        → kapanış özeti yerine önce o ekran yazıldı

      ★★★ GERÇEK BİR AÇIK: A'NIN OTURUMU B'NİN PANELİNİ AÇIYORDU
        A'da giriş yap → çerez → aynı çerezle B'nin paneli → 200
        sebep: oturum yalnızca kullanıcı id'sini tutuyor, guard onu
        İSTEĞİN KİRACISININ şemasından çözüyor; iki markada da id=1
        olan birer kullanıcı var
        ⚠️ bugün tarayıcı yapmaz (SESSION_DOMAIN=null) AMA 3D'deki kayıt
          markalara ALT ALAN ADI veriyor; biri SESSION_DOMAIN'i
          .tikmarka.com yaparsa HER MARKA HER PANELİ AÇAR
          → tek savunma bir ORTAM DEĞİŞKENİYDİ
        çözüm: girişte oturuma marka damgası, her istekte doğrulama

      ★★ ÇÖZÜMÜN İLK HÂLİ ÇALIŞMADI — SEBEBİ ÇOK SİNSİ
        LARAVEL MIDDLEWARE'LERİ ÖNCELİK LİSTESİNE GÖRE SIRALIYOR:
        kontrol `auth`tan ÖNCE yazılıydı ama SONRA koştu
        belirti tuzak: middleware çalışıyor, uyuşmazlığı görüyor,
        logout() işini yapıyor, controller'a check()===false ile
        giriliyor — AMA SAYFA YİNE 200 DÖNÜYOR
        prependToPriorityList denendi, TUTMADI
        doğrusu: uyuşmazlıkta $next HİÇ ÇAĞRILMASIN, middleware kendi
        cevabını döndürsün → zincirin neresinde olduğu fark etmez

      DOĞRULANDI: bitiş ölçütünün tamamı TEK TESTTE yürüyor; her adım
      BİR ÖNCEKİ EKRANDAN gelen bilgiyle (kimlikler modelden okunmuyor)

════════════ ✅ FAZ 4 TAMAMLANDI ════════════
648 test · lint · analyse · CI hepsi yeşil     (Faz 3 sonu: 549 → +99)

Faz 3'te ürün kendi kendini satıyordu ama HER ŞEY CURL İLEYDİ.
Artık üç yüzeyin de ekranı var:
  müşteri tarayıcıdan alışveriş yapıyor
  marka panelden mağazasını yönetiyor
  biz kontrol düzleminden markaları görüyoruz

FAZ 4'ÜN TAŞIYICI DERSİ — Faz 3'ün üstüne:

  ★ ARAYÜZ KATMANI SESSİZ HATANIN YENİ EVİ
    sunucu 200, testler yeşil, veri doğru — kullanıcı BOŞ SAYFA görüyor
    bu fazda ÜÇ KEZ oldu:
      4C  panel boş sayfa    asset_helper_tenancy betiği kiracı yoluna
                             yazıyordu (testler withoutVite ile yeşil)
      4D  bütün sayfalar 500 Inertia DevTools storage'a yazıyordu; hata
                             file_put_contents'ten, yığın izinde sayfa yok
      4H  oturum kontrolü    middleware doğru çalışıyor ama öncelik
          hiç işlemiyordu    listesi onu Authenticate'ten sonraya atıyor

  ★★ TEST YARDIMCISI ÜÇÜNCÜ KEZ ÖLÇÜLECEK ŞEYİ YOK ETTİ
    2E  postJson            Accept başlığını SESSİZCE ekliyor
    4A  getJson             şifresiz çerezi SESSİZCE düşürüyor
    4G  UploadedFile::fake  MIME türünü SESSİZCE uyduruyor
    üçünde de test yeşildi ve HİÇBİR ŞEY ÖLÇMÜYORDU

  ★★ KIRMA DENEMESİ ÜÇ AYRI ŞEY BULUYOR
    yalan test (2C…4A) · hiç yazılmamış test (4E) ·
    ölçülmemiş ikinci savunma (4G)
    ⚠️ ve 4D'de dördüncüsü: KIRMA DENEMESİNİN KENDİSİ YANLIŞ YERİ
      KIRABİLİR — aynı kalıp iki dosyada geçiyordu, ilk eşleşme bozuldu,
      test "geçti" ve YANLIŞ GÜVEN doğdu → artık grep/route:list ile
      değişikliğin uygulandığı doğrulanıyor

  ★ AYNI PROJEDE İKİ FARKLI TEST YÖNTEMİ
    vitrin  sunucuda render → METİN aranır      (assertSee)
    panel   tarayıcıda render → PROP incelenir  (component + props)
    karıştırmak testi yalancı yapıyor (4D'de oldu)

  ★ ARAYÜZ, ALTINDAKİ KARARLARI YENİDEN GÜNDEME GETİRDİ
    ForceJson global (2E)      → api grubuna daraltıldı
    sepet kimliği başlıkta     → çerez eklendi, başlık kaldı
    "oturum kullanmıyoruz"     → gerekçe geçersiz, staff-web açıldı
    route() merkezde           → göreli yola geçildi

AÇIK BORÇLAR — FAZ 5'E
  IyzicoSubscriptionProvider (Faz 3'ten) · müşteri hesabı ekranları ·
  görsel yükleme ekranı · kategori/koleksiyon ekranları ·
  abonelik ekranı · declare(strict_types=1)

FAZ 5 SIRADA — entegrasyonlar: kargo firmaları · e-fatura/e-arşiv

FAZ 4.5 AÇILDI — ARAYÜZ BOŞLUKLARI
  ölçüldü: panelde 73 API ucu, 34 sayfa rotası
  → arka ucun kabaca YARISINA arayüzden erişilemiyor
  Faz 5'ten ÖNCE çünkü marka panelden ÖDEME SAĞLAYICISINI KURAMIYORDU,
  yani gerçek para tahsil edemiyordu

4.5A ✅ VİTRİN YASAL METİN SAYFALARI — 8 test
      ★ BUGÜNKÜ BİR YASAL HATA KAPANDI
        ödeme sayfasındaki "sözleşmeyi okudum" bağlantısı /api/legal/'e
        gidiyordu; müşteri HAM JSON görüyordu
        ⚠️ mesafeli satışta müşterinin sözleşmeyi OKUYABİLMESİ ZORUNLU
        4B'de neden kaçtı: test assertSee('Mesafeli satış sözleşmesini')
        diyordu — BAĞLANTININ VARLIĞINI ölçüyordu, NEREYE GİTTİĞİNİ değil

      4.5A-K1 yasal metinler `magaza-acik` KAPISININ DIŞINDA
        emsal 2G'de: "yasal bir hak, mağazanın açık olmasına bağlanamaz"
        ⚠️ ilk hâli kapının içindeydi, TEST ORTAYA ÇIKARDI: metinlerini
          tamamlamamış (mağazası kapalı) marka, yayınladığı metni bile
          gösteremiyordu

      metin nl2br(e()) ile basılıyor: ham HTML olsaydı marka kendi
      vitrinine betik gömebilirdi (4-K5'in aynısı)
      sürüm + tarih sayfada · yayınlanmamış metin listede yok

4.5B ✅ PANEL: ÖDEME AYARLARI + YASAL METİN — 11 test (toplam 667)
      ★ FAZ 4'ÜN EN CİDDİ BOŞLUĞU: marka artık ödeme sağlayıcısını
        panelden kurabiliyor (uçları 1E'de vardı, ekranı yoktu)

      4.5B-K1 anahtar DEĞERLERİ ekrana hiç gitmiyor
        yalnızca "girilmiş mi"; gösterilseydi panele giren herkes okurdu
      4.5B-K2 BOŞ bırakılan anahtar mevcut değeri SİLMİYOR
        ⚠️ ekran değeri göstermiyor; marka yalnızca sağlayıcıyı
          değiştirdiğinde alanlar BOŞ gider — boşu yazsaydık marka
          farkında olmadan anahtarını siler, TAHSİLAT DURURDU
      4.5B-K3 taslak kaydetmek YAYINLAMAK DEĞİL (1A.4)
        salt-ekleme tablo: eski sürüm duruyor, siparişler ona bağlı
        "yayınlanmamış değişiklik var" uyarısı ayrı gösteriliyor

      sahte sağlayıcı seçiliyken ekran AÇIKÇA uyarıyor: "gerçek para
      tahsil etmez" — yazılmasaydı marka test sağlayıcısıyla satışa
      çıkıp parasını alamazdı

      DOĞRULANDI (gerçek tarayıcı)
        /yasal/distance_sales → 200 + text/html, "Sürüm 1", JSON yok
        ödeme ekranı: iyzico seçili, iki anahtar "girilmiş",
        GERÇEK DEĞER SIZMIYOR
        yasal ekran: üç metnin yayın sürümü + değişiklik uyarısı

4.5-K1 ✅ ÖDEME FORMU IFRAME İÇİNDE — 8 test (toplam 675)
      ESKİ: sipariş → redirect()->away(iyzico), müşteri SİTEDEN ÇIKIYOR
      YENİ: sipariş → /odeme/ode/{uuid}, kart formu IFRAME içinde
      → müşteri sitede kalıyor, kart verisi bize HİÇ UĞRAMIYOR

      ★★ İKİ GÖMME YÖNTEMİNDEN HANGİSİ
        checkoutFormContent (hazır script) → iyzico'nun JS'i BİZİM
          kökenimizde çalışır, kart alanları BİZİM DOM'umuzda olur
        paymentPageUrl + &iframe=true      → her şey ONLARIN kökeninde ✓
        ⚠️ betik daha kolaydı ve çoğu örnekte o var; SEÇİLMEDİ
        test bunu ölçüyor: sayfada checkoutFormContent geçmemeli

      4.5-K1a PaymentInitiation "gömülebilir mi" sorusunu AYRICA cevaplıyor
        sağlayıcı iframe vermiyorsa yönlendirmeye düşülüyor; tek yol
        dayatılsaydı o sağlayıcıya geçildiği gün ödeme TAMAMEN kırılırdı
      4.5-K1b sahte sağlayıcı da gömülebilir — yoksa iframe yolu ANCAK
        CANLIDA ilk kez denenirdi
      4.5-K1c dönüş sayfası ÇERÇEVEDEN ÇIKIYOR
        sağlayıcı dönüş adresini O ÇERÇEVENİN İÇİNDE açıyor; betik
        olmasaydı müşteri "sipariş alındı"yı ödeme formunun yerinde,
        küçük bir çerçevede görürdü
        ⚠️ window.parent DEĞİL window.top: iç içe çerçevede bir seviye
          çıkardı · betik çalışmazsa bağlantı target="_top"

      ★★ KIRMA DENEMESİ BİR TESTİN YALANINI GÖSTERDİ
        çerçeveden çıkış testi assertSee('window.top') diyordu;
        yönlendirme satırı silinse bile `if (window.top !== window.self)`
        koşulu metinde kalıyor ve TEST YEŞİL GEÇİYORDU
        iddia asıl satıra bağlandı (window.top.location.href)

      ⚠️ ESKİ TESTLER BU DEĞİŞİKLİĞİ YAKALAYAMAZDI: assertRedirect()
        hedefsiz çağrılıyordu — "bir yere yönlendirildi" ölçülüyordu,
        NEREYE değil (4.5A'daki dersin aynısı)

      DOĞRULANDI (gerçek iyzico sandbox)
        sipariş → 302 → /odeme/ode/{uuid} (siteden çıkılmıyor)
        iframe kaynağı: sandbox-cpp.iyzipay.com?token=…&iframe=true
        sağlayıcı betiği sayfada YOK
      ⚠️ FAZ 6'YA NOT: PCI DSS 4.0 iframe kullanan sayfalar için istemci
        tarafı betik bütünlüğü koruması istiyor (CSP + betik envanteri)

4.5C ✅ PANEL: PERSONEL/ROLLER + ÖZEL ALAN ADI — 12 test (toplam 687)
      marka artık personel EKLEYEBİLİYOR (uçları 1A'da) ve DNS
      TALİMATINI GÖREBİLİYOR (uçları 3H'de, ekranı yoktu)

      ★★ ÜÇ TESTİM YANLIŞ VARSAYIMLA YAZILMIŞTI — KOD HAKLI ÇIKTI
        "roles: ['Depo']" → varsayılan roller Yönetici/Katalog/Sipariş
        "sahip çıkarma 422" → getRouteKeyName() UUID; id ile 404 geliyordu
          ⚠️ yani ölçtüğüm şey KORUMA DEĞİL KAZAYDI
          ve bu GERÇEK BİR HATA ortaya çıkardı: arayüz id gönderiyordu,
          "personeli çıkar" düğmesi canlıda 404 verirdi → uuid'ye çevrildi
        "sistem rolü değiştirilemez" → 1A.6 yalnızca SİLMEYİ kilitliyor;
          ad/izin değişebiliyor ve bu bilinçli

      ★ Role::permissions ÖZELLİK DEĞİL METOT — özellik gibi okununca
        Laravel ilişki sanıyor, "must return a relationship instance" 500

      ★ TEST 24 SANİYE SÜRÜYORDU: SystemDnsChecker gerçek ağa çıkıyordu
        bundan kötüsü test AĞA BAĞIMLIYDI — ölçtüğü şey bizim kodumuz
        değil internet olurdu → FakeDnsChecker bağlandı, 24sn → 5sn

      ★ DERLENMİŞ BLADE ÖNBELLEĞİ bağlı klasörden çıkarıldı
        aynı errno=35 kilidi DÖRDÜNCÜ KEZ (4D · 4E · 4.5C ×2), her
        seferinde panelin bütün sayfaları 500
        node_modules'te yaptığımızın aynısı: adlandırılmış Docker birimi

      DOĞRULANDI (gerçek tarayıcı)
        personel ekranı: 3 personel, 3 rol (izin ve personel sayılarıyla)
        alan adı ekranı: iki doğrulanmış kayıt
        yeni alan adı → ÜÇ DNS SEÇENEĞİ (CNAME·A·TXT) marka başına
        rastgele belirteçle göründü, sonra silindi

4.5D ✅ MÜŞTERİ HESABI — 11 test (toplam 698)
      vitrinin EN BÜYÜK boşluğu: uçlar 1A/1C/2G'de vardı ama müşterinin
      HİÇBİR EKRANI yoktu — siparişini takip edemiyordu
      ⚠️ üstelik MÜŞTERİ SİPARİŞ LİSTESİ UCU HİÇ YOKTU

      4.5D-K1 kimlik OTURUMLA (customer-web), token'la değil
        vitrin sunucuda render ediliyor, formlar JS'siz (4B-K1)
        `customer` (sanctum) DURUYOR — mobil ve entegrasyonlar için
        girişte üç şey BU SIRAYLA: misafir sepetini taşı (oturumdan ÖNCE)
        · oturum kimliğini tazele · marka damgası (4H)

      ★★ KORUMAYI GENİŞLETMEK ONU DOĞRU YERE TAKMAK DEĞİLDİR
        4H'deki oturum-marka kontrolü müşteri guard'ını kapsayacak şekilde
        genişletildi — YETMEDİ: middleware yalnızca PANEL grubuna
        takılmıştı, vitrinde hiç çalışmıyordu
        → A'nın müşteri oturumu B'nin hesabını AÇMAYA DEVAM EDİYORDU
        iki kırma denemesi AYRI AYRI düştü (guard listesi · grup)

      ★★ BİR TESTİM YANLIŞ ŞEY İDDİA EDİYORDU — GERÇEK KOŞU GÖSTERDİ
        test "kurbanın kendi markasındaki oturumu da kapanır" diyordu
        curl ile ESKİ çerez elle gönderilince A'nın oturumu AÇIK KALDI
        sebep: test istemcisi B'nin YENİ çerezini taşıyor — yani test
        sunucunun davranışını değil KENDİ ÇEREZ TAKİBİNİ ölçüyormuş
        gerçek güvence: ÇALINAN OTURUM BAŞKA MARKADA GEÇMİYOR
        (çerezi çalan zaten A'ya erişebiliyordu; middleware erişimin
         GENİŞLEMESİNİ engelliyor, geri almıyor)
        iddia 4H ve 4.5D testlerinde düzeltildi

      ★ ADLANDIRILMIŞ BİRİM İZİNLERİ İMAJA YAZILDI
        4.5C'de Blade önbelleği birime taşınmıştı; birim root:root 755
        doğduğu için derleyici geçici dosyayı yazamadı
        ⚠️ belirti yanıltıcı: "izin yok" değil tempnam() uyarısı —
          PHP sistem geçici klasörüne düşüyor, rename() patlıyor,
          müşteri kayıt sayfası 500 veriyordu

      DOĞRULANDI (gerçek tarayıcı)
        kayıt → 302 → /hesabim · giriş → hesap sayfası
        üst bar giriş durumuna göre "Hesabım" gösteriyor
        A'nın müşteri çerezi B'de → 302 → B ana sayfası

4.5E ✅ PANEL KATALOG EKRANLARI — 12 test (toplam 710)
      dört boşluk birden: kategori · varyant ekseni · koleksiyon · görsel
      hepsinin ucu 1B/2D'de vardı, hiçbirinin ekranı yoktu

      4.5E-K1 kategori ve eksen TEK EKRANDA (ikisi de ürün eklemeden
        ÖNCE yapılan hazırlık işi)
      4.5E-K2 kurallı koleksiyonun ÜYE SAYISI SORGUDAN
        tablodan verilseydi kurallı koleksiyon hep "0 ürün" görünürdü —
        marka kuralının çalıştığını hiç göremezdi

      ★★ KIRMA DENEMESİ ÖLÜ BİR SAVUNMA BULDU
        controller'a "kurallıya elle ekleme yasak" kontrolü yazmıştım;
        kaldırdım, HİÇBİR TEST DÜŞMEDİ — gerçek koruma serviste
        (CollectionService::urunEkle → manuelOlmali)
        2F/3E kararı uygulandı: KOPYA KALDIRILDI
        sonra servisteki asıl korumayı kırdım, test DÜŞTÜ → artık doğru
        yeri ölçüyor

      ★ GERÇEK ARAYÜZ HATASI: kategori girintisi HİÇ OLUŞMAYACAKTI
        derinlik substr_count($path,'.') ile hesaplanıyordu ama ltree
        yolu `/1/2/` biçiminde — NOKTA YOK, derinlik hep 0 çıkardı

      ★ ÜÇ TESTİM YANLIŞ VARSAYIMLA (kod haklı): koşul anahtarı `op`
        (operator değil) · eksenleriAyarla() Option NESNESİ alıyor ·
        varsayılan rol adları

      ⚠️ renk kutusu yalnızca #rrggbb: serbest metin olsaydı 4-K5'te
        kapatılan CSS ENJEKSİYONU buradan geri gelirdi

      DOĞRULANDI (gerçek tarayıcı)
        katalog: Giyim(0) → Tişört(1) doğru girintiyle, iki eksen
        koleksiyon: kurallı "250 TL Altı" 1 ürün (SORGUDAN), manuel 2
        görsel yüklendi → adresi 200 image/png → panelden silindi
      ⚠️ YAPILMAYAN: kural düzenleme arayüzü · kategori taşıma ·
        görsel sıralama (üçü de uçlarda var, 4.5F'ye)

4.5F ✅ YORUM MODERASYONU + GÖRSEL CİLA + KAPSAM TESTİ — 6 test
      ★ EKRANI OLMAYAN SON ALAN KAPANDI: yorum moderasyonu
        uçları 2E'de vardı; ekran olmadığı için yorumlar HİÇ
        ONAYLANAMIYORDU — vitrinde hiç yorum görünmüyordu ve marka
        bunu fark edemiyordu
        ⚠️ kuyruk ESKİDEN YENİYE (listenin geri kalanının tersine):
          en eski yorum EN ÇOK BEKLEYEN demek

      4.5E'den kalan üç boşluk da kapandı: kategori taşıma · görsel
      sıralama · koleksiyon kural düzenleyici
      ⚠️ kural düzenleyicide alan/işleç listesi SUNUCUDAN: kopyalansaydı
        iki liste ayrışır, marka olmayan bir alanı seçebilirdi

      ★ GÖRSEL: iskeletten çıkış, YENİDEN TASARIM DEĞİL
        görselsiz ürün için GERÇEK yer tutucu — önce boş SVG basılıyordu
        ve tarayıcı KIRIK KARE çiziyordu
        kart gölgesi/hareketi · düğme durumları · GÖRÜNÜR ODAK HALKASI
        ⚠️ outline:none sayfayı "temiz" gösterir ama klavyeyle gezen
          kullanıcı nerede olduğunu göremez

      ★★ BİTİŞ ÖLÇÜTÜ ARTIK YAPISAL TESTLE (PanelKapsamTest)
        Faz 4.5 bir ÖLÇÜMLE açılmıştı (73 uç, 34 sayfa); aynı ölçüm
        artık her koşuda yapılıyor
        ⚠️ uç SAYISI değil ALAN KAPSAMI ölçülüyor (bir ekran 14 ucu
          karşılayabiliyor) · /panel/me için eşlemede BİLEREK null
        ★ ilk kırma denemem YETERSİZDİ: yalnızca listeyi sildim, diğer
          rotalar alanı ayakta tuttu → alanın tamamı kaldırılınca düştü

════════════ ✅ FAZ 4.5 TAMAMLANDI ════════════
716 test · lint · analyse · CI hepsi yeşil     (Faz 4 sonu: 648 → +68)

açılış ölçümü : 73 uç, 34 sayfa — arka ucun yarısına erişilemiyordu
kapanış       : ekranı olmayan API alanı kalmadı, yapısal testle korunuyor

FAZIN TAŞIYICI DERSİ

  ★ "UÇ VAR" İLE "KULLANILABİLİR" ARASINDAKİ FARK
    Faz 4 bitiminde sistem uçtan uca çalışıyordu ve testler yeşildi —
    ama marka GERÇEK PARA TAHSİL EDEMİYORDU, sözleşmesini
    düzenleyemiyordu, müşteri sözleşmeyi OKUYAMIYORDU (bağlantı ham
    JSON'a gidiyordu) ve siparişini takip edemiyordu

    ölçümle bulunan : sözleşme bağlantısı · ödeme ayarları ekranı ·
                      yorum moderasyonu
    testle bulunan  : çapraz marka müşteri oturumu · kategori girintisi
                      (ltree ayracı `/`, `.` değil) · "personeli çıkar"
                      düğmesi 404 (uuid/id)
    ★ altısı da KOD DOĞRU ÇALIŞIRKEN vardı; hiçbiri hata vermiyordu

  ★★ KORUMAYI GENİŞLETMEK ≠ DOĞRU YERE TAKMAK (4.5D)
    guard listesi genişletildi ama middleware vitrin grubunda yoktu

  ★★ TEST İSTEMCİSİ ÖLÇÜMÜ BOZUYOR — DÖRDÜNCÜ KEZ
    2E postJson · 4A getJson · 4G UploadedFile::fake ·
    4.5D test istemcisinin ÇEREZ TAKİBİ

  ★★ KIRMA DENEMESİ DÖRT ŞEY BULUYOR ARTIK
    yalan test · hiç yazılmamış test · ÖLÜ SAVUNMA (4.5E) ·
    kendisinin YANLIŞ YERİ KIRDIĞI durum (4D · 4.5F)

AÇIK BORÇLAR → FAZ 5
  IyzicoSubscriptionProvider · PCI DSS 4.0 betik bütünlüğü (Faz 6) ·
  toplu işlemler · declare(strict_types=1)

FAZ 5 SIRADA — kargo firmaları · e-fatura/e-arşiv

4.5G ✅ KULLANIMDAN ÇIKAN DÜZELTMELER — 724 test
      ⚠️ üçü de TESTLER YEŞİLKEN vardı

      ✅ ADRES FORMUNDA `title` ALANI YOKTU
        AddressRequest zorunlu tutuyor, ekranda karşılığı yok →
        ADRES DEFTERİ HİÇ KULLANILAMIYORDU
        ⚠️ testler göremezdi: hepsi ornekAdres() ile TAM veri gönderiyordu
          eksik olan sunucu değil EKRANDI
        yeni test FORMUN HTML'İNE bakıyor

      ℹ️ ürün oluşturma yönlendirmesi: ölçüldü, ZATEN doğru çalışıyor

      ✅ ÖDEME — İKİ AYRI SORUN
        1) DOĞRULAMAMIZ SAĞLAYICIDAN GEVŞEKTİ
           a@a bizim email kuralımızı geçiyor, iyzico reddediyor
           ⚠️ bedeli ZAMANLAMA: sipariş OLUŞUYOR, stok bağlanıyor,
             ödeme SONRA patlıyor → stok 60 dk kimseye satılamıyor
           DeliverableEmail: nokta + en az iki harflik TLD
           ⚠️ DNS SORGUSU YOK: ödeme akışında ağa çıkmak isteği
             yavaşlatır, ağ kesintisinde satışı durdururdu
        2) PaymentProviderException TARAYICIYA JSON DÖNÜYORDU
           4A ve 4B'de düzeltilen hatanın ÜÇÜNCÜSÜ
           sağlayıcının mesajı hâlâ müşteriye gitmiyor, yalnızca SUNUM
           biçimi ayrıldı

      DOĞRULANDI (gerçek tarayıcı)
        a@a → 302 → /odeme, "alan adı geçersiz görünüyor", SİPARİŞ YOK
        geçerli e-posta → sipariş → GERÇEK iyzico sandbox formu

4.5H ✅ KOLEKSİYON: VİTRİNDE SAYFA, PANELDE KURAL EDİTÖRÜ — 734 test
      kullanıcının bildirdiği iki sorun, ikisi de "UÇ VAR ≠ KULLANILABİLİR"

      ✅ 1) VİTRİNDE KOLEKSİYON SAYFASI YOKTU
        marka koleksiyon kurabiliyordu (2D, /api/collections)
        müşteri HİÇBİR YERDEN göremiyordu
        /koleksiyonlar (liste) + /koleksiyon/{slug} (detay) + başlık bağlantısı
        ⚠️ bağlantı KOŞULLU (koleksiyonVar): aktif koleksiyonu olmayan
          markada görünmüyor — yoksa müşteriye BOŞ SAYFAYA giden menü
        ⚠️ ürünler CollectionQuery::urunler() üzerinden — manuel ve kurallı
          AYNI yoldan, forStorefront() kapsamı korunuyor
          doğrudan products() yazılsaydı manuel koleksiyon TASLAK ürünü
          müşteriye gösterirdi (kırma denemesiyle ölçüldü)
        kapalı koleksiyon (is_active=false) detayda 404

      ✅ 2) PANELDE KURAL EDİTÖRÜ OLUŞTURMA FORMUNDA YOKTU
        "kural bir nesne olmalı" → 2D'de BOŞ KURAL BİLEREK YASAK
          (izin verilseydi koleksiyon TÜM KATALOĞU gösterirdi)
        yani SUNUCU HAKLIYDI, EKRAN EKSİKTİ
        kuralAlanlari + eslesmeler artık HER İKİ ekrana da gidiyor
        ⚠️ controller'da ERKEN KONTROL: koşulsuz istek servise gitmeden
          ANLAŞILIR mesajla dönüyor (servisin mesajı doğruydu ama teknikti)

      ✅ 3) KAPSAM TESTİ VİTRİNİ DE KAPSIYOR
        4.5F'nin testi yalnızca PANELE bakıyordu → bu eksiği YAKALAYAMAZDI
        bilerek null: categories · privacy · me (gerekçeler testin içinde)

      ⚠️ ANALİZ GERÇEK TUZAK YAKALADI
        yeni test yardımcısı koleksiyonluMagaza, CollectionTest'tekiyle
        ÇAKIŞIYORDU → iki dosya birlikte yüklenince PHP "cannot redeclare"
        Larastan gösterdi, testler tek dosya koşarken YEŞİLDİ

      DOĞRULANDI (gerçek curl)
        /koleksiyonlar 200 · /koleksiyon/yaz-seckisi 200 üç ürün
        olmayan slug 404 · başlıkta bağlantı görünüyor

4.5H.1 ✅ KATEGORİ KURALI — 404'ün ÜÇ SEBEBİ — 737 test
      marka kural değerine kategorinin ADINI yazdı ("Giyim"), alan SLUG
      bekliyordu → kural kaydedildi, koleksiyon 404 verdi

      ⚠️ 404'ÜN KAYNAĞI BEKLENMEDİK YERDE
        CollectionQuery kategoriyi firstOrFail() ile arıyordu
        ModelNotFoundException → Laravel 404'e çeviriyor
        yani VERİ sorunu "sayfa yok" diye görünüyordu
        panelde üye sayısı aynı sorgudan → TEK bozuk kural
        KOLEKSİYON LİSTESİNİN TAMAMINI düşürüyordu

      ÜÇ KATMAN, ÜÇÜ DE (hiçbiri tek başına yetmiyor)
        EKRAN  kategori LİSTEDEN seçiliyor, serbest metin değil
               (kural API'den/eski kayıttan bozuk gelebilir)
        YAZMA  CollectionService kategorinin VARLIĞINI doğruluyor
               (kategori kural yazıldıktan SONRA silinebilir)
        OKUMA  bulunamayan kategori 404 DEĞİL, BOŞ EŞLEŞME
               (tek başına bozuk kuralı sessiz bırakırdı)

      ⚠️ koşul SESSİZCE ATLANMIYOR, hiçbir şeyle eşleşiyor
        atlansaydı `all`da kural gevşer, FAZLA ürün gösterirdi
      ⚠️ varlık kontrolü CollectionRules'a KONMADI: o sınıf okuma
        yolunda da çalışıyor ve veritabanına hiç bakmıyor

      ekran ham anahtar gösteriyordu (category · in_tree)
        adlar artık SUNUCUDAN (arayüzde kopyalansa liste ayrışırdı)

      DOĞRULANDI (gerçek curl): iki bozuk koleksiyon 404 → 200

4.5I ✅ MÜŞTERİ KİMLİĞİ VİTRİN SAYFALARINDA — 743 test
      üç şikâyet, TEK kök: siparişlerim boş · adres ödemede yine soruluyor
      · sepet girişten sonra da misafir

      KÖK SEBEP TEK SATIR
        sayfa katmanı $istek->user() çağırıyordu
        varsayılan guard `customer` = SANCTUM (token)
        oturumla giren müşteri sanctum'a GÖRÜNMÜYOR → misafir sayılıyor
        ⚠️ bedeli sessiz: 24 siparişin HEPSİ (ödenmişler dâhil) sahipsiz
          "Siparişlerim" doğru yazılmıştı, HİÇBİR ZAMAN DOLAMAZDI
        ⚠️ API katmanı TERSİ — orada varsayılan guard DOĞRU
          düzeltme yalnızca SAYFA katmanına uygulandı

      ✅ ÖDEME SAYFASI ADRES DEFTERİNİ TANIYOR
        kayıtlı adres varsa liste + seçim, "Başka adrese gönder" form açar
        adresi olmayan bugünkü formu görüyor
        ⚠️ seçilen adres SUNUCUDA çözülüyor, istekten gelen shipping DEĞİL
        ⚠️ sahiplik addresses() ilişkisinden — yabancı uuid bulunamıyor
        ⚠️ required_without:adres_uuid — koşulsuz nullable olsaydı
          hiç adres seçmeyen BOŞ ADRESLE sipariş verirdi
        ⚠️ deftere kayıt yalnızca İSTENİRSE

      ⚠️ ÖLÇEN TESTİ İKİ KEZ YAZDIM
        ilki actingAs kullanıyordu → HATALI KODLA YEŞİL GEÇTİ
        actingAs VARSAYILAN GUARD'I DA DEĞİŞTİRİYOR
        gerçek /giris POST'uyla ölçünce customer_id null çıktı

      AYNI YARDIMCI BİR YALANCI TESTİ DE GİZLEMİŞTİ
        GomuluOdemeTest: actingAs(..., 'customer') = TOKEN guard'ı
        ama ölçtüğü şey bir SAYFA rotası
        düzeltmeden sonra düştü → gerçek girişle yazıldı, KORUMA YERİNDE
        kırılan koruma değil, TESTİN ÖLÇTÜĞÜ ŞEYDİ

      yapısal test: sayfa katmanında guard'sız user() kalamaz

      DOĞRULANDI (gerçek curl)
        giriş → sepet → ödemede kayıtlı adres görünüyor
        shipping HİÇ GÖNDERİLMEDEN sipariş oluştu, adres defterden
        müşteriye bağlandı, "Siparişlerim"de göründü

4.5I.1 ✅ BOŞ METNİ NULL'A ÇEVİREN MIDDLEWARE — 745 test
      kayıtlı adres seçiliyken "shipping.full_name metin olmalıdır"
      müşteri ödeme ekranına HİÇ gidemiyordu

      SEBEP ConvertEmptyStringsToNull
        tarayıcı GİZLİ formdaki alanları da gönderiyor
        (gizlemek göndermemek DEĞİLDİR)
        global middleware boş metni NULL'a çeviriyor
        `string` kuralı null'da düşüyor

      ⚠️ TESTİM GÖREMEZDİ: shipping anahtarlarını HİÇ göndermiyordu
        middleware'in dönüştüreceği değer yoktu → yeşil test, kırık ekran
        gerçek curl koşusu ortaya çıkardı

      düzeltme: `nullable` kuralı `required_without`'tan ÖNCE
      ⚠️ required_* ÖRTÜK — null'da da koşuyor, zorunluluk GEVŞEMİYOR
        bu iddia da ölçüldü (yoksa boş adresli sipariş, kargo çıkamaz)

4.5L ✅ PANEL SİPARİŞ AKIŞI — 751 test
      "panellerden sipariş durumlarını güncelleyemiyorum"

      ÖLÇÜM ÖNCE: yetenek VARDI (paket aç → kargoya ver → teslim edildi)
        sorun AKIŞTI — kargo entegrasyonu yokken siparişi kapatmak için
        satır satır adet girip ÜÇ düğmeye basmak gerekiyordu

      ✅ TEK ADIMDA TAMAMLAMA — FulfillmentService::tamamla()
        ⚠️ KISAYOL DEĞİL, AYNI YOLUN KENDİSİ
          durum doğrudan yazılsaydı ödenmemiş sipariş de "teslim edildi"
          olabilir, stok ve bildirim adımları atlanırdı
        ⚠️ iş kuralı DOMAIN'de — controller'da olsaydı artisan/kuyruk
          aynı işi yaparken kontrolleri atlardı
        ⚠️ ikinci çağrıda "aşırı sevkiyat" değil ANLAŞILIR mesaj

      ✅ PANELDEN İADE TALEBİ
        panel iadeyi İŞLEYEBİLİYORDU ama AÇAMIYORDU
        vitrinde de ekran yok → iade PRATİKTE ULAŞILAMAZDI
        ⚠️ cayma=false: cayma 14 günlük pencereye bağlı; markanın müşteri
          adına açtığı talep takılsaydı KUSURLU ÜRÜN iadesi açılamazdı
        ⚠️ sebep ZORUNLU · talep sonrası iade AYRINTISINA yönlendiriliyor

      ⚠️ YETKİ AYRIMI KORUNDU (yeni uçlarda kolayca kaybedilirdi)
        tamamla → order.fulfill · iade AÇMAK → order.refund
        depocunun kargolayabilmesi, iade başlatabilmesi DEMEK DEĞİL

      DOĞRULANDI (gerçek panel isteği)
        tamamla → fulfilled, tek paket delivered
        iade → 302 /yonetim/iadeler/{uuid}, is_withdrawal=false

4.5L.1 ✅ PANEL KATALOG: EKSENLER · KOLEKSİYON ÜYELİĞİ · BİLEŞEN — 757 test
      dört şikâyet, İKİSİ AYNI KÖKTEN

      ✅ VARYANT EKSENLERİ YOKTU — bedeli İKİNCİ VARYANT
        eksen olmayınca her varyantın options alanı [] oluyor
        (product_id, options) benzersiz kısıtı → İKİNCİ VARYANT HER ZAMAN
        patlıyordu, üstelik ham 500 (duplicate key ... unique constraint)
        markanın EN SIK yaptığı iş, EN ANLAŞILMAZ hatayı veriyordu
        ⚠️ kısıt KALDIRILMADI — aynı "Kırmızı/M" iki kez olsaydı müşteri
          hangisini seçtiğini bilemez, stok ikiye bölünürdü
          Domain'e DuplicateVariantException, kısıt SON SAVUNMA
        ⚠️ CatalogRuleException genel işleyicisi JSON döndürüyor (api doğru)
          panelde yakalanıp oturum hatasına çevrildi — 4A/4B/4.5G'nin
          DÖRDÜNCÜSÜ
        ⚠️ eksen varyant varken KİLİTLİ; ekran gizliyor ama koruma sunucuda

      ✅ ÜRÜN EKRANINDAN KOLEKSİYON ÜYELİĞİ
        seçici koleksiyon ayrıntısında ZATEN VARDI ve çalışıyordu (ölçüldü)
        marka onu ÜRÜN tarafından arıyordu
        ⚠️ yalnızca MANUEL koleksiyonlar — kurallıda üyelik sorgu anında
          yeni kapı açıldı, ESKİ KURAL onunla birlikte geldi

      ✅ ÜRÜN OLUŞTURUNCA VARYANT/GÖRSEL BÖLÜMÜ GELMİYORDU
        ⚠️ SUNUCUDA HİÇBİR ŞEY YANLIŞ DEĞİLDİ
          4.5G'de "ölçtüm çalışıyor" denmişti — ölçülen YÖNLENDİRMEYDİ,
          EKRAN DEĞİL
        Inertia bileşeni yeniden KULLANIYOR: oluşturma ve düzenleme AYNI
        bileşen → setup() bir daha koşmuyor → `yeniMi` true'da donuyor
        computed'e çevrildi + useForm watch ile yeniden tohumlanıyor
        (yoksa başka ürüne geçince kutularda ESKİ ürünün verisi kalırdı)

      DOĞRULANDI (gerçek panel isteği)
        eksensiz ikinci varyant 500 → 302 + oturum hatası
        Renk ekseni atandı, üç varyant eklendi (kırmızı/mavi/siyah)

4.5K ✅ VİTRİNDE İADE EKRANI — 764 test
      ★ İADE, KODU TAMAMEN YAZILMIŞ OLMASINA RAĞMEN ULAŞILAMAZDI
        uçları 2B'de vardı, servisi eksiksizdi, panelde onay/teslim/para
        zinciri çalışıyordu — ama TALEBİ AÇACAK HİÇBİR EKRAN YOKTU

      ✅ SİPARİŞ SAYFASINA İADE BÖLÜMÜ
        ⚠️ "kaç adet iade edebilirim" SERVİSTEN (asimiDogrula ile aynı sorgu)
          ekran kendi hesabını yapsaydı iki formül olur, müşteri formu
          gönderip red alır ve sebebini ANLAMAZDI
        ⚠️ cayma süresi SATIR BAZINDA (2B-K2) — kısmi sevkiyatta her
          paketin kendi teslim tarihi var
        ⚠️ cayma mı KUSURLU mu — müşteri seçiyor; yalnızca cayma
          sunulsaydı 15. günde kusurlu ürün bildiren hiçbir şey yapamazdı
          seçim TALEBİ AÇMAYA yetiyor, ONAYLAMAYA değil (2B-K1)
        ⚠️ düğme "İade talebi oluştur" — "iade et" deseydi para iadesinin
          başladığı beklentisini yaratırdı

      ⚠️ BİR KIRMA DENEMESİ TESTİ KIRAMADI — VE HAKLIYDI
        boş form kontrolü kaldırıldı, test GEÇMEYE DEVAM ETTİ
        servis yine istisna atıyor, oturumda `hata` yine doluyordu
        test "BİR hata var"ı ölçüyordu, "ANLAŞILIR hata var"ı değil
        MESAJIN KENDİSİ iddiaya eklendi → sonra kırma denemesi düştü

      DOĞRULANDI (gerçek curl)
        form görünüyor → talep açıldı → "Talep alındı, marka
        değerlendiriyor" → PANELDE iade listesinde

4.5J ✅ SEPET SAYACI VE BEKLEYEN SİPARİŞ — 771 test
      "sayaç 2 gösteriyor ama içine girince boş, 2'de sabit kaldı"

      ✅ ROZET İLE SEPET SAYFASI AYRI YOLLARDAN OKUYORDU
        rozet         → misafirSepetiBul(token)  ← YALNIZCA misafir
        sepet sayfası → CartResolver::bul()      ← giriş varsa MÜŞTERİ
        ⚠️ İKİ YÖN DE BOZUK, İKİSİ DE SESSİZ
          bayat misafir çerezi → rozet dolu, sepet BOŞ (bildirilen)
          giriş yapmış dolu sepet → rozet HİÇ ÇIKMIYOR (ölçüldü)
        ⚠️ 4B'deki SepetKimligiTest'in İKİNCİ YARISI: kimliği OKUMAK bir
          şey, o kimlikten sepeti ÇÖZMEK başka bir şey
        ⚠️ yapısal testin KAPSAMI DAR: ilk hâli meşru kullanımı (girişte
          birleştirme) ve KENDİ YORUM METNİNİ ihlal sayıyordu

      ✅ BEKLEYEN SİPARİŞE EYLEM
        "Ödemeyi tamamla" + "iptal et"
        ⚠️ iptal odemeBasarisiz'dan AYRI METOT: o "sağlayıcı reddetti"
          demek ve müşteriye "ödemeniz alınamadı" postası gönderiyor
        ⚠️ ASIL KAZANÇ STOKTA: bağlı stok 60 dk kimseye satılamıyordu,
          iptal HEMEN serbest bırakıyor (testi ölçüyor)
        ⚠️ yalnızca pending — ödenmişe izin verilseydi müşteri PARASINI
          GERİ ALMADAN siparişini kapatırdı

      DOĞRULANDI (gerçek curl)
        rozet 2 = sepet sayfası 2 · iptal → cancelled

4.5M ✅ GÖSTERİM SAATİ — 777 test
      "vitrinde ödendi yazıyor, panele saati yanlış düşmüş"

      ÖLÇÜM İDDİAYI YARI DOĞRULADI — TERS YÖNDE
        depolama timestamptz +00              ✅ doğru
        panel new Date().toLocaleString()     ✅ 11:34 (tarayıcı çevirdi)
        vitrin format(), app.timezone=UTC     ❌ 08:34 (ÜÇ SAAT GERİDE)
        yani panel DOĞRUYDU, yanlış olan VİTRİNDİ
        ⚠️ vitrin SUNUCUDA render ediliyor (4-K1), tarayıcı çeviremiyor
          panel Inertia olduğu için TESADÜFEN doğruydu

      ✅ MARKA BAŞINA GÖSTERİM SAAT DİLİMİ (StoreTimezone)
        ⚠️ ÇÖZÜM config/app.php DEĞİŞTİRMEK DEĞİLDİ
          now() sorguya OFİSSİZ metin bağlıyor, PG oturum TimeZone'una
          göre yorumluyor → 15 dk rezervasyonlar ÜÇ SAAT kayardı
          (CLAUDE.md · WooCommerce #43593)
          KIRMA DENEMESİYLE ÖLÇÜLDÜ: app.timezone değişince hem yeni test
          hem 0. fazdan beri duran ZamanDilimiTest düştü
        ⚠️ okuma yolu BEYAZ LİSTE — geçersiz değerde setTimezone istisna
          fırlatır, müşteri kendi sipariş sayfasında 500 görürdü
        ⚠️ panel de MAĞAZANIN dilimini kullanıyor, personelin tarayıcısının
          değil — "sipariş saati" mağazaya ait bir olgu
        iki ekranda kopyalanan tarih() tek yere alındı

      ⏳ KALAN: ödeme akışının TÜNELDEN doğrulanması
        ERR_BLOCKED_BY_LOCAL_NETWORK_ACCESS_CHECKS BİZİM KUSURUMUZ DEĞİL
        ★ KOD DEĞİŞİKLİĞİ GEREKMİYOR (ölçüldü): dönüş adresi isteğin
          host'undan türüyor, tünelden girilince callback herkese açık
        ⚠️ 3DS SMS adımı ELLE — kullanıcıyla birlikte koşulacak

4.5N ✅ MARKA BAŞVURU / ONAY AKIŞI — 784 test
      self-servis kayıt (3D) markayı ANINDA YAYINA alıyordu

      ✅ YENİ DURUM: Pending — kurulu ama panel de vitrin de kapalı
        ⚠️ şema BAŞVURUDA kuruluyor, onayda değil: kurulum senkron ~280ms
          onay TEK SATIRLIK KARAR oluyor
        ⚠️ DENEME SÜRESİ ONAYDA BAŞLIYOR — başvuruda başlasaydı onayı üç
          gün süren marka 14 gününün beşte birini beklemekle geçirirdi
        ⚠️ M-1 BOZULMADI: kurulum hâlâ tamamen otomatik; onay bir kurulum
          adımı değil, bir KARAR

      ✅ DURUM MAKİNESİ — Larastan eksik geçişi DERHÂL yakaladı
        (GECISLER anahtarları tam biliniyor, 3C'de böyle kurulmuştu)
        pending'in YALNIZCA İKİ çıkışı: trial | closed
        ⚠️ Active yok — doğrudan yazılabilseydi yönetici ÖDEME ALMADAN
          markayı ücretli plana koymuş olurdu
        ⚠️ Suspended yok — askı YAYINDAKİ markayı durdurmak demek

      ✅ RED SİLMİYOR, closed + sebep: "neden reddedildi" cevabı kalmalı

      ✅ SERTİFİKA KAPISI DARALTILDI — ASIL KAZANÇ
        `ask` ucu yalnızca verified_at'e bakıyordu → onay bekleyen, hatta
        REDDEDİLMİŞ başvurunun alan adı sertifika alabilirdi
        ⚠️ kota HAFTADA 50 (3-K5); herkesin kota yakabilmesi ucu koyma
          gerekçesini boşa çıkarırdı

      ⚠️ CADDY'YE ELLE EKLEME GELİŞTİRMEDE DEVAM EDİYOR — DÜZELTİLEMEZ
        Let's Encrypt .localhost'a sertifika veremiyor
        ÜRETİMDE liste gerekmiyor: doğrulanmış + yayındaki alan adına
        Caddy sertifikayı kendisi alıyor

      DOĞRULANDI (gerçek curl)
        kayıt → pending, deneme boş, domain-check 404
        onayla → trial, deneme bitişi yazıldı, domain-check 200

4.5O ✅ SEPET/STOK HATALARININ SUNUMU — 787 test
      AYNI HATANIN DÖRDÜNCÜSÜ (4A kapalı mağaza · 4B ödeme dönüşü ·
      4.5G ödeme başlatma) — sepet ve stok istisnaları gözden kaçmıştı

      gerçek curl koşusunda çıktı:
        {"message":"'DC-1' için yeterli stok yok: 2 istendi, 1 kaldı."}
      müşteri ödeme düğmesine basınca bunu HAM görüyordu

      ✅ ÜÇ İSTİSNA DA AYRILDI
        InsufficientStock · CartNotOrderable · VariantNotPurchasable
        ⚠️ tarayıcı SEPETE yönlendiriliyor, back() ile ödemeye değil:
          sorunun düzeltilebileceği tek yer orası
        ⚠️ genel işleyici MERKEZ bağlamında da koşuyor, orada
          vitrin.sepet rotası YOK — route() çağrılsaydı İSTİSNA
          İŞLEYİCİSİNİN KENDİSİ patlardı; Route::has() ile korundu
        ⚠️ mesajın kendisi müşteriye gidiyor: SKU ve kalan adet zaten
          sepetinde gördüğü bilgi (4.5G'deki sağlayıcı mesajının aksine)
        ⚠️ ÜÇÜ BİRDEN: biri atlanırsa aynı hata BEŞİNCİ kez geri gelir

      ⚠️ TESTİ İLK YAZDIĞIMDA YANLIŞ ŞEYİ ÖLÇTÜM
        `stock` düşürüyordum → CartService::engeller()'e takılıyor,
        controller yakalıyor, ZATEN anlaşılır mesaj veriyor
        yani test DÜZELTMEDEN ÖNCE DE GEÇERDİ
        ham JSON'un yolu başka: stok VAR ama BAĞLI; sepet engel görmüyor,
        rezervasyon adımı patlıyor

      DOĞRULANDI (gerçek curl): 302 → /sepet, gövde HTML, JSON yok

4.5M-tünel ✅ KULLANICI DOĞRULADI
      make kaldir ile tünelden gerçek 3DS akışı koşuldu: SMS girildi,
      ödeme tamamlandı, vitrinde "ödendi" göründü
      ERR_BLOCKED_BY_LOCAL_NETWORK_ACCESS_CHECKS yalnızca .localhost
      erişiminin yan etkisiymiş; kod değişikliği gerekmedi

4.5P ✅ PANEL KATALOG CİLASI — 792 test

      ✅ 1) EKSEN DEĞERİ BOŞ → ANLAŞILMAZ HATA
        ConvertEmptyStringsToNull BEŞİNCİ KEZ: value="" → null →
        `string` düşüyor → "options.renk metin olmalıdır"
        ⚠️ UYARI EKRANDA HİÇ GÖRÜNMÜYORDU: anahtar `options.renk`,
          arayüz `errors.options` arıyordu → düğmeye basılıyor, hiçbir
          şey olmuyordu ("saçma sayfa" buydu)
        ⚠️ düğmeler kapatıldı: eksen kaydedilmeden "Ekle", eksen
          seçilmeden "Eksenleri kaydet" çalışmıyor
        ⚠️ bedeli ağırdı: eksensiz varyant eklenince eksenler ARTIK
          KİLİTLİ, marka çıkmaza giriyordu

      ⚠️ KIRMA DENEMESİ BİR KURALIN GEREKSİZ OLDUĞUNU GÖSTERDİ
        isteğe `required` eklemiştim, kaldırınca test YİNE GEÇTİ
        koruma orada değil DOMAIN'de (VariantService eksik ekseni
        zaten reddediyor) → kural çıkarıldı (4.5E "ölü koruma" dersi)

      ✅ 2) ÜRÜN OLUŞTURUNCA VARYANT/GÖRSEL — 4.5L'de düzeltilmişti,
        sunucu tarafı artık testle sabitlendi

      ✅ 3) ARAMA KELİME ORTASINDAN EŞLEŞİYORDU
        ILIKE '%kelime%' → "iş" araması "Tişört"ü getiriyordu
        title ~* '\mkelime' — \m kelime başı sınırı, ~* harf duyarsız
        ⚠️ yalnızca 'kelime%' olsaydı "Kahverengi Deri Çanta" ürünü
          "deri" aramasında HİÇ ÇIKMAZDI
        ⚠️ DESEN KAÇIRILIYOR: `.*` tüm kataloğu döndürürdü, `(` sorguyu
          PATLATIRDI — ikisinin de testi var
        ⚠️ başlıkla başlayanlar ÖNCE

      DOĞRULANDI (gerçek panel): q=iş → boş · q=cüz → Deri Cüzdan

4.5R ✅ ÖDEME DÖNÜŞ EKRANI — 797 test
      "ödeme yapıldı ama web in webde açılamayan bir sayfa çıktı"

      ARAŞTIRMA: iyzico iframe=true modunda ödeme bitince ÇERÇEVENİN
      İÇİNDE callbackUrl'e gidiyor ve token'ı POST GÖVDESİNDE yolluyor
      → çerçeveyi kapatıp sonucu göstermek BİZİM İŞİMİZ

      KUSUR BİZDEYDİ:
        sağlayıcı POST (referans GÖVDEDE)      → 200 ✅
        çerçeveden çıkış betiğinin gittiği GET → 404 ❌
        betik window.location.href'e çıkıyordu; referans gövdedeydi
        müşteri ÖDEMESİ BAŞARILI OLMASINA RAĞMEN 404 görüyordu

      ⚠️ SAHTE SAĞLAYICI BUNU GİZLEMİŞTİ — İKİNCİ KEZ (1E.7.3 ailesi)
        referansı ADRES ÇUBUĞUNA koyduğu için testler ?ref= ile koşuyor
        ve betik çalışıyordu; gerçek şekil (POST+gövde) hiç sınanmamıştı
        iki mevcut test kusuru gizliyordu, ikisi de güncellendi

      ✅ POST → 303 → İMZALI GET SAYFASI (/odeme/sonuc/{uuid})
        ⚠️ imzalı: uuid'i ele geçiren başkasının ödeme durumunu okurdu
        ⚠️ durum yine SİPARİŞTEN okunuyor (1E-K1)
        ⚠️ JSON dalı korundu: sağlayıcı sunucudan sunucuya da çağırıyor

      DOĞRULANDI (gerçek curl, iyzico şekliyle)
        POST token=… → 303 → imzalı adres → 200 "Siparişiniz alındı"
        imzasız aynı adres → 403
