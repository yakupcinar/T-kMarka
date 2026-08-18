@extends('storefront.layout')

@section('baslik', 'Sepet — '.$tema['ad'])

@section('icerik')

    <h1>Sepetim</h1>

    @if ($sepet === null || $sepet->items->isEmpty())
        <p class="bos">
            Sepetiniz boş. <a href="{{ route('vitrin.anasayfa') }}">Alışverişe başlayın</a>.
        </p>
    @else
        <table class="sepet-tablo">
            @foreach ($sepet->items as $satir)
                <tr class="{{ $satir->kullanilabilirMi() ? '' : 'olu' }}">
                    <td>
                        <strong>{{ $satir->variant?->product?->title ?? 'Ürün' }}</strong>

                        {{--
                            ⚠️ ÖLÜ SATIR SİLİNMİYOR, İŞARETLENİYOR (1C-K2).
                            Sessizce silinseydi müşteri ne kaybettiğini bilmezdi.
                        --}}
                        @if (! $satir->kullanilabilirMi())
                            <div class="uyari">Bu ürün artık satışta değil.</div>
                        @elseif (! $satir->stokYetiyorMu())
                            <div class="uyari">Stok yetersiz.</div>
                        @endif
                    </td>

                    <td>{{ number_format((float) $satir->variant?->price, 2, ',', '.') }} TL</td>

                    <td>
                        <form method="post" action="{{ route('vitrin.sepet.guncelle') }}" class="satir-form">
                            @csrf
                            <input type="hidden" name="variant_uuid" value="{{ $satir->variant?->uuid }}">
                            <input type="number" name="quantity" value="{{ $satir->quantity }}"
                                   min="0" max="{{ \App\Domain\Cart\CartService::MAKS_ADET }}">
                            <button type="submit">Güncelle</button>
                        </form>
                    </td>

                    <td>
                        <form method="post" action="{{ route('vitrin.sepet.sil') }}">
                            @csrf
                            <input type="hidden" name="variant_uuid" value="{{ $satir->variant?->uuid }}">
                            <button type="submit" class="sil">Çıkar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>

        <form method="post" action="{{ route('vitrin.sepet.kupon') }}" class="kupon-form">
            @csrf
            <input type="text" name="kod" value="{{ $sepet->coupon_code }}" placeholder="Kupon kodu">
            <button class="dugme" type="submit">Uygula</button>
            @if ($sepet->coupon_code)
                <span class="ipucu">Boş bırakıp uygularsanız kupon kalkar.</span>
            @endif
        </form>

        @if ($engeller !== [])
            {{--
                ⚠️ ENGELLER AÇIKÇA YAZILIYOR. "Ödemeye geç" düğmesini sessizce
                gizlemek, müşteriye neyin eksik olduğunu söylemeden yolu
                kapatmak olurdu.
            --}}
            <div class="engel-kutusu">
                <strong>Siparişi tamamlamadan önce:</strong>
                <ul>
                    @foreach ($engeller as $engel)
                        <li><code>{{ $engel['sku'] }}</code> — {{ $engel['sorun'] }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <p><a class="dugme buyuk" href="{{ route('vitrin.odeme') }}">Ödemeye geç</a></p>
        @endif
    @endif

@endsection
