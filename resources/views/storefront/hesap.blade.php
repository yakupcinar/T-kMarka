@extends('storefront.layout')

@section('baslik', 'Hesabım — '.$tema['ad'])

@php
    /*
    | ⚠️ Durum adları GÖRÜNÜMDE, enum'da DEĞİL. Enum iş mantığına ait ve
    | müşteriye gösterilecek metin bir sunum kararı: aynı `pending`
    | panelde "Bekliyor", vitrinde "Ödeme bekleniyor" olmalı.
    */
    $odemeAdi = [
        'pending' => 'Ödeme bekleniyor',
        'paid' => 'Ödendi',
        'partially_refunded' => 'Kısmen iade edildi',
        'refunded' => 'İade edildi',
        'failed' => 'Ödeme alınamadı',
        'cancelled' => 'İptal edildi',
    ];

    $kargoAdi = [
        'unfulfilled' => 'Hazırlanıyor',
        'partial' => 'Kısmen gönderildi',
        'fulfilled' => 'Gönderildi',
        'cancelled' => 'İptal',
    ];
@endphp

@section('icerik')
    <div class="hesap">
        <div class="hesap-bas">
            <h1>Hesabım</h1>
            <span class="ipucu">{{ $musteri->name }} · {{ $musteri->email }}</span>

            <form method="post" action="{{ route('vitrin.cikis') }}" class="hesap-cikis">
                @csrf
                <button type="submit" class="sil">Çıkış yap</button>
            </form>
        </div>

        <p><a href="{{ route('vitrin.adresler') }}">Adres defterim</a></p>

        <h2>Siparişlerim</h2>

        @if ($siparisler->isEmpty())
            {{-- ⚠️ Boş liste hata gibi görünmemeli: yeni müşteri için NORMAL. --}}
            <p class="bos">Henüz siparişiniz yok.</p>
        @else
            <table class="sepet-tablo">
                @foreach ($siparisler as $s)
                    <tr>
                        <td>
                            <a href="{{ route('vitrin.hesap.siparis', $s->uuid) }}">
                                <strong>{{ $s->order_number }}</strong>
                            </a>
                            <div class="ipucu">{{ $s->placed_at?->format('d.m.Y H:i') }}</div>
                        </td>
                        <td>{{ $s->items->sum('quantity') }} ürün</td>
                        <td>{{ number_format((float) $s->grand_total, 2, ',', '.') }} TL</td>
                        <td>{{ $odemeAdi[$s->payment_status->value] ?? $s->payment_status->value }}</td>
                        <td>{{ $kargoAdi[$s->fulfillment_status->value] ?? $s->fulfillment_status->value }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
@endsection
