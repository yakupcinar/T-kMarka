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
                <input type="email" name="email" value="{{ $eposta }}" required>
            </label>

            <h2>Teslimat adresi</h2>

            @if ($adresler->isNotEmpty())
                {{--
                    ⚠️ KAYITLI ADRES VARSA ÖNCE O GÖSTERİLİYOR (4.5I).
                    Müşteri adres defterine adres kaydedip ödemeye gelince
                    aynı adresi baştan yazmak zorunda kalıyordu.

                    ⚠️ Adres defteri BOŞSA bu blok hiç çıkmıyor: seçilecek
                    bir şey yokken "kayıtlı adreslerim" başlığı göstermek
                    müşteriye kaybettiği bir şey olduğunu düşündürürdü.
                --}}
                <div class="adres-secim">
                    @foreach ($adresler as $a)
                        <label class="adres-kart">
                            <input
                                type="radio"
                                name="adres_uuid"
                                value="{{ $a->uuid }}"
                                @checked(old('adres_uuid', $loop->first ? $a->uuid : null) === $a->uuid)
                            >
                            <span>
                                <strong>{{ $a->title }}</strong> — {{ $a->full_name }}<br>
                                {{ $a->line1 }}{{ $a->line2 ? ', '.$a->line2 : '' }}<br>
                                {{ $a->district }} / {{ $a->city }}
                            </span>
                        </label>
                    @endforeach

                    {{--
                        ⚠️ "Başka adres" de bir RADYO — ayrı düğme değil.
                        Düğme olsaydı iki ayrı durum (seçili adres + açık
                        form) aynı anda var olabilir ve hangisinin
                        gönderileceği belirsiz kalırdı.
                    --}}
                    <label class="adres-kart">
                        <input type="radio" name="adres_uuid" value="" @checked(old('adres_uuid') === '')>
                        <span><strong>Başka adrese gönder</strong></span>
                    </label>
                </div>
            @endif

            {{--
                ⚠️ Form KAPALI DEĞİL, gizli: "başka adres" seçilince
                açılıyor. Sunucuda da kontrol var — gizlemek doğrulama
                değildir (4.5H.1'in dersi).
            --}}
            <div class="yeni-adres" @if ($adresler->isNotEmpty()) hidden @endif>

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

            @auth('customer-web')
                <label class="onay">
                    <input type="checkbox" name="adresi_kaydet" value="1" @checked(old('adresi_kaydet'))>
                    Bu adresi adres defterime kaydet
                </label>
            @endauth

            </div>{{-- .yeni-adres --}}

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
                        {{--
                            ⚠️ BURASI BİR HATAYDI (4.5A'da düzeltildi):
                            bağlantı `/api/legal/...` uçuna gidiyordu ve
                            müşteri HAM JSON görüyordu. Mesafeli satışta
                            müşterinin sözleşmeyi OKUYABİLMESİ zorunlu.
                        --}}
                        <a href="{{ route('vitrin.yasal', 'distance_sales') }}" target="_blank" rel="noopener">
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


{{--
        ⚠️ `required` alanlar GİZLİYKEN tarayıcı formu göndermiyor ve
        "odaklanılamayan alan doldurulmalı" diyerek SESSİZCE duruyor —
        müşteri neyin eksik olduğunu göremiyor. Bu yüzden zorunluluk
        görünürlükle birlikte açılıp kapanıyor.

        ⚠️ Betik çalışmazsa form AÇIK kalıyor (`hidden` kaldırılıyor):
        bozuk durumda müşteri adresini yine de yazabilmeli.
    --}}
    <script>
        (function () {
            var blok = document.querySelector('.yeni-adres')
            if (!blok) return

            var secimler = document.querySelectorAll('input[name="adres_uuid"]')
            if (secimler.length === 0) return

            var zorunlular = blok.querySelectorAll('[required]')

            function uygula() {
                var yeni = document.querySelector('input[name="adres_uuid"]:checked')
                var acik = yeni !== null && yeni.value === ''

                blok.hidden = !acik
                zorunlular.forEach(function (alan) { alan.required = acik })
            }

            secimler.forEach(function (s) { s.addEventListener('change', uygula) })
            uygula()
        })()
    </script>

@endsection
