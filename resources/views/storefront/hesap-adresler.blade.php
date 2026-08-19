@extends('storefront.layout')

@section('baslik', 'Adres defterim — '.$tema['ad'])

@section('icerik')
    <div class="hesap-dar">
        <p><a href="{{ route('vitrin.hesap') }}">← Hesabım</a></p>

        <h1>Adres defterim</h1>

        @if ($adresler->isEmpty())
            <p class="bos">Kayıtlı adresiniz yok.</p>
        @else
            @foreach ($adresler as $a)
                <div class="adres-kart">
                    <strong>{{ $a->title }}</strong>
                    <div>{{ $a->full_name }}</div>
                    <div class="ipucu">{{ $a->phone }}</div>
                    <div>{{ $a->line1 }}</div>
                    <div class="ipucu">{{ $a->district }} / {{ $a->city }}</div>

                    <form method="post" action="{{ route('vitrin.adres.sil', $a->uuid) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sil">Sil</button>
                    </form>
                </div>
            @endforeach
        @endif

        <h2>Adres ekle</h2>

        <form method="post" action="{{ route('vitrin.adres.ekle') }}">
            @csrf

            {{--
                ⚠️ BAŞLIK ALANI 4.5D'de UNUTULMUŞTU ve form hiç
                kaydedilemiyordu: `AddressRequest` `title`'ı zorunlu
                tutuyor, form onu hiç göndermiyordu. Müşteri "başlık alanı
                zorunludur" uyarısını alıyor ama ekranda öyle bir alan
                YOKTU — düzeltilemez bir hata.

                ⚠️ Bu müşterinin KENDİ ETİKETİ ("Ev", "İş", "Annemler"),
                adresteki kişinin adı değil — o `full_name`.
            --}}
            <label>Adres başlığı
                <input type="text" name="title" value="{{ old('title') }}" placeholder="Ev, İş…" required>
            </label>

            <label>Ad soyad <input type="text" name="full_name" value="{{ old('full_name') }}" required></label>
            <label>Telefon <input type="tel" name="phone" value="{{ old('phone') }}" required></label>

            <div class="ikili">
                <label>İl <input type="text" name="city" value="{{ old('city') }}" required></label>
                <label>İlçe <input type="text" name="district" value="{{ old('district') }}" required></label>
            </div>

            <label>Mahalle <input type="text" name="neighborhood" value="{{ old('neighborhood') }}"></label>
            <label>Adres <input type="text" name="line1" value="{{ old('line1') }}" required></label>
            <label>Adres (devam) <input type="text" name="line2" value="{{ old('line2') }}"></label>
            <label>Posta kodu <input type="text" name="postal_code" value="{{ old('postal_code') }}"></label>

            <button class="dugme" type="submit">Ekle</button>
        </form>
    </div>
@endsection
