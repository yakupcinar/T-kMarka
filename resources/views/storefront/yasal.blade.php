{{--
    Yasal metin sayfası. (4.5A)

    ⚠️ DÜZENE GÖRE KOPYASI YOK (`sade`/`vitrinli` ayrımı): yasal metin bir
    görünüm tercihi değil, yasal bir yükümlülük. Kopyalansaydı iki dosya
    arasında bir gün fark oluşabilir ve müşteri seçilen düzene göre farklı
    bir metin görebilirdi.
--}}
@extends('storefront.layout')

@section('baslik', $belge['ad'].' — '.$tema['ad'])

@section('icerik')

    <article class="yasal">
        <h1>{{ $belge['ad'] }}</h1>

        {{--
            ⚠️ SÜRÜM VE TARİH gösteriliyor: müşteri hangi metni okuduğunu,
            marka hangi metnin yürürlükte olduğunu tartışmasız bilmeli
            (1A.4 · 1D-K2).
        --}}
        <p class="yasal-surum">
            Sürüm {{ $belge['surum'] }}@if ($belge['tarih']) · {{ $belge['tarih'] }} tarihinde yayınlandı@endif
        </p>

        {{--
            ⚠️ `{!! !!}` DEĞİL `{{ }}` + `nl2br`. Metni marka yazıyor ve
            ham HTML olarak basılsaydı marka kendi vitrinine betik
            gömebilirdi — 4-K5'in kapattığı kapının aynısı.
        --}}
        <div class="yasal-metin">{!! nl2br(e($belge['icerik'])) !!}</div>
    </article>

@endsection
