<x-mail-layout :markaAdi="$markaAdi" :iletisim="$iletisim" :telefon="$telefon">
  <p style="margin:0 0 16px"><b>{{ $siparis->order_number }}</b> numaralı siparişinizin ödemesi alınamadı.</p>

  {{-- ⚠️ Sağlayıcının ham hata mesajı YOK: hesap yapılandırmasına dair
       ayrıntı içerebiliyor (1E.7.3). Müşteriye eylem söyleniyor. --}}
  <p style="margin:0 0 16px;font-size:14px">
    Kartınızdan tahsilat yapılmadı. Farklı bir kartla tekrar deneyebilirsiniz.
  </p>

  <p style="margin:0;font-size:13px;color:#71717a">
    Siparişiniz kaydımızda duruyor; sorun yaşarsanız bize yazabilirsiniz.
  </p>
</x-mail-layout>
