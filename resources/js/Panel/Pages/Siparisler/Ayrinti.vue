<script setup>
/*
 | Sipariş ayrıntısı ve kargolama. (4E)
 |
 | ⚠️ Kargolama düğmeleri `order.fulfill` izni yoksa GİZLENİYOR — ama bu
 | bir kolaylık; gerçek koruma sunucudaki `izin:order.fulfill` (4C-K4).
 */
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'

const props = defineProps({ siparis: Object })

const izinler = computed(() => usePage().props.auth?.permissions ?? [])
const kargolayabilir = computed(() => izinler.value.includes('order.fulfill'))

/* ⚠️ İade AÇMAK da `order.refund` — para iadesi zincirinin ilk halkası. */
const iadeEdebilir = computed(() => izinler.value.includes('order.refund'))

const odendi = computed(() => ['paid', 'partially_refunded'].includes(props.siparis.payment_status))

/* Kalan adet = sipariş edilen − sevk edilen. */
const kalanlar = computed(() =>
  props.siparis.items.map((s) => ({ ...s, kalan: s.quantity - s.shipped })),
)

const paket = reactive({ carrier: '', tracking_number: '', adetler: {} })

function paketOlustur() {
  const items = Object.entries(paket.adetler)
    .map(([id, adet]) => ({ order_item_id: Number(id), quantity: Number(adet) }))
    .filter((s) => s.quantity > 0)

  if (items.length === 0) return

  router.post(`/yonetim/siparisler/${props.siparis.uuid}/paketler`, {
    items,
    carrier: paket.carrier || null,
    tracking_number: paket.tracking_number || null,
  }, { onSuccess: () => { paket.adetler = {}; paket.carrier = ''; paket.tracking_number = '' } })
}

function kargoyaVer(u) { router.post(`/yonetim/siparisler/${props.siparis.uuid}/paketler/${u}/kargo`) }
function teslimEt(u) { router.post(`/yonetim/siparisler/${props.siparis.uuid}/paketler/${u}/teslim`) }
function paketIptal(u) {
  if (confirm('Paket iptal edilsin mi?')) {
    router.delete(`/yonetim/siparisler/${props.siparis.uuid}/paketler/${u}`)
  }
}

/*
 | ⚠️ TEK ADIMDA TAMAMLAMA (4.5L). Kargo entegrasyonu (Faz 5) gelene
 | kadar marka siparişi "bitti" diye kapatamıyordu: tek yol satır satır
 | adet girip paket açmak, sonra iki düğmeye daha basmaktı.
 |
 | ⚠️ Kalan adetleri SUNUCU hesaplıyor — burada hesaplanıp gönderilseydi
 | aynı kural iki yerde durur ve ekran bayatladığında (başka sekmede
 | paket açılmışsa) fazladan sevkiyat denenirdi.
 */
function tamamla() {
  if (!confirm('Kalan tüm satırlar sevk edilip sipariş teslim edildi olarak kapatılsın mı?')) return
  router.post(`/yonetim/siparisler/${props.siparis.uuid}/tamamla`)
}

const iade = reactive({ reason: '', adetler: {} })
const iadeFormu = ref(false)

function iadeAc() {
  const items = Object.entries(iade.adetler)
    .map(([id, adet]) => ({ order_item_id: Number(id), quantity: Number(adet) }))
    .filter((s) => s.quantity > 0)

  if (items.length === 0 || iade.reason.trim() === '') return

  router.post(`/yonetim/siparisler/${props.siparis.uuid}/iade`, { items, reason: iade.reason })
}

