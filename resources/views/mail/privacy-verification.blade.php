<x-mail-layout :markaAdi="$markaAdi" :iletisim="$iletisim" :telefon="$telefon">
  @if ($silme)
    <p style="margin:0 0 16px">Kişisel verilerinizin silinmesini talep ettiniz.</p>
    {{-- ⚠️ Geri alınamazlık AÇIKÇA söyleniyor (2G-K4). --}}
    <p style="margin:0 0 16px;font-size:14px;color:#b91c1c">
      <b>Bu işlem geri alınamaz.</b> Adınız, e-postanız, telefonunuz ve adresleriniz
      okunamaz hale gelir. Siparişleriniz yasal saklama süresi boyunca kayıtta kalır,
      ancak size bağlı olmaktan çıkar.
    </p>
  @else
    <p style="margin:0 0 16px">Verilerinizin dökümünü talep ettiniz.</p>
  @endif

  <p style="margin:0 0 20px;font-size:14px">Onaylamak için:</p>

  <p style="margin:0 0 20px">
    <a href="{{ $adres }}" style="background:#18181b;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-size:14px">Talebi onayla</a>
  </p>

  <p style="margin:0;font-size:13px;color:#71717a">
    Bağlantı {{ $sonGecerlilik->format('d.m.Y H:i') }} tarihine kadar geçerli.
    Bu talebi siz yapmadıysanız bu postayı yok sayabilirsiniz.
  </p>
</x-mail-layout>
