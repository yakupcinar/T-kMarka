<script setup>
/*
 | Ürün listesi. (4D)
 |
 | ⚠️ Bu sayfa `izin:product.write` ARKASINDA. Menüde gizlemek bir
 | kolaylıktı; adresi elle yazan yetkisiz personel sunucudan 403 alıyor.
 */
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'

const props = defineProps({
  urunler: Object,
  arama: String,
})

const kelime = ref(props.arama ?? '')

function ara() {
  router.get('/yonetim/urunler', { q: kelime.value || undefined }, { preserveState: true })
}

const durumRengi = {
  active: 'bg-green-100 text-green-800',
  draft: 'bg-stone-200 text-stone-700',
  archived: 'bg-amber-100 text-amber-800',
}

const durumAdi = { active: 'Yayında', draft: 'Taslak', archived: 'Arşiv' }

function para(deger) {
  if (deger === null || deger === undefined) return '—'
  return Number(deger).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL'
}
</script>

<template>
  <Head title="Ürünler" />

  <PanelDuzeni>
    <div class="flex items-center gap-4 mb-6">
      <h1 class="text-2xl font-bold">Ürünler</h1>

      <form class="ml-auto flex gap-2" @submit.prevent="ara">
        <input
          v-model="kelime"
          type="search"
          placeholder="Ürün ara"
          class="rounded-lg border border-stone-300 px-3 py-2 text-sm"
        >
        <button type="submit" class="rounded-lg border border-stone-300 px-3 py-2 text-sm bg-white">Ara</button>
      </form>

      <Link
        href="/yonetim/urunler/yeni"
        class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold"
      >Yeni ürün</Link>
    </div>

    <!-- ⚠️ Boş liste "hata" gibi görünmemeli: yeni marka için NORMAL durum. -->
    <div v-if="urunler.data.length === 0" class="rounded-xl bg-white border border-stone-200 p-10 text-center text-stone-600">
      <p v-if="arama">“{{ arama }}” için ürün bulunamadı.</p>
      <p v-else>Henüz ürün yok. İlk ürününüzü ekleyin.</p>
    </div>

    <table v-else class="w-full bg-white rounded-xl border border-stone-200 overflow-hidden">
      <thead class="bg-stone-50 text-left text-sm text-stone-600">
        <tr>
          <th class="p-3">Ürün</th>
          <th class="p-3">Durum</th>
          <th class="p-3">Varyant</th>
          <th class="p-3">Stok</th>
          <th class="p-3">Fiyat</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="urun in urunler.data" :key="urun.uuid" class="border-t border-stone-100">
          <td class="p-3">
            <Link :href="`/yonetim/urunler/${urun.uuid}`" class="font-medium hover:text-orange-600">
              {{ urun.title }}
            </Link>
          </td>
          <td class="p-3">
            <span class="rounded-full px-2 py-0.5 text-xs" :class="durumRengi[urun.status]">
              {{ durumAdi[urun.status] }}
            </span>
          </td>
          <!-- ⚠️ Varyantsız ürün SATILAMAZ; sayı sıfırsa uyarı veriyoruz. -->
          <td class="p-3 text-sm">
            <span v-if="urun.variant_count === 0" class="text-amber-700">yok — satılamaz</span>
            <span v-else>{{ urun.variant_count }}</span>
          </td>
          <td class="p-3 text-sm">{{ urun.stock }}</td>
          <td class="p-3 text-sm">{{ para(urun.min_price) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="urunler.links.length > 3" class="mt-4 flex gap-1 text-sm">
      <Link
        v-for="bag in urunler.links"
        :key="bag.label"
        :href="bag.url ?? ''"
        class="rounded border border-stone-300 px-3 py-1 bg-white"
        :class="{ 'bg-orange-600 text-white border-orange-600': bag.active, 'opacity-40 pointer-events-none': !bag.url }"
        v-html="bag.label"
      />
    </div>
  </PanelDuzeni>
</template>
