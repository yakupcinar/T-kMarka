@extends('storefront.layout')

@section('baslik', 'Koleksiyonlar — '.$tema['ad'])

@section('icerik')

    <h1>Koleksiyonlar</h1>

    @if ($koleksiyonlar->isEmpty())
        {{-- ⚠️ Boş liste hata gibi görünmemeli: yeni mağazada NORMAL. --}}
        <p class="bos">Bu mağazada henüz koleksiyon yok.</p>
    @else
        <div class="izgara">
            @foreach ($koleksiyonlar as $k)
                <a class="kart" href="{{ route('vitrin.koleksiyon', $k->slug) }}">
                    <div class="yok">{{ $k->title }}</div>
                    <div class="govde">
                        <span class="ad">{{ $k->title }}</span>
                        @if ($k->description)
                            <span class="ipucu">{{ \Illuminate\Support\Str::limit($k->description, 60) }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

@endsection
