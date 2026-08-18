{{--
    Panelin kök görünümü — Inertia buradan başlıyor. (4C)

    ⚠️ VİTRİNDEN AYRI DOSYA. Vitrin sunucuda render edilen Blade (4-K1),
    panel Inertia + Vue. Tek düzen paylaşsalardı biri diğerinin ihtiyacına
    göre bozulurdu: vitrin JS'siz çalışmak zorunda, panel değil.

    ⚠️ SSR YOK (4-K2). `@inertia` yalnızca boş kabı basıyor; sayfa
    tarayıcıda render ediliyor. Panelde SEO gerekmediği için kaybımız yok,
    kazancımız paylaşılan Node sürecinin hiç var olmaması.
--}}
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- ⚠️ Panel ARAMA MOTORUNA KAPALI: özel bir çalışma alanı. --}}
    <meta name="robots" content="noindex, nofollow">

    <title inertia>Panel</title>

    @vite(['resources/js/panel.js'])
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
