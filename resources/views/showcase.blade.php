{{--
    TıkMarka — SUNUM VİTRİNİ (tek dosya)

    ⚠️ Bu dosya backend'e HİÇ dokunmuyor: model, servis, controller
    kullanmıyor. Bütün veriyi tarayıcıdan GERÇEK API uçlarına `fetch`
    atarak alıyor. Yani ekranda gördüğün her şey, gerçek müşterinin
    kullanacağı uçların çıktısı.

    ⚠️ Tailwind CDN üzerinden — derleme adımı yok, `npm` gerekmiyor.
--}}
<!doctype html>
<html lang="tr" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TıkMarka — Sistem Vitrini</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<style>
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .kod { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
</style>
</head>
<body class="bg-slate-950 text-slate-200 antialiased">

<div class="mx-auto max-w-6xl px-5 py-10">

  {{-- ─────────── BAŞLIK ─────────── --}}
  <header class="mb-10">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-white">TıkMarka</h1>
        <p class="mt-1 text-slate-400">Çok kiracılı D2C e-ticaret — Faz 1 vitrini</p>
      </div>
      <div class="rounded-lg border border-slate-800 bg-slate-900 px-4 py-3 text-sm">
        <div class="text-slate-500">Şu an bu markadasın</div>
        <div class="kod mt-1 text-emerald-400">{{ $alanAdi }}</div>
        <div class="kod text-xs text-slate-600">şema: tenant_{{ substr($marka, 0, 8) }}…</div>
      </div>
    </div>

    <div class="mt-6 rounded-lg border border-amber-900/60 bg-amber-950/30 p-4 text-sm text-amber-200">
      <b>Bu sayfa nedir?</b> Arayüz değil, <b>kanıt</b>. Aşağıdaki her düğme
      gerçek API ucunu çağırıyor — sahte veri yok. Aynı adresi başka bir
      markanın alan adıyla açarsan <b>tamamen farklı veri</b> görürsün;
      veriler ayrı veritabanı şemalarında duruyor.
    </div>
  </header>

  {{-- ─────────── 1. KATALOG ─────────── --}}
  <section class="mb-10">
    <div class="mb-3 flex items-baseline gap-3">
      <h2 class="text-xl font-semibold text-white">1 · Katalog</h2>
      <span class="kod text-xs text-slate-500">GET /api/products</span>
    </div>
    <p class="mb-4 text-sm text-slate-400">
      Vitrin sorgusu taslak ürünleri, arşivlenmişleri ve <b>satılabilir varyantı
      kalmamış</b> ürünleri hiç döndürmüyor. Maliyet alanı (<span class="kod">cost_price</span>)
      sorguda <b>hiç seçilmiyor</b> — yani sızması mümkün değil, gizlenmiyor.
    </p>
    <div id="katalog" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div class="animate-pulse rounded-lg border border-slate-800 bg-slate-900 p-6 text-slate-600">yükleniyor…</div>
    </div>
  </section>

  {{-- ─────────── 2. SEPET + SİPARİŞ ─────────── --}}
  <section class="mb-10">
    <div class="mb-3 flex items-baseline gap-3">
      <h2 class="text-xl font-semibold text-white">2 · Sepet ve sipariş</h2>
      <span class="kod text-xs text-slate-500">POST /api/cart/items → /api/checkout</span>
    </div>
    <p class="mb-4 text-sm text-slate-400">
      Misafir olarak sipariş verilebiliyor — hesap gerekmiyor. Sepet
      <span class="kod">X-Cart-Token</span> başlığıyla taşınıyor; çerez yok,
      çünkü vitrin teknolojisi henüz seçilmedi.
    </p>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
      <div class="mb-4 flex flex-wrap gap-3">
        <button id="btnSepet" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
          Sepete ürün ekle
        </button>
        <button id="btnSiparis" disabled
          class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-500">
          Sipariş oluştur
        </button>
      </div>
      <pre id="sepetCikti" class="kod max-h-72 overflow-auto rounded bg-slate-950 p-4 text-xs text-slate-400">Henüz bir şey yapılmadı.</pre>
    </div>
  </section>

  {{-- ─────────── 3. ÖDEME ─────────── --}}
  <section class="mb-10">
    <div class="mb-3 flex items-baseline gap-3">
      <h2 class="text-xl font-semibold text-white">3 · Ödeme (iyzico)</h2>
      <span class="kod text-xs text-slate-500">POST /api/orders/{uuid}/pay</span>
    </div>

    <div class="mb-4 grid gap-4 md:grid-cols-3">
      <div class="rounded-lg border border-slate-800 bg-slate-900 p-4">
        <div class="text-sm font-semibold text-white">① Başlat</div>
        <p class="mt-1 text-xs text-slate-400">Tutar <b>sunucuda</b> üretilir. İstemcinin gönderdiği hiçbir tutara bakılmaz.</p>
      </div>
      <div class="rounded-lg border border-slate-800 bg-slate-900 p-4">
        <div class="text-sm font-semibold text-white">② Müşteri bizden çıkar</div>
        <p class="mt-1 text-xs text-slate-400">Kart bilgisi iyzico'nun sayfasına girilir — <b>bize hiç değmez</b>.</p>
      </div>
      <div class="rounded-lg border border-slate-800 bg-slate-900 p-4">
        <div class="text-sm font-semibold text-white">③ Gerçek: webhook</div>
        <p class="mt-1 text-xs text-slate-400">Tarayıcı dönüşü <b>kanıt değil</b>. Sipariş yalnızca sunucu bildirimiyle ödenir.</p>
      </div>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
      <button id="btnOde" disabled
        class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-500">
        Ödemeyi başlat
      </button>
      <pre id="odemeCikti" class="kod mt-4 max-h-72 overflow-auto rounded bg-slate-950 p-4 text-xs text-slate-400">Önce sipariş oluştur.</pre>
    </div>

    {{-- Hesap gerektiren bilgiler: doldurulacak yerler için örnek değerler --}}
    <details class="mt-4 rounded-lg border border-slate-800 bg-slate-900/60 p-4 text-sm">
      <summary class="cursor-pointer font-medium text-slate-300">
        Ödeme sayfasında ne yazacağım? (sandbox test bilgileri)
      </summary>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <div class="mb-2 text-xs uppercase tracking-wide text-slate-500">Başarılı ödeme</div>
          <table class="w-full text-xs">
            <tr><td class="py-1 text-slate-500">Kart</td><td class="kod text-emerald-400">5890040000000016</td></tr>
            <tr><td class="py-1 text-slate-500">Son kullanma</td><td class="kod">12/30</td></tr>
            <tr><td class="py-1 text-slate-500">CVV</td><td class="kod">123</td></tr>
            <tr><td class="py-1 text-slate-500">Ad Soyad</td><td class="kod">Ayse Yilmaz</td></tr>
          </table>
        </div>
        <div>
          <div class="mb-2 text-xs uppercase tracking-wide text-slate-500">Başarısız senaryo</div>
          <table class="w-full text-xs">
            <tr><td class="py-1 text-slate-500">Kart</td><td class="kod text-rose-400">4111111111111129</td></tr>
            <tr><td class="py-1 text-slate-500">Sonuç</td><td>yetersiz bakiye</td></tr>
          </table>
          <p class="mt-3 text-xs text-amber-300">
            ⚠️ 3D&nbsp;Secure ekranındaki SMS kodu <b>sayfada yazıyor</b> —
            sabit bir kod yok, ekrandakini gir.
          </p>
        </div>
      </div>
      <p class="mt-4 text-xs text-slate-500">
        Bunlar iyzico'nun yayımladığı sandbox test kartlarıdır; gerçek para hareketi yoktur.
      </p>
    </details>
  </section>

  {{-- ─────────── 4. OLAY KAYDI ─────────── --}}
  <section class="mb-10">
    <div class="mb-3 flex items-baseline gap-3">
      <h2 class="text-xl font-semibold text-white">4 · Olay kaydı</h2>
      <span class="flex items-center gap-2 text-xs text-slate-500">
        <span class="relative flex h-2 w-2">
          <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
          <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
        </span>
        5 saniyede bir yenileniyor
      </span>
    </div>
    <p class="mb-4 text-sm text-slate-400">
      Olaylar isteğin içinde <b>yazılmıyor</b>: kuyruğa atılıyor, ayrı bir işçi
      süreci yazıyor. Bu yüzden yukarıdaki düğmelere bastıktan <b>birkaç saniye
      sonra</b> burada beliriyorlar — gecikme arıza değil, tasarım.
    </p>

    <div id="olaySayac" class="mb-4 flex flex-wrap gap-2"></div>

    <div class="overflow-hidden rounded-lg border border-slate-800">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-500">
          <tr><th class="px-4 py-3">Olay</th><th class="px-4 py-3">Zaman</th></tr>
        </thead>
        <tbody id="olayListe" class="divide-y divide-slate-800 bg-slate-900/40">
          <tr><td colspan="2" class="px-4 py-6 text-center text-slate-600">yükleniyor…</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <footer class="border-t border-slate-800 pt-6 text-xs text-slate-600">
    Bu sayfa salt okunur bir sunum katmanıdır. Hiçbir iş kuralı burada yaşamaz;
    her işlem gerçek API uçlarından geçer.
  </footer>
</div>

@verbatim
<script>
const $ = (id) => document.getElementById(id);
let sepetToken = null;
let siparis = null;

const yaz = (hedef, veri) => { $(hedef).textContent = typeof veri === 'string' ? veri : JSON.stringify(veri, null, 2); };

/*
 * ⚠️ `ngrok-skip-browser-warning` — ÖLÇÜLEREK eklendi.
 *
 * ngrok'un ücretsiz katmanı tarayıcıdan gelen isteklere bir uyarı
 * sayfası koyuyor. Ziyaretçi onu bir kez tıklayıp geçiyor, ama bu
 * sayfanın `fetch` çağrıları da tarayıcıdan gidiyor: başlık olmadan
 * JSON yerine o uyarının HTML'i dönüyor ve ekran sessizce boş kalıyor.
 *
 * Başlığın DEĞERİ önemsiz, VARLIĞI yeterli (ngrok'un belgesi böyle diyor).
 */
const NGROK_BASLIK = { 'ngrok-skip-browser-warning': '1' };

async function iste(yol, secenek = {}) {
  const baslik = { 'Accept': 'application/json', 'Content-Type': 'application/json', ...NGROK_BASLIK, ...(secenek.headers || {}) };
  if (sepetToken) baslik['X-Cart-Token'] = sepetToken;

  const cevap = await fetch(yol, { ...secenek, headers: baslik });

  /*
  | ⚠️ Cevap JSON değilse ARAYÜZ SESSİZ KALMASIN: sunum ekranında en kötü
  | şey, hiçbir şey olmaması. Ne döndüğünü söylüyoruz.
  */
  const metin = await cevap.text();

  try {
    return { durum: cevap.status, govde: JSON.parse(metin) };
  } catch {
    return { durum: cevap.status, govde: { _hata: 'JSON değil', _onizleme: metin.slice(0, 200) } };
  }
}

/* ─────── Katalog ─────── */
async function kataloguYukle() {
  const { durum, govde } = await iste('/api/products');

  if (durum !== 200) {
    $('katalog').innerHTML = `<div class="rounded-lg border border-rose-900 bg-rose-950/40 p-6 text-sm text-rose-300">
      Katalog alınamadı (HTTP ${durum}). Mağaza kapalıysa vitrin bilerek 503 döner.</div>`;
    return;
  }

  const urunler = govde.products || [];
  if (!urunler.length) { $('katalog').innerHTML = '<div class="rounded-lg border border-slate-800 bg-slate-900 p-6 text-sm text-slate-500">Bu markada yayında ürün yok.</div>'; return; }

  $('katalog').innerHTML = urunler.map(u => `
    <article class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900">
      ${u.image ? `<img src="${u.image}" alt="" class="h-40 w-full object-cover">` : '<div class="h-40 bg-slate-800"></div>'}
      <div class="p-4">
        <h3 class="font-medium text-white">${u.title}</h3>
        <p class="mt-1 text-sm text-slate-400">${u.from_price ? u.from_price + ' TL’den başlayan' : 'fiyat yok'}</p>
        <p class="kod mt-2 text-xs text-slate-600">${u.slug}</p>
      </div>
    </article>`).join('');
}

/* ─────── Sepet ─────── */
$('btnSepet').addEventListener('click', async () => {
  yaz('sepetCikti', 'Ürün ve varyant alınıyor…');

  const liste = await iste('/api/products');
  const ilk = (liste.govde.products || [])[0];
  if (!ilk) return yaz('sepetCikti', 'Yayında ürün yok.');

  const detay = await iste('/api/products/' + ilk.slug);
  const varyant = (detay.govde.product?.variants || []).find(v => v.in_stock);
  if (!varyant) return yaz('sepetCikti', 'Satılabilir varyant yok — stok tükenmiş.');

  const ekle = await iste('/api/cart/items', {
    method: 'POST',
    body: JSON.stringify({ variant_uuid: varyant.uuid, quantity: 1 }),
  });

  if (ekle.durum !== 201) return yaz('sepetCikti', ekle.govde);

  sepetToken = ekle.govde.cart_token;
  $('btnSiparis').disabled = false;

  yaz('sepetCikti',
    `✓ ${varyant.sku} sepete eklendi\n` +
    `  ara toplam : ${ekle.govde.subtotal} TL\n` +
    `  sepet token: ${sepetToken.slice(0, 16)}…\n\n` +
    `Not: bu token tarayıcıda tutuluyor, çerez kullanılmıyor.`);
});

/* ─────── Sipariş ─────── */
$('btnSiparis').addEventListener('click', async () => {
  yaz('sepetCikti', 'Sözleşme sürümü alınıyor…');

  const metin = await iste('/api/legal/distance_sales');
  const surum = metin.govde.document?.version_id;
  if (!surum) return yaz('sepetCikti', 'Mesafeli satış sözleşmesi yayınlanmamış.');

  const cevap = await iste('/api/checkout', {
    method: 'POST',
    body: JSON.stringify({
      email: 'vitrin@example.com',
      legal_version_id: surum,
      shipping: { full_name: 'Ayse Yilmaz', phone: '+905321112233', city: 'Istanbul', district: 'Kadikoy', line1: 'Moda Cad. No:12' },
    }),
  });

  if (cevap.durum !== 201) return yaz('sepetCikti', cevap.govde);

  siparis = cevap.govde.order;
  sepetToken = null;
  $('btnSiparis').disabled = true;
  $('btnOde').disabled = false;

  yaz('sepetCikti',
    `✓ sipariş oluştu: ${siparis.order_number}\n` +
    `  ürünler   : ${siparis.items_total} TL\n` +
    `  kargo     : ${siparis.shipping_total} TL\n` +
    `  TOPLAM    : ${siparis.grand_total} TL\n` +
    `  KDV       : ${siparis.tax_total} TL  ← toplama EKLENMEZ, içinde\n` +
    `  ödeme     : ${siparis.payment_status}\n\n` +
    `Sözleşme sürümü ${surum} siparişe bağlandı. Marka yarın metni\n` +
    `değiştirse bile bu sipariş gördüğün sürüme bağlı kalır.`);
});

/* ─────── Ödeme ─────── */
$('btnOde').addEventListener('click', async () => {
  if (!siparis) return;
  yaz('odemeCikti', 'Sağlayıcıya gidiliyor…');

  const cevap = await iste(`/api/orders/${siparis.uuid}/pay`, { method: 'POST' });

  if (cevap.durum === 503) return yaz('odemeCikti', '⚠ Ödeme yapılandırılmamış: markanın sağlayıcı anahtarları eksik.\n   Panelden /panel/payment ile girilir.');
  if (cevap.durum !== 200) return yaz('odemeCikti', cevap.govde);

  yaz('odemeCikti',
    `✓ ödeme başlatıldı\n` +
    `  referans : ${cevap.govde.reference}\n\n` +
    `Yönlendiriliyorsun. Kart bilgisi SAĞLAYICININ sayfasına girilecek;\n` +
    `bu sisteme hiç değmiyor. Test kartları yukarıdaki bölümde.`);

  setTimeout(() => window.open(cevap.govde.redirect_url, '_blank'), 1200);
});

/* ─────── Olay akışı ─────── */
const etiket = {
  product_viewed: ['ürün görüntülendi', 'bg-sky-500/15 text-sky-300'],
  cart_item_added: ['sepete eklendi', 'bg-emerald-500/15 text-emerald-300'],
  cart_item_removed: ['sepetten çıkarıldı', 'bg-amber-500/15 text-amber-300'],
  order_placed: ['sipariş verildi', 'bg-violet-500/15 text-violet-300'],
  search_performed: ['arama yapıldı', 'bg-slate-500/15 text-slate-300'],
};

async function olaylariYukle() {
  try {
    const cevap = await fetch('/showcase/events', { headers: { 'Accept': 'application/json', ...NGROK_BASLIK } });
    const veri = await cevap.json();

    $('olaySayac').innerHTML = Object.entries(veri.counts || {}).map(([tip, adet]) => {
      const [ad, renk] = etiket[tip] || [tip, 'bg-slate-700 text-slate-300'];
      return `<span class="rounded-full ${renk} px-3 py-1 text-xs font-medium">${ad}: ${adet}</span>`;
    }).join('') || '<span class="text-xs text-slate-600">henüz olay yok</span>';

    const satirlar = veri.events || [];
    $('olayListe').innerHTML = satirlar.length ? satirlar.map(o => {
      const [ad, renk] = etiket[o.type] || [o.type, 'bg-slate-700 text-slate-300'];
      const an = o.at ? new Date(o.at).toLocaleTimeString('tr-TR') : '—';
      return `<tr><td class="px-4 py-2"><span class="rounded ${renk} px-2 py-0.5 text-xs">${ad}</span></td>
              <td class="kod px-4 py-2 text-xs text-slate-500">${an}</td></tr>`;
    }).join('') : '<tr><td colspan="2" class="px-4 py-6 text-center text-slate-600">Henüz olay yok — yukarıdaki düğmelere bas.</td></tr>';
  } catch (e) { /* sessiz: sunum ekranı hata gösterip dikkat dağıtmasın */ }
}

kataloguYukle();
olaylariYukle();
setInterval(olaylariYukle, 5000);
</script>
@endverbatim
</body>
</html>
