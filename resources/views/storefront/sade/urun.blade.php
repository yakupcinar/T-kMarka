@extends('storefront.layout')

@section('baslik', $urun->title.' — '.$tema['ad'])
{{--
    ⚠️ `@section('ad', ifade)` KISA BİÇİMİ KULLANILMIYOR.
    Blade argümanları virgülden bölüyor; içinde virgül olan bir fonksiyon
    çağrısı (`Str::limit(..., 150)`) yanlış ayrışıyor ve görünüm derlenemez
    hâle geliyor. Belirtisi sinsi: sayfa çalışıyor gibi görünüyor ama
    Larastan görünümü bulamıyor. Blok biçimi bu sorunu yaşamıyor.
--}}
@section('aciklama')
    {{ \Illuminate\Support\Str::limit(strip_tags((string) $urun->description), 150) }}
@endsection

@section('icerik')

    <div class="urun">
        <div>
            @if ($urun->images->first())
                <img class="urun-gorsel" src="{{ $urun->images->first()->url() }}" alt="{{ $urun->title }}">
            @else
                <div class="urun-gorsel bos-gorsel">Görsel yok</div>
            @endif
        </div>

        <div>
            <h1>{{ $urun->title }}</h1>

            @if ($urun->brand)
                <p class="marka-adi">{{ $urun->brand }}</p>
            @endif

            @if ($urun->variants->isEmpty())
                {{-- ⚠️ Varyantsız ürün satılamaz; "sepete ekle" gösterilmiyor. --}}
                <p class="bos">Bu ürün şu anda satışta değil.</p>
            @else
                {{--
                    ⚠️ Fiyat SEÇİME GÖRE değişiyor (4.6A). Sabit en düşük fiyat
                    yazılsaydı müşteri 100 TL görüp 120 TL'lik varyantı sepete
                    atardı — ve bunu ancak sepet sayfasında fark ederdi.
                --}}
                <p class="fiyat-buyuk" data-fiyat>
                    {{ number_format((float) $urun->variants->min('price'), 2, ',', '.') }} TL
                </p>

                <form method="post" action="{{ route('vitrin.sepet.ekle') }}" class="ekle-form">
                    {{--
                        ⚠️ CSRF alanı ZORUNLU: bu rota `web` grubunda ve koruma
                        İSTENİYOR (4-K4). 3C'de aynı koruma yanlış yerde olduğu
                        için sorun çıkarmıştı; burası doğru yeri.
                    --}}
                    @csrf

                    {{--
                        VARYANT SEÇİCİSİ (4.6A).

                        ⚠️ Önce TEK DÜZ AÇILIR LİSTE vardı ve tüm varyantları
                        "Kırmızı · M — 100 TL" diye basıyordu: müşteri iki
                        ekseni birden okumak zorundaydı ve stokta olmayan
                        birleşimler de seçilebiliyordu.

                        ⚠️ Seçilen varyant GİZLİ girdiye yazılıyor; sunucu
                        yalnızca `variant_uuid` görüyor, yani seçici bozulsa
                        bile sepete giden veri aynı biçimde doğrulanıyor.
                    --}}
                    @if ($secici['eksenler'] !== [])
                        <div class="varyant-secici"
                             data-secici
                             data-varyantlar='@json($secici['varyantlar'])'>

                            @foreach ($secici['eksenler'] as $eksen)
                                <div class="eksen" data-eksen="{{ $eksen['slug'] }}">
                                    <span class="eksen-ad">{{ $eksen['ad'] }}</span>

                                    {{--
                                        ⚠️ Eşiği aşan eksen AÇILIR LİSTEYE düşüyor
                                        ({{ $listeEsigi }} değerden fazlası): 30 bedenlik bir
                                        eksen kutucuk olarak basılsaydı sayfa okunamazdı.
                                    --}}
                                    @if ($eksen['listeMi'])
                                        <select data-deger-liste>
                                            <option value="">— seçin —</option>
                                            @foreach ($eksen['degerler'] as $deger)
                                                <option value="{{ $deger['slug'] }}">{{ $deger['ad'] }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="kutucuklar">
                                            @foreach ($eksen['degerler'] as $deger)
                                                {{--
                                                    ⚠️ `type="button"`: form içindeyiz ve varsayılan
                                                    `submit` olurdu — kutucuğa basan müşteri sepete
                                                    eksik veriyle istek atardı.
                                                --}}
                                                <button type="button" class="kutucuk" data-deger="{{ $deger['slug'] }}">
                                                    @if ($deger['swatch'])
                                                        <span class="renk" style="background: {{ $deger['swatch'] }}"></span>
                                                    @endif
                                                    {{ $deger['ad'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            <p class="ipucu" data-secici-uyari hidden></p>
                        </div>

                        <input type="hidden" name="variant_uuid" value="" data-varyant-uuid required>
                    @else
                        {{-- ⚠️ EKSENSİZ ÜRÜN: seçilecek bir şey yok, gizli girdi yeter. --}}
                        <input type="hidden" name="variant_uuid" value="{{ $secici['tekVaryant'] ?? $urun->variants->first()->uuid }}">
                    @endif

                    <label>
                        Adet
                        <input type="number" name="quantity" value="1" min="1"
                               max="{{ \App\Domain\Cart\CartService::MAKS_ADET }}">
                    </label>

                    {{-- ⚠️ Seçim tamamlanana kadar KAPALI: eksik seçimle
                         gönderilen form sunucudan "varyant zorunludur"
                         alırdı ve müşteri neyi eksik bıraktığını
                         göremezdi. --}}
                    <button class="dugme buyuk" type="submit" data-ekle-dugme>Sepete ekle</button>
                </form>
            @endif

            @if ($urun->description)
                <div class="aciklama">{!! nl2br(e($urun->description)) !!}</div>
            @endif
        </div>
    </div>


    @if ($secici['eksenler'] !== [])
    {{--
        VARYANT SEÇİMİ (4.6A).

        ⚠️ ÇIKMAZ SOKAK OLUŞMAMALI. Bir değerin kapalı olup olmadığı,
        DİĞER eksenlerdeki seçime göre hesaplanıyor. Kendi ekseni de
        hesaba katılsaydı müşteri "Kırmızı" seçtikten sonra tüm bedenler
        kapanınca sıkışır, rengi değiştiremezdi.

        ⚠️ "Satılabilir" bilgisi SUNUCUDAN geliyor (`stock − committed` +
        aktiflik). Burada yeniden hesaplansaydı sepetle iki ayrı formül
        olurdu — 4.5J'deki tuzağın aynısı.
    --}}
    <script>
        (function () {
            var kok = document.querySelector('[data-secici]')
            if (!kok) return

            var varyantlar = JSON.parse(kok.dataset.varyantlar)
            var gizli = document.querySelector('[data-varyant-uuid]')
            var dugme = document.querySelector('[data-ekle-dugme]')
            var fiyatAlani = document.querySelector('[data-fiyat]')
            var uyari = kok.querySelector('[data-secici-uyari]')
            var eksenler = Array.prototype.slice.call(kok.querySelectorAll('[data-eksen]'))

            var secim = {}

            function paraFormat(deger) {
                return Number(deger).toLocaleString('tr-TR', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2,
                }) + ' TL'
            }

            /* Seçime uyan varyantlar — `haric` verilirse o eksen yok sayılır. */
            function uyanlar(aday, haric) {
                return varyantlar.filter(function (v) {
                    for (var eksen in aday) {
                        if (eksen === haric) continue
                        if (v.secenekler[eksen] !== aday[eksen]) return false
                    }
                    return true
                })
            }

            function uygula() {
                eksenler.forEach(function (grup) {
                    var eksenSlug = grup.dataset.eksen

                    /* ⚠️ KENDİ ekseni hariç tutuluyor — çıkmaz sokağın önlemi. */
                    var olasi = uyanlar(secim, eksenSlug)

                    grup.querySelectorAll('[data-deger]').forEach(function (kutu) {
                        var deger = kutu.dataset.deger
                        var varMi = olasi.some(function (v) {
                            return v.secenekler[eksenSlug] === deger && v.satilabilir
                        })

                        kutu.disabled = !varMi
                        kutu.classList.toggle('tukendi', !varMi)
                        kutu.classList.toggle('secili', secim[eksenSlug] === deger)
                    })

                    var liste = grup.querySelector('[data-deger-liste]')
                    if (liste) {
                        Array.prototype.slice.call(liste.options).forEach(function (secenek) {
                            if (!secenek.value) return
                            secenek.disabled = !olasi.some(function (v) {
                                return v.secenekler[eksenSlug] === secenek.value && v.satilabilir
                            })
                        })
                    }
                })

                var tam = eksenler.every(function (grup) { return secim[grup.dataset.eksen] })
                var secilen = tam ? uyanlar(secim)[0] : null

                gizli.value = secilen && secilen.satilabilir ? secilen.uuid : ''
                dugme.disabled = !gizli.value

                if (secilen) fiyatAlani.textContent = paraFormat(secilen.fiyat)

                /* ⚠️ Müşteri NEDEN ekleyemediğini görmeli. */
                if (!tam) {
                    uyari.textContent = 'Devam etmek için tüm seçenekleri belirleyin.'
                    uyari.hidden = false
                } else if (!gizli.value) {
                    uyari.textContent = 'Bu seçenek şu anda stokta yok.'
                    uyari.hidden = false
                } else {
                    uyari.hidden = true
                }
            }

            kok.addEventListener('click', function (olay) {
                var kutu = olay.target.closest('[data-deger]')
                if (!kutu || kutu.disabled) return

                var eksenSlug = kutu.closest('[data-eksen]').dataset.eksen
                secim[eksenSlug] = secim[eksenSlug] === kutu.dataset.deger ? undefined : kutu.dataset.deger
                uygula()
            })

            kok.addEventListener('change', function (olay) {
                var liste = olay.target.closest('[data-deger-liste]')
                if (!liste) return

                secim[liste.closest('[data-eksen]').dataset.eksen] = liste.value || undefined
                uygula()
            })

            /*
             ⚠️ Betik çalışmazsa düğme AÇIK kalıyor ve gizli girdi boş —
             sunucu doğrulaması devreye giriyor. Kapalı bırakılsaydı
             JavaScript'i kapalı müşteri hiçbir şey satın alamazdı.
            */
            uygula()
        })()
    </script>
    @endif

@endsection
