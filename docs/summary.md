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
```
