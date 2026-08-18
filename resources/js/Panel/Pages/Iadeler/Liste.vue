<script setup>
/* İade talepleri. (4E) — görmek `order.view`, karar vermek `order.refund`. */
import { Head, Link } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'

defineProps({ talepler: Object })

const durumAdi = {
  requested: 'Talep edildi', approved: 'Onaylandı', rejected: 'Reddedildi',
  received: 'Teslim alındı', completed: 'Tamamlandı',
}
const durumRengi = {
  requested: 'bg-amber-100 text-amber-800', approved: 'bg-blue-100 text-blue-800',
  rejected: 'bg-stone-200 text-stone-700', received: 'bg-indigo-100 text-indigo-800',
  completed: 'bg-green-100 text-green-800',
}
function tarih(v) { return v ? new Date(v).toLocaleString('tr-TR') : '—' }
</script>

<template>
  <Head title="İadeler" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-6">İade talepleri</h1>

    <div v-if="talepler.data.length === 0" class="rounded-xl bg-white border border-stone-200 p-10 text-center text-stone-600">
      İade talebi yok.
    </div>

    <table v-else class="w-full bg-white rounded-xl border border-stone-200 overflow-hidden">
      <thead class="bg-stone-50 text-left text-sm text-stone-600">
        <tr><th class="p-3">Sipariş</th><th class="p-3">Tür</th><th class="p-3">Ürün</th><th class="p-3">Durum</th><th class="p-3">Tarih</th></tr>
      </thead>
      <tbody>
        <tr v-for="t in talepler.data" :key="t.uuid" class="border-t border-stone-100">
          <td class="p-3">
            <Link :href="`/yonetim/iadeler/${t.uuid}`" class="font-medium hover:text-orange-600">{{ t.order_number }}</Link>
          </td>
          <!-- ⚠️ CAYMA mı AYIPLI mı: kargo bedelinin geri verilip
               verilmeyeceğini bu belirliyor (2B-K1). -->
          <td class="p-3 text-sm">{{ t.is_withdrawal ? 'Cayma' : 'Ayıplı ürün' }}</td>
          <td class="p-3 text-sm">{{ t.item_count }} adet</td>
          <td class="p-3">
            <span class="rounded-full px-2 py-0.5 text-xs" :class="durumRengi[t.status]">{{ durumAdi[t.status] ?? t.status }}</span>
          </td>
          <td class="p-3 text-sm">{{ tarih(t.created_at) }}</td>
        </tr>
      </tbody>
    </table>
  </PanelDuzeni>
</template>
