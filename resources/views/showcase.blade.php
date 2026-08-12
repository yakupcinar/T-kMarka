<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TıkMarka · Sistem Sunumu</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
<main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:py-12">
    <header class="mb-10 flex flex-col justify-between gap-5 border-b border-white/10 pb-8 md:flex-row md:items-end">
        <div>
            <p class="mb-3 text-xs font-semibold tracking-[0.24em] text-cyan-300">TIKMARKA · CANLI BACKEND SUNUMU</p>
            <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-5xl">D2C ticaret çekirdeği</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-400">
                Bu ekran yalnızca okur: katalog, iyzico ödeme durumu ve kuyrukla kaydedilmiş olaylar
                aynı tenant şemasından geliyor. API ve iş kuralları değiştirilmedi.
            </p>
        </div>
        <div class="rounded-2xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100">
            <span class="mr-2 inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
            Tenant bağlı · {{ Str::limit($tenantId, 18, '…') }}
        </div>
    </header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Sistem özeti">
        <x-showcase.metric label="Katalog ürünü" :value="$summary['products']" detail="Vitrinde satılabilir ürünler" />
        <x-showcase.metric label="Ödeme denemesi" :value="$summary['payments']" detail="Provider referansıyla tekilleştirilir" />
        <x-showcase.metric label="Başarılı tahsilat" :value="$summary['captured_payments']" detail="Webhook doğrulamasından sonra" />
        <x-showcase.metric label="Kaydedilen olay" :value="$summary['events']" detail="Kuyruk üzerinden tenant şemasına" />
    </section>

    <section class="mt-10">
        <div class="mb-5 flex items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-cyan-300">1B / 1C · KATALOG VE SEPET</p>
                <h2 class="mt-1 text-2xl font-semibold text-white">Satılabilir ürünler</h2>
            </div>
            <span class="text-xs text-slate-500">Fiyat, satılabilir varyanttan türetilir</span>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($products as $product)
                <article class="overflow-hidden rounded-3xl border border-white/10 bg-slate-900 shadow-2xl shadow-black/20">
                    <div class="aspect-[16/10] bg-gradient-to-br from-cyan-500/20 via-slate-800 to-violet-500/20">
                        @if ($product['image'])
                            <img class="h-full w-full object-cover" src="{{ $product['image'] }}" alt="{{ $product['title'] }}">
                        @else
                            <div class="flex h-full items-center justify-center text-sm text-slate-500">Görsel bekleniyor</div>
                        @endif
                    </div>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-lg font-semibold text-white">{{ $product['title'] }}</h3>
                            <span class="rounded-full bg-white/5 px-2.5 py-1 text-xs text-slate-400">{{ $product['variants'] }} varyant</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-400">/{{ $product['slug'] }}</p>
                        <div class="mt-5 flex items-center justify-between">
                            <span class="text-lg font-semibold text-cyan-300">{{ $product['from_price'] ? number_format((float) $product['from_price'], 2, ',', '.') . ' TL' : '—' }}</span>
                            <button type="button" class="showcase-cart rounded-xl bg-cyan-400 px-3 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" data-product="{{ $product['title'] }}">Sepete eklemeyi göster</button>
                        </div>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-white/15 p-8 text-slate-400">Bu tenantta vitrinde gösterilecek ürün yok.</p>
            @endforelse
        </div>
        <p id="cart-message" class="mt-4 hidden rounded-xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100"></p>
    </section>

    <section class="mt-12 grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
        <article class="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 to-indigo-950 p-6 sm:p-8">
            <p class="text-sm font-semibold text-violet-300">1E / 1E.7 · IYZICO ÖDEME</p>
            <h2 class="mt-2 text-2xl font-semibold text-white">Ödeme kanıtı tarayıcı değil, webhook’tur.</h2>
            <p class="mt-3 max-w-xl leading-7 text-slate-400">
                Müşteri 3D Secure ekranından döndüğünde bu dönüş yalnızca bilgilendirme içindir.
                Siparişi ödenmiş yapan tek kaynak, imzası doğrulanan sağlayıcı bildirimi ve veritabanı tekilliğidir.
            </p>
            <div class="mt-7 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl bg-black/20 p-4"><span class="text-xs text-slate-500">1. Başlat</span><p class="mt-2 font-medium">Yönlendirme URL’i</p></div>
                <div class="rounded-2xl bg-black/20 p-4"><span class="text-xs text-slate-500">2. Banka</span><p class="mt-2 font-medium">3D Secure doğrulaması</p></div>
                <div class="rounded-2xl bg-emerald-400/10 p-4"><span class="text-xs text-emerald-300">3. Kanıt</span><p class="mt-2 font-medium text-emerald-100">İmzalı webhook</p></div>
            </div>
            <button id="payment-demo" type="button" class="mt-7 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-200">Ödeme dönüşünü simüle et</button>
            <p id="payment-message" class="mt-4 hidden rounded-xl border px-4 py-3 text-sm"></p>
        </article>

        <aside class="rounded-3xl border border-white/10 bg-slate-900 p-6 sm:p-8">
            <p class="text-sm font-semibold text-cyan-300">SON ÖDEME DURUMU</p>
            <div id="payment-summary" class="mt-6">
                @if ($summary['latest_payment'])
                    <p class="text-3xl font-semibold text-white">{{ strtoupper($summary['latest_payment']['status']) }}</p>
                    <p class="mt-2 text-slate-400">{{ strtoupper($summary['latest_payment']['provider']) }} · {{ number_format((float) $summary['latest_payment']['amount'], 2, ',', '.') }} TL</p>
                    <p class="mt-5 text-xs text-slate-500">Bu alan yalnızca maskelenmiş özet gösterir; referans, müşteri ve ham sağlayıcı yanıtı gösterilmez.</p>
                @else
                    <p class="text-xl font-semibold text-white">Henüz ödeme kaydı yok</p>
                    <p class="mt-2 text-sm leading-6 text-slate-400">Sandbox akışında ödeme başlatıldığında en güncel deneme burada görünür.</p>
                @endif
            </div>
        </aside>
    </section>

    <section class="mt-12 rounded-3xl border border-white/10 bg-slate-900">
        <div class="flex flex-col justify-between gap-3 border-b border-white/10 p-6 sm:flex-row sm:items-center">
            <div>
                <p class="text-sm font-semibold text-emerald-300">1F · CANLI SİSTEM OLAYLARI</p>
                <h2 class="mt-1 text-2xl font-semibold text-white">Kuyrukla yazılan olay kaydı</h2>
            </div>
            <span id="activity-state" class="text-sm text-slate-500">10 saniyede bir yenilenir</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-left text-sm">
                <thead class="border-b border-white/10 text-xs uppercase tracking-wider text-slate-500">
                    <tr><th class="px-6 py-4 font-medium">Olay tipi</th><th class="px-6 py-4 font-medium">Gerçekleşme zamanı</th><th class="px-6 py-4 font-medium">Durum</th></tr>
                </thead>
                <tbody id="activity-rows" class="divide-y divide-white/5">
                    @forelse ($activity as $event)
                        <tr><td class="px-6 py-4 font-medium text-white">{{ str_replace('_', ' ', $event['type']) }}</td><td class="px-6 py-4 text-slate-400">{{ \Carbon\Carbon::parse($event['occurred_at'])->format('d.m.Y H:i:s') }}</td><td class="px-6 py-4"><span class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs text-emerald-300">kaydedildi</span></td></tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500">Henüz olay kaydı yok. Bir ürün detayını API’den açınca olay kuyrukla yazılır.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <footer class="mt-8 text-center text-xs leading-6 text-slate-600">
        Sunum ekranı yalnızca okuma yapar. API rotaları, ödeme iş kuralları ve tenant izolasyonu değiştirilmez.
    </footer>