function para(v) { return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL' }
const paketDurumu = { pending: 'Hazırlanıyor', shipped: 'Kargoda', delivered: 'Teslim edildi', cancelled: 'İptal' }
</script>

<template>
  <Head :title="siparis.order_number" />

  <PanelDuzeni>
    <div class="flex items-center gap-3 mb-6">
      <Link href="/yonetim/siparisler" class="text-sm text-stone-600 hover:text-orange-600">← Siparişler</Link>
      <h1 class="text-2xl font-bold">{{ siparis.order_number }}</h1>
      <span class="text-stone-500">{{ para(siparis.grand_total) }}</span>
    </div>

    <div v-if="siparis.stock_shortfall" class="mb-4 rounded-lg bg-red-100 border border-red-300 px-4 py-3 text-sm">
      ⚠ Bu siparişte stok açığı var — sipariş alındı ama stok yetmiyor.
    </div>

    <div class="grid grid-cols-3 gap-6">
      <div class="col-span-2 space-y-6">
        <div class="rounded-xl bg-white border border-stone-200 p-5">
          <h2 class="font-semibold mb-3">Ürünler</h2>
          <table class="w-full text-sm">
            <tr v-for="s in kalanlar" :key="s.id" class="border-b border-stone-100">
              <td class="py-2">
                {{ s.title }} <code class="text-stone-500">{{ s.sku }}</code>
              </td>
              <td class="py-2">{{ s.quantity }} adet</td>
              <!-- ⚠️ SEVK EDİLEN ayrı gösteriliyor: kısmi sevkiyatta marka
                   neyin gittiğini bilmeden ikinci paketi hazırlayamaz. -->
              <td class="py-2 text-stone-600">{{ s.shipped }} sevk edildi</td>
              <td class="py-2 text-right">{{ para(s.line_total) }}</td>
            </tr>
          </table>
        </div>

        <div v-if="kargolayabilir && odendi && kalanlar.some((s) => s.kalan > 0)"
             class="rounded-xl bg-white border border-stone-200 p-5">
          <h2 class="font-semibold mb-1">Siparişi tamamla</h2>
          <p class="text-sm text-stone-600 mb-3">
            Kalan tüm satırlar tek pakette sevk edilir ve sipariş teslim edildi olarak kapanır.
            Kargo firması takip etmek istersen aşağıdaki paket bölümünü kullan.
          </p>
          <button type="button" class="rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm font-semibold" @click="tamamla">
            Siparişi tamamla
          </button>
        </div>

        <div v-if="kargolayabilir" class="rounded-xl bg-white border border-stone-200 p-5">
          <h2 class="font-semibold mb-3">Yeni paket</h2>

          <p v-if="kalanlar.every((s) => s.kalan <= 0)" class="text-sm text-stone-600">
            Bu siparişin tamamı sevk edildi.
          </p>

          <template v-else>
            <div v-for="s in kalanlar.filter((x) => x.kalan > 0)" :key="s.id" class="flex items-center gap-3 mb-2 text-sm">
              <span class="flex-1">{{ s.title }}</span>
              <span class="text-stone-500">kalan {{ s.kalan }}</span>
              <input v-model="paket.adetler[s.id]" type="number" min="0" :max="s.kalan"
                     class="w-20 rounded-lg border border-stone-300 px-2 py-1">
            </div>

            <div class="flex gap-2 mt-3">
              <input v-model="paket.carrier" placeholder="Kargo firması" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
              <input v-model="paket.tracking_number" placeholder="Takip no" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
              <button type="button" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold" @click="paketOlustur">
                Paket oluştur
              </button>
            </div>
          </template>
        </div>

        <!--
          ⚠️ İADE AÇMA (4.5L): panel iadeyi İŞLEYEBİLİYORDU (onayla ·
          teslim al · para iadesi) ama AÇAMIYORDU. Vitrinde de ekranı yok,
          yani iade pratikte ulaşılamaz bir özellikti.

          ⚠️ Ödenmemiş siparişte hiç gösterilmiyor: geri verilecek para yok
          ve servis zaten reddediyor.
        -->
        <div v-if="iadeEdebilir && odendi" class="rounded-xl bg-white border border-stone-200 p-5">
          <div class="flex items-center justify-between">
            <h2 class="font-semibold">İade</h2>
            <button type="button" class="text-sm text-orange-700" @click="iadeFormu = !iadeFormu">
              {{ iadeFormu ? 'vazgeç' : 'İade talebi aç' }}
            </button>
          </div>

          <template v-if="iadeFormu">
            <div v-for="s in siparis.items" :key="s.id" class="flex items-center gap-3 mt-3 text-sm">
              <span class="flex-1">{{ s.title }}</span>
              <span class="text-stone-500">{{ s.quantity }} adet</span>
              <input v-model="iade.adetler[s.id]" type="number" min="0" :max="s.quantity"
                     class="w-20 rounded-lg border border-stone-300 px-2 py-1">
            </div>

            <input v-model="iade.reason" placeholder="Sebep (zorunlu)"
                   class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mt-3">

            <p class="text-xs text-stone-500 mt-2">
              Bu talep cayma değil, marka tarafından açılan iadedir — 14 günlük cayma süresine takılmaz.
            </p>

            <button type="button" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold mt-3" @click="iadeAc">
              Talebi aç
            </button>
          </template>
        </div>

        <div class="rounded-xl bg-white border border-stone-200 p-5">
          <h2 class="font-semibold mb-3">Paketler</h2>

          <p v-if="siparis.fulfillments.length === 0" class="text-sm text-stone-600">Henüz paket yok.</p>

          <div v-for="p in siparis.fulfillments" :key="p.uuid" class="border-b border-stone-100 py-3 text-sm">
            <div class="flex items-center gap-3">
              <span class="font-medium">{{ paketDurumu[p.status] ?? p.status }}</span>
              <span v-if="p.carrier" class="text-stone-600">{{ p.carrier }}</span>
              <code v-if="p.tracking_number" class="text-stone-500">{{ p.tracking_number }}</code>

              <span v-if="kargolayabilir" class="ml-auto flex gap-2">
                <button v-if="p.status === 'pending'" type="button" class="rounded border border-stone-300 px-2 py-1" @click="kargoyaVer(p.uuid)">Kargoya ver</button>
                <button v-if="p.status === 'shipped'" type="button" class="rounded border border-stone-300 px-2 py-1" @click="teslimEt(p.uuid)">Teslim edildi</button>
                <button v-if="p.status !== 'cancelled' && p.status !== 'delivered'" type="button" class="rounded border border-red-300 text-red-700 px-2 py-1" @click="paketIptal(p.uuid)">İptal</button>
              </span>
            </div>
          </div>
        </div>
      </div>

      <aside class="space-y-6">
        <div class="rounded-xl bg-white border border-stone-200 p-5 text-sm">
          <h2 class="font-semibold mb-3">Teslimat</h2>
          <p>{{ siparis.shipping_address.full_name }}</p>
          <p class="text-stone-600">{{ siparis.shipping_address.phone }}</p>
          <p class="text-stone-600">{{ siparis.shipping_address.line1 }}</p>
          <p class="text-stone-600">{{ siparis.shipping_address.district }} / {{ siparis.shipping_address.city }}</p>
        </div>

        <div class="rounded-xl bg-white border border-stone-200 p-5 text-sm">
          <h2 class="font-semibold mb-3">Tutarlar</h2>
          <div class="flex justify-between py-1"><span>Ürünler</span><span>{{ para(siparis.items_total) }}</span></div>
          <div class="flex justify-between py-1"><span>Kargo</span><span>{{ para(siparis.shipping_total) }}</span></div>
          <!-- ⚠️ KDV bilgi amaçlı: tahsil edilen tutarın İÇİNDE (§8.2). -->
          <div class="flex justify-between py-1 text-stone-500"><span>KDV (dâhil)</span><span>{{ para(siparis.tax_total) }}</span></div>
          <div class="flex justify-between py-2 border-t border-stone-200 font-semibold"><span>Toplam</span><span>{{ para(siparis.grand_total) }}</span></div>
        </div>

        <!-- ⚠️ Müşterinin ONAYLADIĞI sözleşme sürümü: "neyi kabul etti"
             sorusu sonradan tartışmasız cevaplanabilsin diye (1D-K2). -->
        <div v-if="siparis.contract_version" class="rounded-xl bg-white border border-stone-200 p-5 text-sm">
          <h2 class="font-semibold mb-1">Sözleşme</h2>
          <p class="text-stone-600">Onaylanan sürüm: v{{ siparis.contract_version }}</p>
        </div>
      </aside>
    </div>
  </PanelDuzeni>
</template>
