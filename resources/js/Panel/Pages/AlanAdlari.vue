<script setup>
/*
 | Özel alan adı. (4.5C — 3H'nin karşılığı)
 |
 | ⚠️ DNS kaydını MARKA ekliyor, biz kontrol ediyoruz. O adım insan işi ve
 | destek yükünün tamamı orada; bu yüzden talimat açıkça ve ÜÇ SEÇENEKLE
 | duruyor — marka sağlayıcısının izin verdiğini kullanabilsin.
 */
import { useForm, Head, router } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

defineProps({ alanAdlari: Array, sonAlanAdi: Boolean })

const form = useForm({ domain: '' })

function ekle() { form.post('/yonetim/alan-adlari', { onSuccess: () => form.reset() }) }
function dogrula(d) { router.post(`/yonetim/alan-adlari/${d}/dogrula`) }
function sil(d) {
  if (confirm(`${d} silinsin mi?`)) router.delete(`/yonetim/alan-adlari/${d}`)
}
</script>

<template>
  <Head title="Alan adları" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-2">Alan adları</h1>
    <p class="text-sm text-stone-600 mb-6">
      Kendi alan adınızı bağlayabilirsiniz. DNS kaydını siz eklersiniz, biz kontrol ederiz.
      Doğrulandıktan sonra güvenlik sertifikası ilk ziyarette otomatik alınır.
    </p>

    <div v-for="d in alanAdlari" :key="d.domain" class="rounded-xl bg-white border border-stone-200 p-5 mb-4">
      <div class="flex items-center gap-3">
        <strong>{{ d.domain }}</strong>

        <span class="rounded-full px-2 py-0.5 text-xs" :class="d.dogrulandi ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
          {{ d.dogrulandi ? `doğrulandı · ${d.dogrulama_tarihi}` : 'doğrulanmadı' }}
        </span>

        <span class="ml-auto flex gap-2">
          <button v-if="!d.dogrulandi" type="button" class="rounded-lg border border-stone-300 px-3 py-1 text-sm" @click="dogrula(d.domain)">
            Kontrol et
          </button>
          <!-- ⚠️ SON alan adı silinemez: silinseydi markaya hiçbir
               adresten ulaşılamaz, paneline de girilemezdi. -->
          <button v-if="!sonAlanAdi" type="button" class="rounded-lg border border-red-300 text-red-700 px-3 py-1 text-sm" @click="sil(d.domain)">
            Sil
          </button>
        </span>
      </div>

      <!-- ⚠️ Talimat doğrulanmamış HER kayıtta duruyor, yalnızca yeni
           eklenende değil: marka sayfayı kapatıp dönünce ne yapacağını
           yeniden görebilmeli. -->
      <div v-if="d.talimat" class="mt-4 border-t border-stone-200 pt-4">
        <p class="text-sm mb-2">
          Aşağıdaki kayıtlardan <strong>birini</strong> DNS panelinize ekleyin, sonra “Kontrol et” deyin.
        </p>

        <table class="w-full text-sm">
          <tr v-for="(k, tur) in d.talimat" :key="tur" class="border-b border-stone-100">
            <td class="py-2 font-mono">{{ k.type }}</td>
            <td class="py-2 font-mono text-stone-600">{{ k.name }}</td>
            <td class="py-2 font-mono break-all">{{ k.value }}</td>
          </tr>
        </table>

        <p class="text-xs text-stone-500 mt-2">
          DNS değişikliğinin yayılması birkaç saat sürebilir.
        </p>
      </div>
    </div>

    <form class="rounded-xl bg-white border border-stone-200 p-5 max-w-lg" @submit.prevent="ekle">
      <h2 class="font-semibold text-sm mb-2">Alan adı ekle</h2>

      <div class="flex gap-2">
        <input v-model="form.domain" placeholder="magazam.com" class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
        <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold" :disabled="form.processing">
          Ekle
        </button>
      </div>

      <p v-if="form.errors.domain" class="text-sm text-red-700 mt-2">{{ form.errors.domain }}</p>
    </form>
  </PanelDuzeni>
</template>
