@php
    $odemeAdi = [
        'pending' => 'Ödeme bekleniyor', 'paid' => 'Ödendi',
        'partially_refunded' => 'Kısmen iade edildi', 'refunded' => 'İade edildi',
        'failed' => 'Ödeme alınamadı', 'cancelled' => 'İptal edildi',
    ];
    $paketAdi = [
        'pending' => 'Hazırlanıyor', 'shipped' => 'Kargoya verildi',
        'delivered' => 'Teslim edildi', 'cancelled' => 'İptal edildi',
    ];
@endphp

@extends('storefront.layout')

@section('baslik', $siparis->order_number.' — '.$tema['ad'])

@section('icerik')
    <div class="hesap">
        <p><a href="{{ route('vitrin.hesap') }}">← Hesabım</a></p>

        <h1>{{ $siparis->order_number }}</h1>
        <p class="ipucu">
            {{ $siparis->placed_at?->format('d.m.Y H:i') }} ·
            {{ $odemeAdi[$siparis->payment_status->value] ?? $siparis->payment_status->value }}
        </p>

        <table class="sepet-tablo">
            @foreach ($siparis->items as $satir)
                <tr>
                    <td>{{ $satir->product_title }} <code>{{ $satir->sku }}</code></td>
                    <td>{{ $satir->quantity }} adet</td>
                    <td>{{ number_format((float) $satir->line_total, 2, ',', '.') }} TL</td>
                </tr>
            @endforeach
        </table>

        <p>
            Ürünler: {{ number_format((float) $siparis->items_total, 2, ',', '.') }} TL ·
            Kargo: {{ number_format((float) $siparis->shipping_total, 2, ',', '.') }} TL ·
            <strong>Toplam: {{ number_format((float) $siparis->grand_total, 2, ',', '.') }} TL</strong>
        </p>

        <h2>Kargo</h2>

        @if ($siparis->fulfillments->isEmpty())
            <p class="ipucu">Siparişiniz henüz hazırlanıyor.</p>
        @else
            @foreach ($siparis->fulfillments as $paket)
                <p>
                    {{ $paketAdi[$paket->status->value] ?? $paket->status->value }}
                    @if ($paket->carrier) · {{ $paket->carrier }} @endif
                    {{--
                        ⚠️ TAKİP NUMARASI müşteriye gösteriliyor: kargonun
                        nerede olduğunu sormak için markayı aramak zorunda
                        kalmamalı.
                    --}}
                    @if ($paket->tracking_number) · <code>{{ $paket->tracking_number }}</code> @endif
                </p>
            @endforeach
        @endif

        <h2>Teslimat adresi</h2>
        <p class="ipucu">
            {{ $siparis->shipping_full_name }}<br>
            {{ $siparis->shipping_line1 }}<br>
            {{ $siparis->shipping_district }} / {{ $siparis->shipping_city }}
        </p>

        {{--
            ⚠️ Onaylanan SÖZLEŞME SÜRÜMÜ müşteriye de gösteriliyor:
            "neyi kabul ettim" sorusu ona da açık olmalı (1D-K2).
        --}}
        @if ($siparis->legalVersion)
            <p class="ipucu">
                Onayladığınız satış sözleşmesi: sürüm {{ $siparis->legalVersion->version_no }}
            </p>
        @endif
    </div>
@endsection
