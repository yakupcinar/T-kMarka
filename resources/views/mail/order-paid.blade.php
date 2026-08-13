<x-mail-layout :markaAdi="$markaAdi" :iletisim="$iletisim" :telefon="$telefon">
  <p style="margin:0 0 16px">Siparişiniz alındı, hazırlanmaya başlıyor.</p>

  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <tr><td style="padding:6px 0;color:#71717a">Sipariş no</td><td style="text-align:right"><b>{{ $siparis->order_number }}</b></td></tr>
    @foreach ($siparis->items as $satir)
      <tr>
        <td style="padding:6px 0;border-top:1px solid #f4f4f5">{{ $satir->product_title }} × {{ $satir->quantity }}</td>
        <td style="text-align:right;border-top:1px solid #f4f4f5">{{ $satir->line_total }} TL</td>
      </tr>
    @endforeach
    <tr><td style="padding:6px 0;border-top:1px solid #e4e4e7;color:#71717a">Kargo</td><td style="text-align:right;border-top:1px solid #e4e4e7">{{ $siparis->shipping_total }} TL</td></tr>
    <tr><td style="padding:6px 0"><b>Toplam</b></td><td style="text-align:right"><b>{{ $siparis->grand_total }} TL</b></td></tr>
    {{-- ⚠️ KDV toplama EKLENMİYOR, içinde (§8.2). Eklenseydi mailde
         gösterilen tutar tahsil edilenden fazla görünürdü. --}}
    <tr><td style="padding:2px 0;font-size:12px;color:#a1a1aa">KDV (dâhil)</td><td style="text-align:right;font-size:12px;color:#a1a1aa">{{ $siparis->tax_total }} TL</td></tr>
  </table>

  <p style="margin:20px 0 0;font-size:13px;color:#71717a">
    Kargoya verildiğinde takip bilgisiyle tekrar yazacağız.
  </p>
</x-mail-layout>