</main>

<script>
    const activityRows = document.getElementById('activity-rows');
    const activityState = document.getElementById('activity-state');
    const paymentSummary = document.getElementById('payment-summary');

    const formatDate = (value) => new Intl.DateTimeFormat('tr-TR', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value));
    const formatMoney = (value) => new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value));

    function renderActivity(items) {
        activityRows.innerHTML = items.length
            ? items.map((item) => `<tr><td class="px-6 py-4 font-medium text-white">${item.type.replaceAll('_', ' ')}</td><td class="px-6 py-4 text-slate-400">${formatDate(item.occurred_at)}</td><td class="px-6 py-4"><span class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs text-emerald-300">kaydedildi</span></td></tr>`).join('')
            : '<tr><td colspan="3" class="px-6 py-8 text-center text-slate-500">Henüz olay kaydı yok.</td></tr>';
    }

    function renderPayment(payment) {
        paymentSummary.innerHTML = payment
            ? `<p class="text-3xl font-semibold text-white">${payment.status.toUpperCase()}</p><p class="mt-2 text-slate-400">${payment.provider.toUpperCase()} · ${formatMoney(payment.amount)} TL</p><p class="mt-5 text-xs text-slate-500">Yalnızca maskelenmiş özet gösterilir; müşteri, referans ve ham sağlayıcı yanıtı kapalıdır.</p>`
            : '<p class="text-xl font-semibold text-white">Henüz ödeme kaydı yok</p><p class="mt-2 text-sm leading-6 text-slate-400">Sandbox akışında ödeme başlatıldığında en güncel deneme burada görünür.</p>';
    }

    async function refreshActivity() {
        try {
            const response = await fetch('{{ route('showcase.activity') }}', { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Sunum verisi alınamadı');
            const data = await response.json();
            renderActivity(data.activity);
            renderPayment(data.summary.latest_payment);
            activityState.textContent = `Son yenileme: ${formatDate(data.updated_at)}`;
        } catch {
            activityState.textContent = 'Yenileme başarısız; mevcut kayıtlar ekranda tutuluyor.';
        }
    }

    document.querySelectorAll('.showcase-cart').forEach((button) => button.addEventListener('click', () => {
        const message = document.getElementById('cart-message');
        message.textContent = `${button.dataset.product} için demo sepet adımı gösterildi. Bu sunum düğmesi kayıt yazmaz; gerçek akış API’de X-Cart-Token ile çalışır.`;
        message.classList.remove('hidden');
    }));

    document.getElementById('payment-demo').addEventListener('click', () => {
        const message = document.getElementById('payment-message');
        message.textContent = 'Tarayıcı dönüşü başarı yazmaz. Ekran ancak imzalı iyzico webhook’u veritabanında işlediğinde ödeme durumunun değiştiğini gösterir.';
        message.className = 'mt-4 rounded-xl border border-amber-300/30 bg-amber-300/10 px-4 py-3 text-sm text-amber-100';
        refreshActivity();
    });

    window.setInterval(refreshActivity, 10000);
</script>
</body>
</html>
