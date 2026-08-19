{{--
    Gömülü ödeme adımı — kart formu IFRAME içinde. (4.5-K1)

    ⚠️ Müşteri SİTEDEN AYRILMIYOR ama kart verisi bize HİÇ UĞRAMIYOR:
    iframe'in içeriği tamamen sağlayıcının kökeninde. Bizim sayfamız
    yalnızca çerçeveyi çiziyor.

    ⚠️ Sağlayıcının hazır BETİĞİ (`checkoutFormContent`) kullanılmıyor;
    o betik sağlayıcının JavaScript'ini BİZİM kökenimizde çalıştırırdı.
    Gerekçenin tamamı [PaymentInitiation::gomulebilirMi]'de.
--}}
@extends('storefront.layout')

@section('baslik', 'Ödeme — '.$tema['ad'])

@section('icerik')

    <div class="odeme-gomulu">
        <h1>Ödeme</h1>
        <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>

        {{--
            ⚠️ `sandbox` KOYULMUYOR. Ödeme formunun 3D Secure için banka
            sayfasına gitmesi, form göndermesi ve betik çalıştırması
            gerekiyor; kısıtlı bir sandbox bunları engelleyip ödemeyi
            sessizce bozardı.

            ⚠️ `title` erişilebilirlik için: ekran okuyucu çerçevenin ne
            olduğunu söyleyebilmeli.
        --}}
        <iframe
            src="{{ $gomuluAdres }}"
            title="Ödeme formu"
            class="odeme-cercevesi"
            allow="payment"
        ></iframe>

        {{--
            ⚠️ Müşteriye çerçevenin KİME ait olduğu yazılıyor. Yazılmasaydı
            kart bilgisini kime verdiğini bilemez — ve bilmediği bir forma
            kart girmemesi doğru davranış.
        --}}
        <p class="ipucu">
            Ödeme formu bankanız ve ödeme kuruluşu tarafından sağlanır.
            Kart bilgileriniz {{ $tema['ad'] }} sunucularına gönderilmez.
        </p>
    </div>

@endsection
