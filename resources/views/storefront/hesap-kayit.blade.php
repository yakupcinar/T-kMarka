@extends('storefront.layout')

@section('baslik', 'Kayıt — '.$tema['ad'])

@section('icerik')
    <div class="hesap-dar">
        <h1>Hesap oluştur</h1>

        <form method="post" action="{{ route('vitrin.kayit') }}">
            @csrf

            <label>Ad soyad
                <input type="text" name="name" value="{{ old('name') }}" required autofocus>
            </label>

            <label>E-posta
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>

            <label>Parola
                <input type="password" name="password" required>
                <span class="ipucu">En az 8 karakter.</span>
            </label>

            <label>Telefon <span class="ipucu">(isteğe bağlı)</span>
                <input type="tel" name="phone" value="{{ old('phone') }}">
            </label>

            {{--
                ⚠️ Pazarlama onayı VARSAYILAN OLARAK KAPALI ve ayrı bir
                kutu: kayıt olmak, e-posta almayı kabul etmek değildir
                (1A.2 · KVKK açık rıza).
            --}}
            <label class="onay-satiri">
                <input type="checkbox" name="accepts_marketing" value="1">
                <span>Kampanya ve indirim e-postaları almak istiyorum.</span>
            </label>

            <button class="dugme buyuk" type="submit">Hesap oluştur</button>
        </form>

        <p class="ipucu">
            Hesabınız var mı? <a href="{{ route('vitrin.giris') }}">Giriş yapın</a>.
        </p>
    </div>
@endsection
