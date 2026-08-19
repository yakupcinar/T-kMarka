<script setup>
/* Koleksiyon ayrıntısı ve üyeleri. (4.5E) */
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const props = defineProps({
  koleksiyon: Object, uyeler: Array, eklenebilir: Array,
  kuralAlanlari: Array, eslesmeler: Array,
})

/* KURAL DÜZENLEYİCİ (4.5F) */
const kural = useForm({
  match: props.koleksiyon.rules?.match ?? 'all',
  conditions: props.koleksiyon.rules?.conditions
    ? JSON.parse(JSON.stringify(props.koleksiyon.rules.conditions))
    : [],
})

function kosulEkle() {
  const ilk = props.kuralAlanlari[0]
  kural.conditions.push({ field: ilk.alan, op: ilk.islecler[0], value: '' })
}

function kosulSil(i) { kural.conditions.splice(i, 1) }

/*
 | ⚠️ Alan değişince İŞLEÇ de sıfırlanıyor: her alan farklı işleç
 | destekliyor (2D) ve eskisi kalırsa sunucu "desteklemiyor" diye
 | reddeder — marka sebebini anlamaz.
 */
function alanDegisti(k) {
  const alan = props.kuralAlanlari.find((a) => a.alan === k.field)
  k.op = alan?.islecler[0] ?? ''
}

function kuralKaydet() { kural.post(`/yonetim/koleksiyonlar/${props.koleksiyon.uuid}/kural`) }

const secilen = ref('')

function urunEkle() {
  if (!secilen.value) return
  router.post(`/yonetim/koleksiyonlar/${props.koleksiyon.uuid}/urunler`, { product_uuid: secilen.value },
    { onSuccess: () => { secilen.value = '' } })
}

function urunCikar(u) {
  router.delete(`/yonetim/koleksiyonlar/${props.koleksiyon.uuid}/urunler/${u.uuid}`)
}
</script>

<template>
  <Head :title="koleksiyon.title" />

  <PanelDuzeni>
    <div class="flex items-center gap-3 mb-6">
      <Link href="/yonetim/koleksiyonlar" class="text-sm text-stone-600 hover:text-orange-600">← Koleksiyonlar</Link>
      <h1 class="text-2xl font-bold">{{ koleksiyon.title }}</h1>
      <span class="rounded-full bg-stone-200 px-2 py-0.5 text-xs">
        {{ koleksiyon.type === 'manual' ? 'Elle seçilen' : 'Kurallı' }}
      </span>
    </div>

    <!-- ⚠️ Kurallıda ELLE EKLEME YOK ve sebebi yazılı: üyelik sorguyla
         belirleniyor, elle eklenen ürün sonraki sorguda kaybolurdu. -->
    <div v-if="koleksiyon.type === 'rule'" class="rounded-lg bg-stone-100 border border-stone-200 px-4 py-3 text-sm mb-4">
      Bu koleksiyonun üyeleri kurala göre <strong>otomatik</strong> belirlenir; ürün elle eklenmez.
      Fiyat ya da etiket değişince liste kendiliğinden güncellenir.
    </div>

    <!-- KURAL DÜZENLEYİCİ — yalnızca kurallı koleksiyonda -->
    <div v-if="koleksiyon.type === 'rule'" class="rounded-xl bg-white border border-stone-200 p-5 mb-4">
      <h2 class="font-semibold mb-3">Kural</h2>

      <label class="block text-sm mb-3">
        Koşullardan
        <select v-model="kural.match" class="ml-2 rounded-lg border border-stone-300 px-2 py-1">
          <option v-for="e in eslesmeler" :key="e" :value="e">{{ e === 'all' ? 'hepsi' : 'herhangi biri' }}</option>
        </select>
        sağlanmalı
      </label>

      <div v-for="(k, i) in kural.conditions" :key="i" class="flex gap-2 mb-2 items-center">
        <select v-model="k.field" class="rounded-lg border border-stone-300 px-2 py-1 text-sm" @change="alanDegisti(k)">
          <option v-for="a in kuralAlanlari" :key="a.alan" :value="a.alan">{{ a.alan }}</option>
        </select>

        <!-- ⚠️ İşleç listesi ALANA göre değişiyor (2D): sabit liste
             gösterilseydi marka desteklenmeyen bir işleç seçebilirdi. -->
        <select v-model="k.op" class="rounded-lg border border-stone-300 px-2 py-1 text-sm">
          <option v-for="o in (kuralAlanlari.find((a) => a.alan === k.field)?.islecler ?? [])" :key="o" :value="o">{{ o }}</option>
        </select>

        <input v-model="k.value" placeholder="değer" class="flex-1 rounded-lg border border-stone-300 px-2 py-1 text-sm">
        <button type="button" class="text-red-700 text-sm" @click="kosulSil(i)">sil</button>
      </div>

      <div class="flex gap-2 mt-3">
        <button type="button" class="rounded-lg border border-stone-300 px-3 py-2 text-sm" @click="kosulEkle">Koşul ekle</button>
        <button type="button" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold" @click="kuralKaydet">
          Kuralı kaydet
        </button>
      </div>

      <p v-for="(h, alan) in kural.errors" :key="alan" class="text-sm text-red-700 mt-2">{{ h }}</p>
    </div>

    <div class="rounded-xl bg-white border border-stone-200 p-5">
      <h2 class="font-semibold mb-3">Ürünler ({{ uyeler.length }})</h2>

      <p v-if="uyeler.length === 0" class="text-sm text-stone-600">Bu koleksiyonda ürün yok.</p>

      <table v-else class="w-full text-sm mb-4">
        <tr v-for="u in uyeler" :key="u.uuid" class="border-b border-stone-100">
          <td class="py-2">{{ u.title }}</td>
          <td class="py-2 text-xs text-stone-500">{{ u.status }}</td>
          <td class="py-2 text-right">
            <button v-if="koleksiyon.type === 'manual'" type="button" class="text-red-700" @click="urunCikar(u)">çıkar</button>
          </td>
        </tr>
      </table>

      <div v-if="koleksiyon.type === 'manual'" class="border-t border-stone-200 pt-4 flex gap-2">
        <select v-model="secilen" class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
          <option value="">— ürün seçin —</option>
          <option v-for="u in eklenebilir" :key="u.uuid" :value="u.uuid">{{ u.title }}</option>
        </select>
        <button type="button" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold" @click="urunEkle">Ekle</button>
      </div>
    </div>
  </PanelDuzeni>
</template>
