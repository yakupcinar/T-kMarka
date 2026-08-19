@extends('storefront.layout')

@section('baslik', $koleksiyon->title.' — '.$tema['ad'])

@section('aciklama')
    {{ \Illuminate\Support\Str::limit(strip_tags((string) $koleksiyon->description), 150) ?: $koleksiyon->title }}
@endsection

@section('icerik')

    <h1>{{ $koleksiyon->title }}</h1>

    @if ($koleksiyon->description)
        <p class="ipucu">{{ $koleksiyon->description }}</p>
    @endif

    @if ($urunler->isEmpty())
        {{--
            ⚠️ KURALLI koleksiyonda bu durum NORMAL olabilir: kurala uyan
            ürün kalmamıştır. Hata gibi göstermek marka ve müşteriyi
            yanıltırdı.
        --}}
        <p class="bos">Bu koleksiyonda şu anda ürün yok.</p>
    @else
        <div class="izgara">
            @foreach ($urunler as $urun)
                <a class="kart" href="{{ route('vitrin.urun', $urun->slug) }}">
                    @if ($urun->images->first())
                        <img src="{{ $urun->images->first()->url() }}" alt="{{ $urun->title }}">
                    @else
                        <div class="yok">Görsel yok</div>
                    @endif

                    <div class="govde">
                        <span class="ad">{{ $urun->title }}</span>
                        @if ($urun->variants->isNotEmpty())
                            <span class="fiyat">
                                {{ number_format((float) $urun->variants->min('price'), 2, ',', '.') }} TL
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

@endsection
