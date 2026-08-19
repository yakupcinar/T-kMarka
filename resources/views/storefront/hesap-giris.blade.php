@extends('storefront.layout')

@section('baslik', 'Giriş — '.$tema['ad'])

@section('icerik')
    <div class="hesap-dar">
        <h1>Giriş yap</h1>

        <form method="post" action="{{ route('vitrin.giris') }}">
            @csrf

            <label>E-posta
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>

            <label>Parola
                <input type="password" name="password" required>
            </label>

            <button class="dugme buyuk" type="submit">Giriş yap</button>
        </form>

        <p class="ipucu">
            Hesabınız yok mu? <a href="{{ route('vitrin.kayit') }}">Kayıt olun</a>.
            {{-- ⚠️ Üyelik ZORUNLU DEĞİL (M-1): misafir de sipariş verebiliyor. --}}
            Üye olmadan da sipariş verebilirsiniz.
        </p>
    </div>
@endsection
