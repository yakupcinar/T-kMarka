<script setup>
/*
 | Personel ve roller. (4.5C)
 |
 | ⚠️ `izin:staff.manage` arkasında ve o izin SİSTEMDEKİ EN TEHLİKELİSİ:
 | yetki dağıtma yetkisi.
 */
import { ref, reactive } from 'vue'
import { useForm, Head, router } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const props = defineProps({ personel: Array, roller: Array, izinler: Array })

const yeni = useForm({ name: '', email: '', password: '', roles: [] })
const rolForm = useForm({ name: '', permissions: [] })
const duzenlenen = ref(null)

function personelEkle() { yeni.post('/yonetim/personel', { onSuccess: () => yeni.reset() }) }

function personelCikar(k) {
  if (confirm(`${k.name} çıkarılsın mı?`)) router.delete(`/yonetim/personel/${k.uuid}`)
}

function rolKaydet() {
  if (duzenlenen.value) {
    rolForm.put(`/yonetim/roller/${duzenlenen.value}`, { onSuccess: rolTemizle })
  } else {
    rolForm.post('/yonetim/roller', { onSuccess: rolTemizle })
  }
}

function rolTemizle() { rolForm.reset(); duzenlenen.value = null }

function rolDuzenle(r) {
  duzenlenen.value = r.id
  rolForm.name = r.name
  rolForm.permissions = [...r.permissions]
}

function rolSil(r) {
  if (confirm(`"${r.name}" rolü silinsin mi?`)) router.delete(`/yonetim/roller/${r.id}`)
}
</script>

<template>
  <Head title="Personel" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-6">Personel ve roller</h1>

    <div class="grid grid-cols-2 gap-6">
      <div class="rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-3">Personel</h2>

        <table class="w-full text-sm mb-5">
          <tr v-for="k in personel" :key="k.uuid" class="border-b border-stone-100">
            <td class="py-2">
              {{ k.name }}
              <!-- ⚠️ SAHİP çıkarılamıyor (1A.3); sebebi görünür olmalı,
                   yoksa "neden silemiyorum" sorusu doğar. -->
              <span v-if="k.is_owner" class="text-xs rounded-full bg-stone-200 px-2 py-0.5">sahip</span>
              <div class="text-xs text-stone-500">{{ k.email }}</div>
            </td>
            <td class="py-2 text-xs text-stone-600">{{ k.roles.join(', ') || '—' }}</td>
            <td class="py-2 text-right">
              <button v-if="!k.is_owner" type="button" class="text-red-700 text-sm" @click="personelCikar(k)">çıkar</button>
            </td>
          </tr>
        </table>

        <form class="border-t border-stone-200 pt-4" @submit.prevent="personelEkle">
          <h3 class="text-sm font-semibold mb-2">Personel ekle</h3>

          <input v-model="yeni.name" placeholder="Ad soyad" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mb-2">
          <input v-model="yeni.email" type="email" placeholder="E-posta" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mb-2">
          <input v-model="yeni.password" type="password" placeholder="Parola (en az 8 karakter)" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mb-2">

          <!-- ⚠️ Roller İSİMLE gönderiliyor (1A.6): id gönderilseydi iç
               kimlikler sızardı ve okunmaz olurdu. -->
          <div class="mb-3">
            <label v-for="r in roller" :key="r.id" class="inline-flex items-center gap-1 mr-3 text-sm">
              <input v-model="yeni.roles" type="checkbox" :value="r.name"> {{ r.name }}
            </label>
          </div>

          <p v-for="(h, alan) in yeni.errors" :key="alan" class="text-sm text-red-700 mb-1">{{ h }}</p>

          <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold" :disabled="yeni.processing">
            Ekle
          </button>
        </form>
      </div>

      <div class="rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-3">Roller</h2>

        <table class="w-full text-sm mb-5">
          <tr v-for="r in roller" :key="r.id" class="border-b border-stone-100">
            <td class="py-2">
              {{ r.name }}
              <!-- ⚠️ SİSTEM ROLÜ silinemiyor / adı değişmiyor (1A.6). -->
              <span v-if="r.is_system" class="text-xs rounded-full bg-stone-200 px-2 py-0.5">sistem</span>
              <div class="text-xs text-stone-500">{{ r.permissions.length }} izin · {{ r.staff_count }} personel</div>
            </td>
            <td class="py-2 text-right whitespace-nowrap">
              <button type="button" class="text-sm text-stone-600 mr-2" @click="rolDuzenle(r)">düzenle</button>
              <!-- ⚠️ Kullanımdaki rol silinemiyor: silinseydi o roldeki
                   personel SESSİZCE yetkisiz kalırdı. -->
              <button v-if="!r.is_system && r.staff_count === 0" type="button" class="text-sm text-red-700" @click="rolSil(r)">sil</button>
            </td>
          </tr>
        </table>

        <form class="border-t border-stone-200 pt-4" @submit.prevent="rolKaydet">
          <h3 class="text-sm font-semibold mb-2">{{ duzenlenen ? 'Rolü düzenle' : 'Rol ekle' }}</h3>

          <input v-model="rolForm.name" placeholder="Rol adı" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm mb-2">

          <div class="mb-3 grid grid-cols-2 gap-1">
            <label v-for="i in izinler" :key="i.value" class="inline-flex items-center gap-1 text-sm">
              <input v-model="rolForm.permissions" type="checkbox" :value="i.value"> {{ i.label }}
            </label>
          </div>

          <p v-for="(h, alan) in rolForm.errors" :key="alan" class="text-sm text-red-700 mb-1">{{ h }}</p>

          <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold" :disabled="rolForm.processing">
            {{ duzenlenen ? 'Kaydet' : 'Ekle' }}
          </button>
          <button v-if="duzenlenen" type="button" class="ml-2 text-sm text-stone-600" @click="rolTemizle">vazgeç</button>
        </form>
      </div>
    </div>
  </PanelDuzeni>
</template>
