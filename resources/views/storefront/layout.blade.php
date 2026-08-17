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

        .kart img { width: 100%; aspect-ratio: 1; object-fit: cover; background: #f5f5f4; }
        .kart .govde { padding: 12px; display: flex; flex-direction: column; gap: 4px; }
        .kart .ad { font-weight: 600; font-size: 15px; }
        .kart .fiyat { color: var(--marka); font-weight: 700; }

        .bos { padding: 64px 0; text-align: center; color: #78716c; }

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

        <form class="ara" method="get" action="{{ route('vitrin.anasayfa') }}">
            <input type="search" name="q" value="{{ $arama ?? '' }}" placeholder="Ürün ara" aria-label="Ürün ara">
            <button class="dugme" type="submit">Ara</button>
        </form>

        {{--
            ⚠️ Sepet sayısı SUNUCUDA yazılıyor — bu 4A'nın çerez kararının
            (CartToken) tek görünür sebebi. Başlık tek yol olarak kalsaydı
            burada her zaman 0 yazardı.
        --}}
        <a class="sepet" href="{{ route('vitrin.anasayfa') }}">
            Sepet @if ($sepetAdedi > 0)<span>{{ $sepetAdedi }}</span>@endif
        </a>
    </div>
</header>

<main class="kapsa">
    @yield('icerik')
</main>

<footer>
    <div class="kapsa">
        {{-- ⚠️ Yıl sabit yazılmıyor: her 1 Ocak'ta eskimiş bir sayfa olurdu. --}}
        © {{ date('Y') }} {{ $tema['ad'] }}
    </div>
</footer>

</body>
</html>
