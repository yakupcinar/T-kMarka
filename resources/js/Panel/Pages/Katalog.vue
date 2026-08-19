<script setup>
/*
 | Kategoriler ve varyant eksenleri. (4.5E)
 |
 | ⚠️ İkisi tek ekranda: ikisi de ürün eklemeden ÖNCE yapılan hazırlık işi.
 */
import { ref } from 'vue'
import { useForm, Head, router } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

defineProps({ kategoriler: Array, eksenler: Array })

const kategori = useForm({ name: '', parent_uuid: '' })
const eksen = useForm({ name: '' })
const acikEksen = ref(null)
const deger = useForm({ value: '', swatch: '' })

function kategoriEkle() { kategori.post('/yonetim/katalog/kategoriler', { onSuccess: () => kategori.reset() }) }
function kategoriSil(k) {
  if (confirm(`"${k.name}" silinsin mi?`)) router.delete(`/yonetim/katalog/kategoriler/${k.uuid}`)
}
function eksenEkle() { eksen.post('/yonetim/katalog/eksenler', { onSuccess: () => eksen.reset() }) }
function eksenSil(e) {
  if (confirm(`"${e.name}" ekseni silinsin mi?`)) router.delete(`/yonetim/katalog/eksenler/${e.uuid}`)
}
function degerEkle(e) {
  deger.post(`/yonetim/katalog/eksenler/${e.uuid}/degerler`, { onSuccess: () => deger.reset() })
}
function degerSil(e, d) { router.delete(`/yonetim/katalog/eksenler/${e.uuid}/degerler/${d.uuid}`) }
</script>

<template>
  <Head title="Katalog ayarları" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-6">Katalog ayarları</h1>

    <div class="grid grid-cols-2 gap-6">
      <div class="rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-1">Kategoriler</h2>
        <p class="text-xs text-stone-500 mb-3">Ürünler tek bir kategoriye bağlanır.</p>

        <table class="w-full text-sm mb-4">
          <tr v-for="k in kategoriler" :key="k.uuid" class="border-b border-stone-100">
            <!-- ⚠️ Girinti DERİNLİKTEN çiziliyor: `ltree` yolu zaten sıralı
                 geliyor, ağacı istemcide kurmaya gerek yok. -->
            <td class="py-2" :style="{ paddingLeft: (k.derinlik * 18) + 'px' }">
              {{ k.name }}
              <span class="text-xs text-stone-500">{{ k.urun_sayisi }} ürün</span>
            </td>
            <td class="py-2 text-right">
              <!-- ⚠️ Ürünü olan kategori silinemiyor; düğme gizlenmiyor,
                   sunucu sebebini yazıyor. -->
              <button type="button" class="text-red-700 text-sm" @click="kategoriSil(k)">sil</button>
            </td>
          </tr>
        </table>

        <form class="border-t border-stone-200 pt-4" @submit.prevent="kategoriEkle">
          <input v-model="kategori.name" placeholder="Kategori adı" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mb-2">

          <select v-model="kategori.parent_uuid" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mb-2">
            <option value="">— üst kategori yok —</option>
            <option v-for="k in kategoriler" :key="k.uuid" :value="k.uuid">{{ k.name }}</option>
          </select>

          <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold">Ekle</button>
        </form>
      </div>

      <div class="rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-1">Varyant eksenleri</h2>
        <!-- ⚠️ Eksen olmadan çok varyantlı ürün kurulamıyor; ekran bunu
             söylüyor, yoksa marka "beden nasıl eklenir" diye arar. -->
        <p class="text-xs text-stone-500 mb-3">Beden, renk gibi eksenler. Ürün varyantları bunlardan kurulur.</p>

        <div v-for="e in eksenler" :key="e.uuid" class="border-b border-stone-100 py-2">
          <div class="flex items-center gap-2 text-sm">
            <strong>{{ e.name }}</strong>
            <span class="text-xs text-stone-500">{{ e.degerler.length }} değer</span>
            <span class="ml-auto flex gap-2">
              <button type="button" class="text-sm text-stone-600" @click="acikEksen = acikEksen === e.uuid ? null : e.uuid">
                değerler
              </button>
              <button type="button" class="text-sm text-red-700" @click="eksenSil(e)">sil</button>
            </span>
          </div>

          <div v-if="acikEksen === e.uuid" class="mt-2 pl-3">
            <div v-for="d in e.degerler" :key="d.uuid" class="flex items-center gap-2 text-sm py-1">
              <span v-if="d.swatch" class="inline-block w-4 h-4 rounded-full border border-stone-300" :style="{ background: d.swatch }" />
              <span>{{ d.value }}</span>
              <button type="button" class="ml-auto text-red-700 text-xs" @click="degerSil(e, d)">sil</button>
            </div>

            <div class="flex gap-2 mt-2">
              <input v-model="deger.value" placeholder="Değer" class="flex-1 rounded-lg border border-stone-300 px-2 py-1 text-sm">
              <!-- ⚠️ Renk kutusu yalnızca #rrggbb: serbest metin CSS'e girerdi. -->
              <input v-model="deger.swatch" placeholder="#rrggbb" class="w-28 rounded-lg border border-stone-300 px-2 py-1 text-sm">
              <button type="button" class="rounded-lg border border-stone-300 px-3 text-sm" @click="degerEkle(e)">Ekle</button>
            </div>
          </div>
        </div>

        <form class="border-t border-stone-200 pt-4 mt-3 flex gap-2" @submit.prevent="eksenEkle">
          <input v-model="eksen.name" placeholder="Eksen adı (Beden, Renk…)" class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
          <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold">Ekle</button>
        </form>
      </div>
    </div>
  </PanelDuzeni>
</template>
