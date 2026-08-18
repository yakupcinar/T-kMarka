{{--
    Kontrol düzleminin kök görünümü. (4F)

    ⚠️ MARKA PANELİNDEN AYRI DOSYA ve AYRI PAKET (`platform.js`).
    Tek paket paylaşsalardı marka personelinin tarayıcısına kontrol
    düzleminin bütün ekran kodu inerdi — çalıştıramasa bile HANGİ
    işlemlerin var olduğunu okuyabilirdi.

    ⚠️ SSR YOK (4-K2), arama motoruna KAPALI.
--}}
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title inertia>TıkMarka Yönetim</title>

    @vite(['resources/js/platform.js'])
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
