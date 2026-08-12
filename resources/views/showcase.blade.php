{{--
    TıkMarka — sunum vitrini (tek dosya)

    ⚠️ Backend'e dokunmuyor: model/servis/controller kullanmıyor.
    Her şey gerçek API uçlarına tarayıcıdan `fetch` ile gidiyor.
--}}
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TıkMarka — Vitrin</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<style>
  body{font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
  .kod{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
</style>
</head>
<body class="bg-slate-950 text-slate-200">

<div class="mx-auto max-w-6xl px-5 py-8">

  <header class="mb-8 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-white">TıkMarka</h1>
    <div class="kod rounded border border-slate-800 bg-slate-900 px-3 py-1.5 text-xs text-emerald-400">
      {{ $alanAdi }}
    </div>
  </header>

  <div class="grid gap-6 lg:grid-cols-2">

    {{-- KATALOG --}}
    <section class="rounded-lg border border-slate-800 bg-slate-900/50 p-5">
      <div class="mb-1 flex items-baseline gap-2">
        <h2 class="font-semibold text-white">Katalog</h2>
        <span class="kod text-xs text-slate-500">GET /api/products</span>
      </div>
      <p class="mb-4 text-xs text-slate-500">Yalnızca satılabilir ürünler.</p>

      <div id="kategoriler" class="mb-4 flex flex-wrap gap-2"></div>
      <div id="katalog" class="grid gap-3 sm:grid-cols-2"></div>
    </section>

    {{-- VARYANT --}}
    <section class="rounded-lg border border-slate-800 bg-slate-900/50 p-5">
      <div class="mb-1 flex items-baseline gap-2">
        <h2 class="font-semibold text-white">Varyantlar</h2>
        <span class="kod text-xs text-slate-500">GET /api/products/{slug}</span>
      </div>
      <p class="mb-4 text-xs text-slate-500">Ürüne tıkla.</p>
      <div id="varyantlar" class="text-sm text-slate-600">—</div>
    </section>

    {{-- SEPET --}}
    <section class="rounded-lg border border-slate-800 bg-slate-900/50 p-5">
      <div class="mb-1 flex items-baseline gap-2">
        <h2 class="font-semibold text-white">Sepet</h2>
        <span class="kod text-xs text-slate-500">POST/GET/DELETE /api/cart</span>
      </div>
      <p class="mb-4 text-xs text-slate-500">Misafir sepeti — hesap yok.</p>
      <div id="sepet" class="mb-3 text-sm text-slate-600">Boş.</div>
      <button id="btnSepetYenile" class="rounded bg-slate-800 px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-700">Yenile</button>
    </section>

    {{-- SÖZLEŞME --}}
    <section class="rounded-lg border border-slate-800 bg-slate-900/50 p-5">
      <div class="mb-1 flex items-baseline gap-2">
        <h2 class="font-semibold text-white">Sözleşme</h2>
        <span class="kod text-xs text-slate-500">GET /api/legal/{tur}</span>
      </div>
      <p class="mb-4 text-xs text-slate-500">Sipariş gördüğün sürüme bağlanır.</p>
      <div class="mb-3 flex flex-wrap gap-2">
        <button data-legal="distance_sales" class="legal rounded bg-slate-800 px-3 py-1.5 text-xs hover:bg-slate-700">Mesafeli satış</button>
        <button data-legal="privacy" class="legal rounded bg-slate-800 px-3 py-1.5 text-xs hover:bg-slate-700">KVKK</button>
        <button data-legal="returns" class="legal rounded bg-slate-800 px-3 py-1.5 text-xs hover:bg-slate-700">İade</button>
      </div>
      <pre id="sozlesme" class="kod max-h-40 overflow-auto rounded bg-slate-950 p-3 text-xs text-slate-500">—</pre>
    </section>
  </div>

  {{-- SİPARİŞ + ÖDEME --}}
  <section class="mt-6 rounded-lg border border-slate-800 bg-slate-900/50 p-5">
    <div class="mb-1 flex items-baseline gap-2">
      <h2 class="font-semibold text-white">Sipariş ve ödeme</h2>
      <span class="kod text-xs text-slate-500">POST /api/checkout → /api/orders/{uuid}/pay</span>
    </div>
    <p class="mb-4 text-xs text-slate-500">Tutar sunucuda üretilir. Kart bize değmez.</p>

    <div class="mb-4 flex flex-wrap gap-2">
      <button id="btnSiparis" disabled class="rounded bg-emerald-600 px-3 py-1.5 text-sm text-white hover:bg-emerald-500 disabled:bg-slate-800 disabled:text-slate-600">Sipariş oluştur</button>
      <button id="btnOde" disabled class="rounded bg-violet-600 px-3 py-1.5 text-sm text-white hover:bg-violet-500 disabled:bg-slate-800 disabled:text-slate-600">Ödemeyi başlat</button>
      <button id="btnDurum" disabled class="rounded bg-slate-800 px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-700 disabled:text-slate-600">Ödeme durumu</button>
      <span class="kod self-center text-xs text-slate-600">GET /odeme/donus</span>
    </div>

    <pre id="cikti" class="kod max-h-64 overflow-auto rounded bg-slate-950 p-4 text-xs text-slate-400">—</pre>

    <details class="mt-3 text-xs">
      <summary class="cursor-pointer text-slate-500">Test kartları</summary>
      <div class="kod mt-2 space-y-1 text-slate-400">
        <div><span class="text-emerald-400">5890040000000016</span> başarılı · 12/30 · 123</div>
        <div><span class="text-rose-400">4111111111111129</span> yetersiz bakiye</div>
        <div class="text-amber-400">SMS kodu 3DS ekranında yazıyor</div>
      </div>
    </details>
  </section>

  {{-- OLAYLAR --}}
  <section class="mt-6 rounded-lg border border-slate-800 bg-slate-900/50 p-5">
    <div class="mb-1 flex items-baseline gap-2">
      <h2 class="font-semibold text-white">Olay kaydı</h2>
      <span class="kod text-xs text-slate-500">events tablosu</span>
      <span class="ml-auto flex items-center gap-1.5 text-xs text-slate-500">
        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>5 sn
      </span>
    </div>
    <p class="mb-4 text-xs text-slate-500">Kuyrukta yazılır — birkaç saniye gecikir.</p>

    <div id="sayac" class="mb-3 flex flex-wrap gap-2"></div>
    <div id="olaylar" class="divide-y divide-slate-800 rounded border border-slate-800 text-sm"></div>
  </section>

  <footer class="mt-8 text-xs text-slate-600">
    Salt okunur sunum. Kategori sayfası, ürün detayı ve arama henüz yok — Faz 2.
  </footer>
</div>

@verbatim
<script>
const $ = (id) => document.getElementById(id);
const NG = { 'ngrok-skip-browser-warning': '1' };
let sepetToken = null, siparis = null, referans = null, secilenSlug = null;

const yaz = (v) => $('cikti').textContent = typeof v === 'string' ? v : JSON.stringify(v, null, 2);

async function iste(yol, secenek = {}) {
  const h = { 'Accept': 'application/json', 'Content-Type': 'application/json', ...NG, ...(secenek.headers || {}) };
  if (sepetToken) h['X-Cart-Token'] = sepetToken;
  const c = await fetch(yol, { ...secenek, headers: h });
  const t = await c.text();
  try { return { durum: c.status, govde: JSON.parse(t) }; }
  catch { return { durum: c.status, govde: { hata: 'JSON değil', onizleme: t.slice(0, 150) } }; }
}

/* Katalog + kategori */
async function kategorileriYukle() {
  const { govde } = await iste('/api/categories');
  const k = govde.categories || [];
  $('kategoriler').innerHTML =
    `<button data-kat="" class="kat rounded-full bg-sky-600 px-3 py-1 text-xs text-white">Tümü</button>` +
    k.map(x => `<button data-kat="${x.slug}" class="kat rounded-full bg-slate-800 px-3 py-1 text-xs hover:bg-slate-700">${x.name}</button>`).join('');
  document.querySelectorAll('.kat').forEach(b => b.onclick = () => {
    document.querySelectorAll('.kat').forEach(x => x.className = x.className.replace('bg-sky-600 text-white', 'bg-slate-800'));
    b.className = b.className.replace('bg-slate-800', 'bg-sky-600 text-white');
    kataloguYukle(b.dataset.kat);
  });
}

async function kataloguYukle(kategori = '') {
  const { durum, govde } = await iste('/api/products' + (kategori ? '?category=' + kategori : ''));
  if (durum !== 200) return $('katalog').innerHTML = `<div class="text-sm text-rose-400">HTTP ${durum}</div>`;
  const u = govde.products || [];
  $('katalog').innerHTML = u.length ? u.map(x => `
    <button data-slug="${x.slug}" class="urun overflow-hidden rounded border border-slate-800 bg-slate-900 text-left hover:border-slate-600">
      ${x.image ? `<img src="${x.image}" class="h-24 w-full object-cover">` : '<div class="h-24 bg-slate-800"></div>'}
      <div class="p-3">
        <div class="text-sm font-medium text-white">${x.title}</div>
        <div class="text-xs text-slate-500">${x.from_price ?? '—'} TL</div>
      </div>
    </button>`).join('') : '<div class="text-sm text-slate-600">Ürün yok.</div>';
  document.querySelectorAll('.urun').forEach(b => b.onclick = () => varyantlariYukle(b.dataset.slug));
}

/* Varyantlar */
async function varyantlariYukle(slug) {
  secilenSlug = slug;
  $('varyantlar').innerHTML = '<span class="text-slate-600">yükleniyor…</span>';
  const { govde } = await iste('/api/products/' + slug);
  const v = govde.product?.variants || [];
  $('varyantlar').innerHTML = `<div class="mb-2 text-sm text-white">${govde.product?.title ?? ''}</div>` + v.map(x => `
    <div class="flex items-center justify-between border-t border-slate-800 py-2 text-xs">
      <span class="kod ${x.in_stock ? 'text-slate-300' : 'text-slate-600 line-through'}">${x.sku}</span>
      <span class="text-slate-500">${x.price} TL</span>
      <button data-uuid="${x.uuid}" ${x.in_stock ? '' : 'disabled'}
        class="ekle rounded bg-sky-700 px-2 py-1 text-white hover:bg-sky-600 disabled:bg-slate-800 disabled:text-slate-600">Ekle</button>
    </div>`).join('');
  document.querySelectorAll('.ekle').forEach(b => b.onclick = () => sepeteEkle(b.dataset.uuid));
}

/* Sepet */
async function sepeteEkle(uuid) {
  const { durum, govde } = await iste('/api/cart/items', { method: 'POST', body: JSON.stringify({ variant_uuid: uuid, quantity: 1 }) });
  if (durum !== 201) return yaz(govde);
  sepetToken = govde.cart_token;
  sepetiGoster(govde);
}

async function sepetiYukle() {
  if (!sepetToken) return $('sepet').textContent = 'Boş.';
  const { govde } = await iste('/api/cart');
  sepetiGoster(govde);
}

function sepetiGoster(s) {
  const satirlar = s.items || [];
  $('sepet').innerHTML = satirlar.map(x => `
    <div class="flex items-center justify-between border-b border-slate-800 py-2 text-xs">
      <span class="kod text-slate-300">${x.sku} ×${x.quantity}</span>
      <span class="text-slate-500">${x.line_total} TL</span>
      <button data-uuid="${x.variant_uuid}" class="sil rounded bg-slate-800 px-2 py-1 text-rose-400 hover:bg-slate-700">Sil</button>
    </div>`).join('') + `<div class="pt-2 text-sm text-white">Toplam: ${s.subtotal} TL</div>`;
  $('btnSiparis').disabled = satirlar.length === 0;
  document.querySelectorAll('.sil').forEach(b => b.onclick = async () => {
    await iste('/api/cart/items/' + b.dataset.uuid, { method: 'DELETE' });
    sepetiYukle();
  });
}

$('btnSepetYenile').onclick = sepetiYukle;

/* Sözleşme */
document.querySelectorAll('.legal').forEach(b => b.onclick = async () => {
  const { durum, govde } = await iste('/api/legal/' + b.dataset.legal);
  $('sozlesme').textContent = durum === 200
    ? `sürüm ${govde.document.version} (id ${govde.document.version_id})\n\n${govde.document.content.slice(0, 400)}`
    : `HTTP ${durum} — yayınlanmamış`;
});

/* Sipariş */
$('btnSiparis').onclick = async () => {
  const m = await iste('/api/legal/distance_sales');
  const surum = m.govde.document?.version_id;
  if (!surum) return yaz('Sözleşme yayınlanmamış.');

  const { durum, govde } = await iste('/api/checkout', {
    method: 'POST',
    body: JSON.stringify({
      email: 'vitrin@example.com', legal_version_id: surum,
      shipping: { full_name: 'Ayse Yilmaz', phone: '+905321112233', city: 'Istanbul', district: 'Kadikoy', line1: 'Moda Cad. No:12' },
    }),
  });
  if (durum !== 201) return yaz(govde);

  siparis = govde.order; sepetToken = null;
  $('btnSiparis').disabled = true; $('btnOde').disabled = false;
  $('sepet').textContent = 'Siparişe dönüştü.';
  yaz(`${siparis.order_number}\nürün ${siparis.items_total} + kargo ${siparis.shipping_total} = ${siparis.grand_total} TL\nKDV ${siparis.tax_total} (içinde)\ndurum ${siparis.payment_status}`);
};

/* Ödeme */
$('btnOde').onclick = async () => {
  const { durum, govde } = await iste(`/api/orders/${siparis.uuid}/pay`, { method: 'POST' });
  if (durum === 503) return yaz('Sağlayıcı anahtarları eksik (/panel/payment).');
  if (durum !== 200) return yaz(govde);
  referans = govde.reference;
  $('btnDurum').disabled = false;
  yaz(`referans ${referans}\nyönlendiriliyor…`);
  setTimeout(() => window.open(govde.redirect_url, '_blank'), 800);
};

$('btnDurum').onclick = async () => {
  const { durum, govde } = await iste('/odeme/donus?ref=' + referans);
  yaz(durum === 200 ? `${govde.order_number}\ndurum ${govde.payment_status} → ${govde.state}` : govde);
};

/* Olaylar */
const et = {
  product_viewed: ['görüntülendi', 'text-sky-300'],
  cart_item_added: ['sepete eklendi', 'text-emerald-300'],
  cart_item_removed: ['sepetten çıktı', 'text-amber-300'],
  order_placed: ['sipariş verildi', 'text-violet-300'],
  search_performed: ['arama', 'text-slate-300'],
};

async function olaylar() {
  try {
    const c = await fetch('/showcase/events', { headers: { 'Accept': 'application/json', ...NG } });
    const v = await c.json();
    $('sayac').innerHTML = Object.entries(v.counts || {}).map(([t, n]) => {
      const [ad, r] = et[t] || [t, 'text-slate-300'];
      return `<span class="rounded bg-slate-800 px-2 py-1 text-xs ${r}">${ad} ${n}</span>`;
    }).join('') || '<span class="text-xs text-slate-600">yok</span>';

    $('olaylar').innerHTML = (v.events || []).map(o => {
      const [ad, r] = et[o.type] || [o.type, 'text-slate-300'];
      return `<div class="flex justify-between px-3 py-2 text-xs">
        <span class="${r}">${ad}</span>
        <span class="kod text-slate-600">${o.at ? new Date(o.at).toLocaleTimeString('tr-TR') : '—'}</span></div>`;
    }).join('') || '<div class="px-3 py-4 text-center text-xs text-slate-600">Henüz yok.</div>';
  } catch {}
}

kategorileriYukle();
kataloguYukle();
olaylar();
setInterval(olaylar, 5000);
</script>
@endverbatim
</body>
</html>
