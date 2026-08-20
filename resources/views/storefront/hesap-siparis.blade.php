@php
    $odemeAdi = [
        'pending' => 'Ödeme bekleniyor', 'paid' => 'Ödendi',
        'partially_refunded' => 'Kısmen iade edildi', 'refunded' => 'İade edildi',
        'failed' => 'Ödeme alınamadı', 'cancelled' => 'İptal edildi',
    ];
    $iadeAdi = [
        'requested' => 'Talep alındı, marka değerlendiriyor',
        'approved' => 'Onaylandı — ürünü gönderebilirsiniz',
        'received' => 'Ürün markaya ulaştı',
        'refunded' => 'Para iadesi yapıldı',
        'rejected' => 'Reddedildi',
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
            İADE (4.5K) — uçları 2B'de vardı, ekranı yoktu: müşteri iade
            talebini HİÇBİR YERDEN açamıyordu.
        --}}
        <h2>İade</h2>

        @if ($siparis->returns->isNotEmpty())
            <table class="sepet-tablo">
                @foreach ($siparis->returns as $talep)
                    <tr>
                        <td>{{ $talep->created_at?->format('d.m.Y') }}</td>
                        <td>{{ $talep->items->sum('quantity') }} ürün</td>
                        <td>{{ $iadeAdi[$talep->status->value] ?? $talep->status->value }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if (! $iadeEdilebilir)
            {{-- ⚠️ Ödenmemiş siparişte geri verilecek para yok. --}}
            <p class="ipucu">Bu siparişin ödemesi tamamlanmadığı için iade açılamaz.</p>
        @elseif (collect($iadeBilgisi)->every(fn ($b) => $b['kalan'] === 0))
            <p class="ipucu">Bu siparişteki tüm ürünler için iade talebi açılmış.</p>
        @else
            <form method="post" action="{{ route('vitrin.hesap.iade', $siparis->uuid) }}">
                @csrf

                <table class="sepet-tablo">
                    @foreach ($siparis->items as $satir)
                        @php($bilgi = $iadeBilgisi[$satir->id])
                        @continue($bilgi['kalan'] === 0)

                        <tr>
                            <td>
                                {{ $satir->product_title }} <code>{{ $satir->sku }}</code>
                                <div class="ipucu">
                                    {{--
                                        ⚠️ Süre TESLİM tarihinden işliyor ve SATIR BAZINDA
                                        (2B-K2): kısmi sevkiyatta her paketin kendi tarihi var.
                                    --}}
                                    @if ($bilgi['teslim'])
                                        Teslim: {{ $bilgi['teslim']->format('d.m.Y') }} ·
                                        @if ($bilgi['cayma_acik'])
                                            cayma süresi {{ $bilgi['teslim']->copy()->addDays(14)->format('d.m.Y') }} tarihine kadar
                                        @else
                                            cayma süresi doldu
                                        @endif
                                    @else
                                        Henüz teslim edilmedi
                                    @endif
                                </div>
                            </td>
                            <td>en fazla {{ $bilgi['kalan'] }}</td>
                            <td>
                                <input type="number" name="adetler[{{ $satir->id }}]"
                                       min="0" max="{{ $bilgi['kalan'] }}" value="0" style="width:5rem">
                            </td>
                        </tr>
                    @endforeach
                </table>

                {{--
                    ⚠️ CAYMA mı KUSURLU ÜRÜN mü — müşteri seçiyor. Cayma 14 günle
                    sınırlı, kusurlu ürün değil. Yalnızca cayma sunulsaydı 15. günde
                    kusurlu ürün bildiren müşteri hiçbir şey yapamaz, markayı aramak
                    zorunda kalırdı.

                    ⚠️ Seçim TALEBİ AÇMAYA yetiyor, iadeyi ONAYLAMAYA değil:
                    kusurlu olduğu iddiasını marka değerlendiriyor.
                --}}
                <p>
                    <label><input type="radio" name="tur" value="cayma" checked> Cayma hakkı (14 gün)</label>
                    <label><input type="radio" name="tur" value="kusurlu"> Ürün kusurlu / hatalı</label>
                </p>

                <label>Açıklama <span class="ipucu">(isteğe bağlı)</span>
                    <input type="text" name="sebep" value="{{ old('sebep') }}" maxlength="255">
                </label>

                {{--
                    ⚠️ Müşteri yalnızca TALEP açıyor; onay, teslim alma ve para
                    iadesi markanın işi (2B-K1). Düğme "iade et" deseydi para
                    iadesinin başladığı beklentisini yaratırdı.
                --}}
                <button type="submit" class="dugme">İade talebi oluştur</button>
            </form>
        @endif

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
