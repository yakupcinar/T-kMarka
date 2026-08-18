@extends('storefront.layout')

@section('baslik', 'Sipariş '.$siparis->order_number.' — '.$tema['ad'])

@section('icerik')

    <div class="sonuc">
        @if ($durum === 'success')
            <h1>Siparişiniz alındı</h1>
            <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>
            <p>Ödemeniz onaylandı. Kargoya verildiğinde e-posta ile haber vereceğiz.</p>

        @elseif ($durum === 'failed')
            <h1>Ödeme tamamlanamadı</h1>
            <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>

            {{--
                ⚠️ SEBEP YAZILMIYOR. Bankanın ret gerekçesi (limit, bakiye,
                fraud) müşterinin kartına dair bilgidir; onu bizim ekranımızda
                göstermek hem yanlış olabilir hem de gereksizce ifşa eder.
            --}}
            <p>Ödemeniz alınamadı. Bankanızla görüşüp yeniden deneyebilirsiniz.</p>

        @else
            {{--
                ★ EN ÖNEMLİ DAL: `processing` = "bildirim HENÜZ GELMEDİ",
                "başarısız" DEĞİL.

                ⚠️ Sağlayıcı ilk bildirimi 10-15 saniye sonra atıyor; müşteri
                bu ekrana 3 saniyede varabiliyor. Ara durum "başarısız"
                gösterilseydi müşteri paniğe kapılır, ikinci kez ödemeye
                çalışır ya da bankasını arardı — oysa ödemesi yolda.
            --}}
            <h1>Ödemeniz işleniyor</h1>
            <p class="siparis-no">Sipariş numaranız: <strong>{{ $siparis->order_number }}</strong></p>
            <p>
                Bankanızdan onay bekliyoruz. Bu birkaç saniye sürebilir —
                sayfayı yenileyerek durumu görebilirsiniz.
            </p>
        @endif

        <p><a class="dugme" href="{{ route('vitrin.anasayfa') }}">Alışverişe devam et</a></p>
    </div>

@endsection
