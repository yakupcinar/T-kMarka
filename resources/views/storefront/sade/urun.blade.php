@extends('storefront.layout')

@section('baslik', $urun->title.' — '.$tema['ad'])
{{--
    ⚠️ `@section('ad', ifade)` KISA BİÇİMİ KULLANILMIYOR.
    Blade argümanları virgülden bölüyor; içinde virgül olan bir fonksiyon
    çağrısı (`Str::limit(..., 150)`) yanlış ayrışıyor ve görünüm derlenemez
    hâle geliyor. Belirtisi sinsi: sayfa çalışıyor gibi görünüyor ama
    Larastan görünümü bulamıyor. Blok biçimi bu sorunu yaşamıyor.
--}}
@section('aciklama')
    {{ \Illuminate\Support\Str::limit(strip_tags((string) $urun->description), 150) }}
@endsection

@section('icerik')

    <div class="urun">
        <div>
            @if ($urun->images->first())
                <img class="urun-gorsel" src="{{ $urun->images->first()->url() }}" alt="{{ $urun->title }}">
            @else
                <div class="urun-gorsel bos-gorsel">Görsel yok</div>
            @endif
        </div>

        <div>
            <h1>{{ $urun->title }}</h1>

            @if ($urun->brand)
                <p class="marka-adi">{{ $urun->brand }}</p>
            @endif

            @if ($urun->variants->isEmpty())
                {{-- ⚠️ Varyantsız ürün satılamaz; "sepete ekle" gösterilmiyor. --}}
                <p class="bos">Bu ürün şu anda satışta değil.</p>
            @else
                <p class="fiyat-buyuk">
                    {{ number_format((float) $urun->variants->min('price'), 2, ',', '.') }} TL
                </p>

                <form method="post" action="{{ route('vitrin.sepet.ekle') }}" class="ekle-form">
                    {{--
                        ⚠️ CSRF alanı ZORUNLU: bu rota `web` grubunda ve koruma
                        İSTENİYOR (4-K4). 3C'de aynı koruma yanlış yerde olduğu
                        için sorun çıkarmıştı; burası doğru yeri.
                    --}}
                    @csrf

                    @if ($urun->variants->count() > 1)
                        <label>
                            Seçenek
                            <select name="variant_uuid" required>
                                @foreach ($urun->variants as $varyant)
                                    <option value="{{ $varyant->uuid }}">
                                        {{ collect($varyant->options)->implode(' · ') ?: $varyant->sku }}
                                        — {{ number_format((float) $varyant->price, 2, ',', '.') }} TL
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        <input type="hidden" name="variant_uuid" value="{{ $urun->variants->first()->uuid }}">
                    @endif

                    <label>
                        Adet
                        <input type="number" name="quantity" value="1" min="1"
                               max="{{ \App\Domain\Cart\CartService::MAKS_ADET }}">
                    </label>

                    <button class="dugme buyuk" type="submit">Sepete ekle</button>
                </form>
            @endif

            @if ($urun->description)
                <div class="aciklama">{!! nl2br(e($urun->description)) !!}</div>
            @endif
        </div>
    </div>

@endsection
