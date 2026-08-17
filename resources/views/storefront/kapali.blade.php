{{--
    Mağaza kapalı — 503 ile birlikte dönüyor. (4A)

    ⚠️ Bu sayfa CONTROLLER'DAN değil MIDDLEWARE'den geliyor
    ([RequirePublishedStore]). Marka mağazasını kapattığında müşterinin
    gördüğü tek şey bu.

    ⚠️ Önceden burası ham JSON'du — API için doğruydu, tarayıcıdaki müşteri
    için değil: ekranda süslü parantezli bir metin görünürdü.
--}}
@extends('storefront.layout')

@section('baslik', $tema['ad'].' — şu anda kapalı')

@section('icerik')

    <div class="bos">
        <h1 style="color:#1c1917">Mağaza şu anda kapalı</h1>

        {{--
            ⚠️ SEBEP YAZILMIYOR. "Marka aboneliğini yenilemedi" ya da
            "ayarları eksik" demek markanın iç durumunu müşteriye ifşa
            ederdi. Müşterinin bilmesi gereken tek şey ne zaman dönebileceği.
        --}}
        <p>{{ $tema['ad'] }} kısa süre içinde tekrar hizmete girecek.</p>
    </div>

@endsection
