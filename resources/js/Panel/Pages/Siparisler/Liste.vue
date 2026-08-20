<script setup>
/* Sipariş listesi. (4E) — `izin:order.view` arkasında. */
import { Head, Link, router } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'
import { tarih } from '../../Yardimcilar/tarih'

const props = defineProps({ siparisler: Object, durum: String })

const odemeAdi = { paid: 'Ödendi', pending: 'Bekliyor', failed: 'Başarısız', cancelled: 'İptal', refunded: 'İade' }
const odemeRengi = {
  paid: 'bg-green-100 text-green-800',
  pending: 'bg-amber-100 text-amber-800',
  failed: 'bg-red-100 text-red-800',
  cancelled: 'bg-stone-200 text-stone-700',
  refunded: 'bg-blue-100 text-blue-800',
}
const kargoAdi = { unfulfilled: 'Bekliyor', partial: 'Kısmi', fulfilled: 'Tamam', cancelled: 'İptal' }

function suz(deger) {
  router.get('/yonetim/siparisler', { durum: deger || undefined }, { preserveState: true })
}

function para(v) { return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL' }
</script>

<template>
  <Head title="Siparişler" />

  <PanelDuzeni>
    <div class="flex items-center gap-4 mb-6">
      <h1 class="text-2xl font-bold">Siparişler</h1>

      <select class="ml-auto rounded-lg border border-stone-300 px-3 py-2 text-sm" :value="durum ?? ''" @change="suz($event.target.value)">
        <option value="">Tüm kargo durumları</option>
        <option value="unfulfilled">Bekleyen</option>
        <option value="partial">Kısmi</option>
        <option value="fulfilled">Tamamlanan</option>
      </select>
    </div>

    <div v-if="siparisler.data.length === 0" class="rounded-xl bg-white border border-stone-200 p-10 text-center text-stone-600">
      Henüz sipariş yok.
    </div>

    <table v-else class="w-full bg-white rounded-xl border border-stone-200 overflow-hidden">
      <thead class="bg-stone-50 text-left text-sm text-stone-600">
        <tr>
          <th class="p-3">Sipariş</th><th class="p-3">Tarih</th><th class="p-3">Ödeme</th>
          <th class="p-3">Kargo</th><th class="p-3">Tutar</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in siparisler.data" :key="s.uuid" class="border-t border-stone-100">
          <td class="p-3">
            <Link :href="`/yonetim/siparisler/${s.uuid}`" class="font-medium hover:text-orange-600">
              {{ s.order_number }}
            </Link>
            <!-- ⚠️ STOK AÇIĞI LİSTEDE görünüyor: yalnızca ayrıntıda olsaydı
                 marka onu ancak siparişi açınca fark ederdi. -->
            <div v-if="s.stock_shortfall" class="text-xs text-red-700">⚠ stok açığı</div>
            <div class="text-xs text-stone-500">{{ s.email }}</div>
          </td>
          <td class="p-3 text-sm">{{ tarih(s.placed_at) }}</td>
          <td class="p-3">
            <span class="rounded-full px-2 py-0.5 text-xs" :class="odemeRengi[s.payment_status]">
              {{ odemeAdi[s.payment_status] ?? s.payment_status }}
            </span>
          </td>
          <td class="p-3 text-sm">{{ kargoAdi[s.fulfillment_status] ?? s.fulfillment_status }}</td>
          <td class="p-3 text-sm">{{ para(s.grand_total) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="siparisler.links.length > 3" class="mt-4 flex gap-1 text-sm">
      <Link v-for="b in siparisler.links" :key="b.label" :href="b.url ?? ''"
            class="rounded border border-stone-300 px-3 py-1 bg-white"
            :class="{ 'bg-orange-600 text-white border-orange-600': b.active, 'opacity-40 pointer-events-none': !b.url }"
            v-html="b.label" />
    </div>
  </PanelDuzeni>
</template>
