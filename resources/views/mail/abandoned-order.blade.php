<x-mail-layout :markaAdi="$markaAdi" :iletisim="$iletisim" :telefon="$telefon">
  <p style="margin:0 0 16px"><b>{{ $siparis->order_number }}</b> numaralı siparişinizin ödemesi tamamlanmadı.</p>

  {{-- ⚠️ STOK SÖZÜ YOK. Rezervasyon 60 dakikada düşüyor (1D-K3) ve bu mail
       o süre dolduktan sonra gidiyor. "Ürünleriniz ayrıldı" demek tutulamayacak
       bir söz olurdu; ödeme kabul edilse bile stok açığı çıkabilir (1E-K5). --}}
  <p style="margin:0 0 16px;font-size:14px">
    Siparişiniz kaydımızda duruyor. Ödemeyi tamamlamak isterseniz mağazadan
    tekrar başlatabilirsiniz; ürünlerin stok durumu o anda kontrol edilecek.
  </p>

  <p style="margin:0 0 16px;font-size:14px">
    Toplam tutar: <b>{{ $siparis->grand_total }} TL</b>
  </p>

  <p style="margin:0;font-size:13px;color:#71717a">
    Vazgeçtiyseniz bir şey yapmanıza gerek yok — bu tek hatırlatmadır.
  </p>
</x-mail-layout>
