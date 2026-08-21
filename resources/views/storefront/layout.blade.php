{{--
    Vitrin düzeni — MARKANIN sitesi, bizim değil. (4A)

    ⚠️ Bu dosyayı marka DÜZENLEYEMEZ (4-K5). Blade PHP'dir ve kum havuzu
    yoktur; markanın yazdığı şablonu render etmek uzaktan kod çalıştırmadır
    ve şema bazlı kiracılıkta bedeli BÜTÜN markaların verisidir.
    Marka yalnızca `settings.theme` üzerinden AYAR seçiyor.

    ⚠️ Aşağıdaki her `$tema` değeri [ThemeSettings]'ten geçmiş, yani
    doğrulanmış. Ayarı doğrudan okuyup buraya basmak, kapattığımız kapının
    yanındaki pencereyi açmak olurdu.
--}}
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- ⚠️ Başlık MARKANIN adı. Vitrin markanın sitesi; bizim adımız geçmiyor. --}}
    <title>@yield('baslik', $tema['ad'])</title>

    <meta name="description" content="@yield('aciklama', $tema['ad'])">

    <style>
        /*
        | ⚠️ Renk ve yazı tipi CSS değişkeni olarak giriyor — kural
        | gövdesine değil. Değer yine de [ThemeSettings]'te kalıba
        | uydurulmuş durumda; burası ikinci kapı, tek kapı değil.
        */
        :root {
            --marka: {{ $tema['renk'] }};
            --yazi: {{ $tema['yazi_tipi'] }};
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: var(--yazi);
            color: #1c1917;
            background: #fafaf9;
            line-height: 1.6;
        }

        .kapsa { max-width: 1100px; margin: 0 auto; padding: 0 20px; }

        header {
            background: #fff;
            border-bottom: 1px solid #e7e5e4;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        header .kapsa {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-top: 16px;
            padding-bottom: 16px;
        }

        .logo {
            font-size: 20px;
            font-weight: 800;
            color: var(--marka);
            text-decoration: none;
        }

        .logo img { height: 34px; display: block; }

        .ara { margin-left: auto; display: flex; gap: 8px; }

        .ara input {
            padding: 8px 12px;
            border: 1px solid #d6d3d1;
            border-radius: 8px;
            font: inherit;
            min-width: 200px;
        }

        .dugme {
            background: var(--marka);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 8px 16px;
            font: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .sepet { text-decoration: none; color: #1c1917; font-weight: 600; white-space: nowrap; }

        .sepet span {
            background: var(--marka);
            color: #fff;
            border-radius: 999px;
            padding: 1px 8px;
            font-size: 13px;
            margin-left: 4px;
        }

        .izgara {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            padding: 32px 0;
        }

        .kart {
            background: #fff;
            border: 1px solid #e7e5e4;
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }

        .kart img { width: 100%; aspect-ratio: 1; object-fit: cover; background: #f5f5f4; display: block; }

        /*
        | ⚠️ GÖRSELSİZ ÜRÜN için gerçek bir yer tutucu. Önce boş bir SVG
        | veri adresi basılıyordu ve tarayıcı KIRIK KARE çiziyordu —
        | müşteriye "bir şey yüklenemedi" izlenimi veriyordu.
        */
        .kart .yok, .bos-gorsel {
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #f5f5f4, #e7e5e4);
            color: #a8a29e;
            font-size: 13px;
        }
        .kart .govde { padding: 12px; display: flex; flex-direction: column; gap: 4px; }
        .kart .ad { font-weight: 600; font-size: 15px; }
        .kart .fiyat { color: var(--marka); font-weight: 700; }

        .bos { padding: 64px 0; text-align: center; color: #78716c; }

        /* GÖRSEL İYİLEŞTİRME (4.5F) — yeni yapı değil, mevcut yapının cilası */
        .kart { transition: box-shadow .15s, transform .15s; }
        .kart:hover { box-shadow: 0 6px 20px rgb(0 0 0 / .08); transform: translateY(-2px); }
        .dugme { transition: filter .15s; }
        .dugme:hover { filter: brightness(.93); }
        .dugme:disabled { opacity: .6; cursor: not-allowed; }

        /*
        | ⚠️ ODAK HALKASI görünür bırakılıyor. `outline: none` yazmak
        | sayfayı "temiz" gösterir ama klavyeyle gezen kullanıcı nerede
        | olduğunu göremez — erişilebilirlik kaybı.
        */
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible {
            outline: 2px solid var(--marka);
            outline-offset: 2px;
        }

        h1, h2 { line-height: 1.25; }
        table { border-collapse: collapse; }

        /* ÜRÜN SAYFASI */
        .urun { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; padding: 32px 0; }
        .urun-gorsel { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 12px; background: #f5f5f4; }
        .bos-gorsel { display: grid; place-items: center; color: #a8a29e; }
        .marka-adi { color: #78716c; margin-top: -8px; }
        .fiyat-buyuk { font-size: 28px; font-weight: 800; color: var(--marka); }
        .ekle-form { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin: 20px 0; }
        .ekle-form label { display: flex; flex-direction: column; gap: 4px; font-size: 14px; }
        .ekle-form input, .ekle-form select { padding: 8px; border: 1px solid #d6d3d1; border-radius: 8px; font: inherit; }
        .ekle-form input[type=number] { width: 80px; }
        .dugme.buyuk { padding: 12px 24px; font-size: 16px; font-weight: 600; }
        .aciklama { margin-top: 24px; color: #44403c; }

        /* SEPET */
        /* ⚠️ Form içindeki eylem BAĞLANTI GİBİ görünüyor: "iptal et"
           yıkıcı bir düğme gibi durmasın ama tıklanabilir olduğu belli
           olsun. GET bağlantısı yapılamaz — iptal veri değiştiriyor. */
        .baglanti-dugme {
            background: none; border: 0; padding: 0; font: inherit;
            color: #c2410c; cursor: pointer; text-decoration: underline;
        }

        /* VARYANT SEÇİCİSİ (4.6A) */
        .varyant-secici { margin: 16px 0; }
        .eksen { margin-bottom: 12px; }
        .eksen-ad { display: block; font-size: 14px; color: #57534e; margin-bottom: 6px; }
        .kutucuklar { display: flex; flex-wrap: wrap; gap: 8px; }
        .kutucuk {
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid #d6d3d1; background: #fff; color: #1c1917;
            border-radius: 8px; padding: 8px 14px; font: inherit; cursor: pointer;
        }
        .kutucuk.secili { border-color: #1c1917; box-shadow: inset 0 0 0 1px #1c1917; }

        /* ⚠️ Tükenen değer GİZLENMİYOR, kapatılıyor: müşteri o seçeneğin
           var olduğunu ama şu an alınamadığını görmeli. */
        .kutucuk:disabled, .kutucuk.tukendi {
            opacity: .45; cursor: not-allowed; text-decoration: line-through;
        }
        .kutucuk .renk {
            width: 14px; height: 14px; border-radius: 50%;
            border: 1px solid rgba(0,0,0,.2); display: inline-block;
        }

        .sepet-tablo { width: 100%; border-collapse: collapse; margin: 24px 0; }
        .sepet-tablo td { border-bottom: 1px solid #e7e5e4; padding: 14px 8px; vertical-align: top; }
        .sepet-tablo tr.olu { opacity: .55; }
        .satir-form { display: flex; gap: 6px; }
        .satir-form input { width: 64px; padding: 6px; border: 1px solid #d6d3d1; border-radius: 6px; font: inherit; }
        .satir-form button, .sil { padding: 6px 10px; border: 1px solid #d6d3d1; background: #fff; border-radius: 6px; cursor: pointer; font: inherit; }
        .sil { color: #b91c1c; }
        .uyari { color: #b45309; font-size: 13px; margin-top: 4px; }
        .kupon-form { display: flex; gap: 8px; align-items: center; margin: 20px 0; flex-wrap: wrap; }
        .kupon-form input { padding: 8px 12px; border: 1px solid #d6d3d1; border-radius: 8px; font: inherit; }
        .ipucu { color: #78716c; font-size: 13px; }
        .engel-kutusu { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 10px; padding: 14px 18px; margin: 20px 0; }

        /* BİLDİRİMLER */
        .bildirim { padding: 12px 18px; border-radius: 10px; margin: 16px 0; }
        .bildirim.iyi { background: #dcfce7; border: 1px solid #86efac; }
        .bildirim.kotu { background: #fee2e2; border: 1px solid #fca5a5; }

        /* ÖDEME */
        .odeme-form { display: grid; grid-template-columns: 1.4fr 1fr; gap: 32px; padding: 24px 0; align-items: start; }
        .odeme-form h2 { font-size: 17px; margin: 24px 0 8px; }
        .odeme-form label { display: flex; flex-direction: column; gap: 4px; font-size: 14px; margin-bottom: 12px; }
        .odeme-form input[type=text], .odeme-form input[type=email], .odeme-form input[type=tel] {
            padding: 9px 12px; border: 1px solid #d6d3d1; border-radius: 8px; font: inherit;
        }
        .ikili { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .odeme-sag { background: #fff; border: 1px solid #e7e5e4; border-radius: 12px; padding: 20px; position: sticky; top: 90px; }
        .ozet { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .ozet td { padding: 6px 0; border-bottom: 1px solid #f5f5f4; font-size: 14px; }
        .ozet .sag { text-align: right; white-space: nowrap; }
        .onay { flex-direction: row !important; align-items: flex-start; gap: 8px; margin: 16px 0; }
        .onay input { margin-top: 4px; }

        /* "VİTRİNLİ" DÜZENİ (4G) — yalnızca yerleşim farkı, ayrı tema değil */
        .karsilama { padding: 48px 0 8px; text-align: center; }
        .karsilama h1 { font-size: 34px; margin: 0 0 8px; }
        .karsilama p { color: #78716c; margin: 0; }
        .urun-genis { grid-template-columns: 1fr; max-width: 720px; margin: 0 auto; }

        /* MÜŞTERİ HESABI (4.5D) */
        .hesap { max-width: 900px; margin: 0 auto; padding: 24px 0; }
        .hesap-dar { max-width: 460px; margin: 0 auto; padding: 24px 0; }
        .hesap-dar label, .hesap-adres label { display: flex; flex-direction: column; gap: 4px; font-size: 14px; margin-bottom: 12px; }
        .hesap-dar input { padding: 9px 12px; border: 1px solid #d6d3d1; border-radius: 8px; font: inherit; }
        .hesap-bas { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .hesap-cikis { margin-left: auto; }
        .onay-satiri { flex-direction: row !important; align-items: flex-start; gap: 8px; }
        .adres-kart { border: 1px solid #e7e5e4; border-radius: 10px; padding: 14px; margin-bottom: 12px; background: #fff; }
        .adres-baslik { display: inline-block; background: #f5f5f4; border-radius: 999px; padding: 1px 10px; font-size: 12px; margin-bottom: 4px; }

        /* YASAL METİN (4.5A) */
        .yasal { max-width: 760px; margin: 0 auto; padding: 32px 0; }
        .yasal h1 { font-size: 26px; }
        .yasal-surum { color: #78716c; font-size: 14px; margin-top: -8px; }
        .yasal-metin { margin-top: 20px; color: #292524; }
        .yasal-liste { line-height: 2.2; }
        footer a { color: inherit; }

        /* GÖMÜLÜ ÖDEME (4.5-K1) */
        .odeme-gomulu { max-width: 760px; margin: 0 auto; padding: 24px 0; }
        .odeme-cercevesi { width: 100%; min-height: 640px; border: 1px solid #e7e5e4; border-radius: 12px; background: #fff; }

        /* SONUÇ */
        .sonuc { padding: 56px 0; text-align: center; }
        .siparis-no { font-size: 17px; }

        @media (max-width: 720px) {
            .urun { grid-template-columns: 1fr; }
            .odeme-form { grid-template-columns: 1fr; }
            .ikili { grid-template-columns: 1fr; }
        }

        footer {
            border-top: 1px solid #e7e5e4;
            margin-top: 48px;
            padding: 24px 0;
            color: #78716c;
            font-size: 14px;
        }
    </style>
</head>
<body>

<header>
    <div class="kapsa">
        <a class="logo" href="{{ route('vitrin.anasayfa') }}">
            @if ($tema['logo'])
                {{-- ⚠️ `alt` mağaza adı: logo yüklenmezse marka yine görünür. --}}
                <img src="{{ $tema['logo'] }}" alt="{{ $tema['ad'] }}">
            @else
                {{ $tema['ad'] }}
            @endif
        </a>

        {{-- ⚠️ Koleksiyon YOKSA bağlantı da yok: boş sayfaya götüren bir
             menü maddesi müşteriyi yanıltırdı. --}}
        @if (($koleksiyonVar ?? false))
            <a class="sepet" href="{{ route('vitrin.koleksiyonlar') }}">Koleksiyonlar</a>
        @endif

        <form class="ara" method="get" action="{{ route('vitrin.anasayfa') }}">
            <input type="search" name="q" value="{{ $arama ?? '' }}" placeholder="Ürün ara" aria-label="Ürün ara">
            <button class="dugme" type="submit">Ara</button>
        </form>

        {{--
            ⚠️ Sepet sayısı SUNUCUDA yazılıyor — bu 4A'nın çerez kararının
            (CartToken) tek görünür sebebi. Başlık tek yol olarak kalsaydı
            burada her zaman 0 yazardı.
        --}}
        {{--
            ⚠️ Bağlantı GİRİŞ DURUMUNA göre değişiyor: giriş yapmış
            müşteriye "Giriş yap" göstermek, yapmamışa "Hesabım"
            göstermek kadar yanlış olurdu.
        --}}
        @auth('customer-web')
            <a class="sepet" href="{{ route('vitrin.hesap') }}">Hesabım</a>
        @else
            <a class="sepet" href="{{ route('vitrin.giris') }}">Giriş</a>
        @endauth

        <a class="sepet" href="{{ route('vitrin.sepet') }}">
            Sepet @if ($sepetAdedi > 0)<span>{{ $sepetAdedi }}</span>@endif
        </a>
    </div>
</header>

<main class="kapsa">

    {{--
        ⚠️ Bildirimler DÜZENDE, sayfalarda değil: PRG deseninde her işlem
        yönlendirmeyle bitiyor ve sonucu gösterecek tek ortak yer burası.
        Her sayfaya ayrı yazılsaydı biri unutulur ve o işlem sessizce
        "hiçbir şey olmamış" gibi görünürdü.
    --}}
    @if (session('mesaj'))
        <p class="bildirim iyi">{{ session('mesaj') }}</p>
    @endif

    @if (session('hata'))
        <p class="bildirim kotu">{{ session('hata') }}</p>
    @endif

    {{--
        ⚠️ `$errors` VARLIĞI KONTROL EDİLİYOR — ve bu gerçek bir 500'den
        sonra eklendi.

        `$errors` görünümlere `ShareErrorsFromSession` tarafından veriliyor
        ve o middleware yalnızca `web` grubunda çalışıyor. Ödeme dönüşü
        ekranı (4B) `api` grubunda — sağlayıcı POST ettiği için oraya
        taşınamıyor — ve aynı düzeni kullanıyor. Kontrolsüz hâlde
        "Undefined variable $errors" ile 500 veriyordu: müşteri ödemesini
        bitirmiş, dönüş ekranında hata sayfası görüyordu.
    --}}
    @if (isset($errors) && $errors->any())
        <div class="bildirim kotu">
            @foreach ($errors->all() as $hata)
                <div>{{ $hata }}</div>
            @endforeach
        </div>
    @endif

    @yield('icerik')
</main>

<footer>
    <div class="kapsa">
        {{-- ⚠️ Yıl sabit yazılmıyor: her 1 Ocak'ta eskimiş bir sayfa olurdu. --}}
        © {{ date('Y') }} {{ $tema['ad'] }}

        {{--
            ⚠️ Yasal metinlere HER SAYFADAN erişilebilmeli: müşteri
            sözleşmeyi yalnızca ödeme anında değil, öncesinde de
            okuyabilmeli.
        --}}
        · <a href="{{ route('vitrin.yasal.liste') }}">Yasal metinler</a>
    </div>
</footer>

</body>
</html>
