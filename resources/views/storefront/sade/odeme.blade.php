@extends('storefront.layout')

@section('baslik', 'Ödeme — '.$tema['ad'])

@section('icerik')

    <h1>Ödeme</h1>

    <form method="post" action="{{ route('vitrin.odeme.gonder') }}" class="odeme-form">
        @csrf

        <div class="odeme-sol">
            <h2>İletişim</h2>

            {{--
                ⚠️ E-POSTA MİSAFİRDEN DE İSTENİYOR: sipariş durumu, kargo ve
                iade bildirimleri (2H) buraya gidiyor. Zorunlu ÜYELİK yok
                (M-1) ama iletişim adresi olmadan sipariş takip edilemez.
            --}}
            <label>E-posta
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>

            <h2>Teslimat adresi</h2>

            <label>Ad soyad
                <input type="text" name="shipping[full_name]" value="{{ old('shipping.full_name') }}" required>
            </label>

            <label>Telefon
                <input type="tel" name="shipping[phone]" value="{{ old('shipping.phone') }}" required>
            </label>

            <div class="ikili">
                <label>İl
                    <input type="text" name="shipping[city]" value="{{ old('shipping.city') }}" required>
                </label>

                <label>İlçe
                    <input type="text" name="shipping[district]" value="{{ old('shipping.district') }}" required>
                </label>
            </div>

            <label>Mahalle
                <input type="text" name="shipping[neighborhood]" value="{{ old('shipping.neighborhood') }}">
            </label>

            <label>Adres
                <input type="text" name="shipping[line1]" value="{{ old('shipping.line1') }}" required>
            </label>

            <label>Adres (devam)
                <input type="text" name="shipping[line2]" value="{{ old('shipping.line2') }}">
            </label>

            <label>Posta kodu
                <input type="text" name="shipping[postal_code]" value="{{ old('shipping.postal_code') }}">
            </label>

            <h2>Fatura <span class="ipucu">(kurumsal fatura istiyorsanız)</span></h2>

            <div class="ikili">
                <label>VKN / TCKN
                    <input type="text" name="billing_tax_number" value="{{ old('billing_tax_number') }}">
                </label>

                <label>Vergi dairesi
                    <input type="text" name="billing_tax_office" value="{{ old('billing_tax_office') }}">
                </label>
            </div>
        </div>

        <aside class="odeme-sag">
            <h2>Siparişiniz</h2>

            <table class="ozet">
                @foreach ($sepet->items as $satir)
                    <tr>
                        <td>{{ $satir->variant?->product?->title }} × {{ $satir->quantity }}</td>
                        <td class="sag">
                            {{ number_format((float) $satir->variant?->price * $satir->quantity, 2, ',', '.') }} TL
                        </td>
                    </tr>
                @endforeach
            </table>

            @if ($sozlesme === null)
                {{--
                    ⚠️ SÖZLEŞME YOKSA SİPARİŞ VERİLEMEZ ve sebebi açıkça
                    yazılıyor. Düğmeyi sessizce gizlemek, müşteriyi neden
                    devam edemediğini bilmeden bırakmak olurdu.
                --}}
                <p class="bildirim kotu">
                    Bu mağazanın satış sözleşmesi yayınlanmamış; sipariş alınamıyor.
                </p>
            @else
                <input type="hidden" name="legal_version_id" value="{{ $sozlesme->id }}">

                <label class="onay">
                    <input type="checkbox" name="sozlesme_onay" value="1" required>
                    <span>
                        <a href="{{ url('/api/legal/distance_sales') }}" target="_blank" rel="noopener">
                            Mesafeli satış sözleşmesini
                        </a>
                        okudum, onaylıyorum.
                    </span>
                </label>

                <button class="dugme buyuk" type="submit">Ödemeye geç</button>

                <p class="ipucu">Ödeme sayfasına yönlendirileceksiniz.</p>
            @endif
        </aside>
    </form>

@endsection
