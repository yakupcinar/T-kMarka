<x-mail-layout :markaAdi="$markaAdi" :iletisim="$iletisim" :telefon="$telefon">
  @if ($teslim)
    <p style="margin:0 0 16px">Siparişiniz teslim edildi. Afiyet olsun!</p>
  @else
    <p style="margin:0 0 16px">Siparişiniz kargoya verildi.</p>
  @endif

  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#71717a">Sipariş no</td><td style="text-align:right"><b>{{ $paket->order?->order_number }}</b></td></tr>
    @if ($paket->carrier)
      <tr><td style="padding:6px 0;color:#71717a">Kargo</td><td style="text-align:right">{{ $paket->carrier }}</td></tr>
    @endif
    @if ($paket->tracking_number)
      <tr><td style="padding:6px 0;color:#71717a">Takip no</td><td style="text-align:right"><b>{{ $paket->tracking_number }}</b></td></tr>
    @endif
  </table>

  {{-- ⚠️ PAKET bazında: kısmi sevkiyat var (1D.4). Müşteri bu paketin
       neyi taşıdığını görmeli, yoksa eksik geldi sanır. --}}
  <p style="margin:16px 0 6px;font-size:13px;color:#71717a">Bu pakettekiler:</p>
  <ul style="margin:0;padding-left:18px;font-size:14px">
    @foreach ($paket->items as $kalem)
      <li>{{ $kalem->orderItem?->product_title }} × {{ $kalem->quantity }}</li>
    @endforeach
  </ul>
</x-mail-layout>
