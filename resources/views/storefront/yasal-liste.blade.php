@extends('storefront.layout')

@section('baslik', 'Yasal metinler — '.$tema['ad'])

@section('icerik')

    <article class="yasal">
        <h1>Yasal metinler</h1>

        {{-- ⚠️ Yayınlanmamış metin listede YOK: tıklayınca 404 alınırdı. --}}
        <ul class="yasal-liste">
            @foreach ($belgeler as $b)
                <li><a href="{{ route('vitrin.yasal', $b['tur']) }}">{{ $b['ad'] }}</a></li>
            @endforeach
        </ul>

        @if ($belgeler === [])
            <p class="bos">Bu mağaza henüz yasal metinlerini yayınlamadı.</p>
        @endif
    </article>

@endsection
