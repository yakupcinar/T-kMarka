{{--
    "vitrinli" düzeni — ana sayfa. (4G)

    ⚠️ İKİNCİ DÜZEN. 4A'da `DUZENLER` listesi tek elemanlıydı ve "sonradan
    eklemek, kavramı sonradan icat etmekten kolay" diye bırakılmıştı.
    Buradaki tek fark ana sayfanın karşılama bölümü ve daha geniş kartlar —
    ürün, sepet ve ödeme sayfaları `sade` ile aynı dosyalar.

    ⚠️ Marka bu dosyayı DÜZENLEYEMEZ (4-K5); yalnızca hangi düzenin
    kullanılacağını SEÇER.
--}}
@extends('storefront.layout')

@section('baslik', $arama ? $arama.' — '.$tema['ad'] : $tema['ad'])

@section('icerik')

    {{-- Karşılama bölümü — `sade` düzeninde yok. --}}
    @unless ($arama)
        <div class="karsilama">
            <h1>{{ $tema['ad'] }}</h1>
            <p>Seçilmiş ürünler, doğrudan üreticiden.</p>
        </div>
    @endunless

    @if ($arama)
        <p style="padding-top:24px">
            <strong>“{{ $arama }}”</strong> için {{ $urunler->count() }} sonuç
            · <a href="{{ route('vitrin.anasayfa') }}">aramayı temizle</a>
        </p>
    @endif

    @if ($urunler->isEmpty())
        <p class="bos">
            @if ($arama)
                Aradığınız ürün bulunamadı.
            @else
                {{-- ⚠️ Mağaza AÇIK ama ürünü yok: müşteriye hata gibi görünmemeli. --}}
                Bu mağazada henüz ürün yok.
            @endif
        </p>
    @else
        <div class="izgara">
            @foreach ($urunler as $urun)
                <a class="kart" href="{{ route('vitrin.urun', $urun->slug) }}">
                    @if ($urun->images->first())
                        <img src="{{ $urun->images->first()->url() }}" alt="{{ $urun->title }}">
                    @else
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="">
                    @endif

                    <div class="govde">
                        <span class="ad">{{ $urun->title }}</span>

                        {{--
                            ⚠️ Fiyat EN DÜŞÜK varyanttan. Tek fiyat yazılsaydı
                            çok varyantlı üründe hangi fiyatın gösterildiği
                            rastgele olurdu.
                        --}}
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
