{{--
    "sade" düzeni — ana sayfa. (4A)

    ⚠️ Klasör adı `settings.theme.layout` değerinden geliyor ([ThemeSettings]).
    Bugün tek düzen var; 4G'de ikincisi eklendiğinde yapılacak tek şey yeni
    bir klasör açmak ve listeye adını yazmak olacak.
--}}
@extends('storefront.layout')

@section('baslik', $arama ? $arama.' — '.$tema['ad'] : $tema['ad'])

@section('icerik')

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
                        {{--
                            ⚠️ Boş SVG veri adresi yerine GERÇEK yer tutucu:
                            tarayıcı boş görseli kırık kare olarak çiziyordu
                            ve müşteriye "yüklenemedi" izlenimi veriyordu.
                        --}}
                        <div class="yok">Görsel yok</div>
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
