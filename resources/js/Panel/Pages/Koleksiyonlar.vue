<script setup>
/*
 | Koleksiyonlar. (4.5E — 2D'nin ekranı)
 |
 | ⚠️ İKİ TÜR ve farkları ekranda görünür:
 |   elle seçilen → ürünleri marka tek tek ekliyor
 |   kurallı      → üyeler SORGU ANINDA hesaplanıyor, liste kendiliğinden
 |                  güncelleniyor
 */
import { useForm, Head, Link, router } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const props = defineProps({
  koleksiyonlar: Array, turler: Array,
  kuralAlanlari: Array, eslesmeler: Array,
})

/*
 | ⚠️ `rules` FORMDA: kurallı koleksiyon KURAL OLMADAN oluşturulamıyor
 | (2D — boş kural tüm kataloğu gösterirdi). "Önce oluştur, sonra kuralını
 | yaz" akışı yazılmıştı ve HİÇ ÇALIŞMIYORDU.
 */
const form = useForm({
  title: '', type: 'manual', is_active: true,
  rules: { match: 'all', conditions: [] },
})

function kosulEkle() {
  const ilk = props.kuralAlanlari[0]
  form.rules.conditions.push({ field: ilk.alan, op: ilk.islecler[0], value: '' })
}

function kosulSil(i) { form.rules.conditions.splice(i, 1) }

/*
 | ⚠️ Alan değişince İŞLEÇ sıfırlanıyor: her alan farklı işleç destekliyor
 | ve eskisi kalırsa sunucu "desteklemiyor" diye reddeder.
 */
function alanDegisti(k) {
  const alan = props.kuralAlanlari.find((a) => a.alan === k.field)
  k.op = alan?.islecler[0] ?? ''
}

/*
 | ⚠️ Tür MANUEL'e dönerse koşullar temizleniyor: kalsaydı sunucuya
 | anlamsız veri gider ve marka "kural yazdım ama işlemiyor" derdi.
 */
function turDegisti() {
  if (form.type === 'manual') form.rules.conditions = []
  else if (form.rules.conditions.length === 0) kosulEkle()
}

function ekle() { form.post('/yonetim/koleksiyonlar', { onSuccess: () => form.reset() }) }
function sil(k) {
  if (confirm(`"${k.title}" silinsin mi?`)) router.delete(`/yonetim/koleksiyonlar/${k.uuid}`)
}

const turAdi = { manual: 'Elle seçilen', rule: 'Kurallı (otomatik)' }
</script>

<template>
  <Head title="Koleksiyonlar" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-6">Koleksiyonlar</h1>

    <div v-if="koleksiyonlar.length === 0" class="rounded-xl bg-white border border-stone-200 p-10 text-center text-stone-600 mb-6">
      Henüz koleksiyon yok.
    </div>

    <table v-else class="w-full bg-white rounded-xl border border-stone-200 overflow-hidden mb-6">
      <thead class="bg-stone-50 text-left text-sm text-stone-600">
        <tr><th class="p-3">Koleksiyon</th><th class="p-3">Tür</th><th class="p-3">Ürün</th><th class="p-3">Durum</th><th /></tr>
      </thead>
      <tbody>
        <tr v-for="k in koleksiyonlar" :key="k.uuid" class="border-t border-stone-100">
          <td class="p-3">
            <Link :href="`/yonetim/koleksiyonlar/${k.uuid}`" class="font-medium hover:text-orange-600">{{ k.title }}</Link>
          </td>
          <td class="p-3 text-sm">{{ turAdi[k.type] ?? k.type }}</td>
          <!-- ⚠️ Kurallıda bu sayı SORGUDAN geliyor: tabloya bakılsaydı
               hep 0 görünürdü. -->
          <td class="p-3 text-sm">{{ k.urun_sayisi }}</td>
          <td class="p-3 text-sm">{{ k.is_active ? 'Yayında' : 'Kapalı' }}</td>
          <td class="p-3 text-right">
            <button type="button" class="text-red-700 text-sm" @click="sil(k)">sil</button>
          </td>
        </tr>
      </tbody>
    </table>

    <form class="rounded-xl bg-white border border-stone-200 p-5 max-w-lg" @submit.prevent="ekle">
      <h2 class="font-semibold text-sm mb-3">Koleksiyon ekle</h2>

      <input v-model="form.title" placeholder="Başlık" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mb-2">

      <select v-model="form.type" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mb-2" @change="turDegisti">
        <option v-for="t in turler" :key="t.deger" :value="t.deger">{{ t.ad }}</option>
      </select>

      <!-- ⚠️ KURAL DÜZENLEYİCİ OLUŞTURMA FORMUNDA: kurallı koleksiyon
           kuralsız oluşturulamıyor (2D). Ayrıntı sayfasına bırakmak
           "hiç oluşturulamaz" demekti. -->
      <div v-if="form.type === 'rule'" class="rounded-lg bg-stone-50 border border-stone-200 p-3 mb-3">
        <p class="text-xs text-stone-600 mb-2">
          Üyeler otomatik hesaplanır. <strong>En az bir koşul</strong> gerekli.
        </p>

        <label class="block text-sm mb-2">
          Koşullardan
          <select v-model="form.rules.match" class="ml-1 rounded-lg border border-stone-300 px-2 py-1">
            <option v-for="e in eslesmeler" :key="e" :value="e">{{ e === 'all' ? 'hepsi' : 'herhangi biri' }}</option>
          </select>
          sağlanmalı
        </label>

        <div v-for="(k, i) in form.rules.conditions" :key="i" class="flex gap-2 mb-2 items-center">
          <select v-model="k.field" class="rounded-lg border border-stone-300 px-2 py-1 text-sm" @change="alanDegisti(k)">
            <option v-for="a in kuralAlanlari" :key="a.alan" :value="a.alan">{{ a.alan }}</option>
          </select>

          <select v-model="k.op" class="rounded-lg border border-stone-300 px-2 py-1 text-sm">
            <option v-for="o in (kuralAlanlari.find((a) => a.alan === k.field)?.islecler ?? [])" :key="o" :value="o">{{ o }}</option>
          </select>

          <input v-model="k.value" placeholder="değer" class="flex-1 rounded-lg border border-stone-300 px-2 py-1 text-sm">
          <button type="button" class="text-red-700 text-sm" @click="kosulSil(i)">sil</button>
        </div>

        <button type="button" class="rounded-lg border border-stone-300 px-3 py-1 text-sm" @click="kosulEkle">Koşul ekle</button>
      </div>

      <p v-for="(h, alan) in form.errors" :key="alan" class="text-sm text-red-700 mb-1">{{ h }}</p>

      <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold">Ekle</button>
    </form>
  </PanelDuzeni>
</template>
