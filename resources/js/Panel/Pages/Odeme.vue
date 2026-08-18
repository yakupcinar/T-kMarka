<script setup>
/*
 | Ödeme sağlayıcısı ayarları. (4.5B)
 |
 | ⚠️ Mevcut anahtar DEĞERLERİ GÖSTERİLMİYOR — yalnızca "girilmiş mi".
 | Anahtarlar şifreli saklanıyor (1E.1) ve ekranda gösterilseydi
 | veritabanı yedeğini gören biri gibi, panele giren herkes de sağlayıcı
 | anahtarlarını okuyabilirdi.
 */
import { reactive, computed } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const props = defineProps({ odeme: Object })

const anahtarlar = reactive({})
Object.keys(props.odeme.keys).forEach((k) => { anahtarlar[k] = '' })

const form = useForm({ provider: props.odeme.provider, keys: anahtarlar })

const saglayiciAdi = { fake: 'Sahte sağlayıcı (test)', iyzico: 'iyzico' }
const anahtarAdi = {
  api_key: 'API anahtarı', secret_key: 'Gizli anahtar',
  base_url: 'Servis adresi', webhook_secret: 'Bildirim imza anahtarı',
}

const hazir = computed(() => props.odeme.ready)

function kaydet() { form.post('/yonetim/odeme-ayarlari') }
</script>

<template>
  <Head title="Ödeme" />

  <PanelDuzeni>
    <div class="flex items-center gap-3 mb-6">
      <h1 class="text-2xl font-bold">Ödeme</h1>
      <span class="rounded-full px-3 py-1 text-xs" :class="hazir ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
        {{ hazir ? 'Tahsilata hazır' : 'Eksik ayar var' }}
      </span>
    </div>

    <!-- ⚠️ Sahte sağlayıcı canlıda para tahsil ETMEZ. Bunu yazmazsak
         marka test sağlayıcısıyla satışa çıkıp parasını alamaz. -->
    <p v-if="form.provider === 'fake'" class="mb-4 rounded-lg bg-amber-100 border border-amber-300 px-4 py-3 text-sm">
      Şu an <strong>sahte sağlayıcı</strong> seçili. Bu sağlayıcı gerçek para tahsil etmez; yalnızca test içindir.
    </p>

    <form class="max-w-xl rounded-xl bg-white border border-stone-200 p-5" @submit.prevent="kaydet">
      <label class="block text-sm mb-4">
        Sağlayıcı
        <select v-model="form.provider" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
          <option v-for="s in odeme.available" :key="s" :value="s">{{ saglayiciAdi[s] ?? s }}</option>
        </select>
      </label>

      <h2 class="font-semibold text-sm mb-2">Anahtarlar</h2>

      <label v-for="(girilmis, anahtar) in odeme.keys" :key="anahtar" class="block text-sm mb-3">
        {{ anahtarAdi[anahtar] ?? anahtar }}
        <!-- ⚠️ Değer gösterilmiyor, DURUMU gösteriliyor. -->
        <span class="text-xs" :class="girilmis ? 'text-green-700' : 'text-amber-700'">
          {{ girilmis ? '· girilmiş' : '· eksik' }}
        </span>
        <input
          v-model="form.keys[anahtar]"
          type="password"
          autocomplete="off"
          :placeholder="girilmis ? 'değiştirmek için yeni değeri yazın' : 'zorunlu'"
          class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"
        >
      </label>

      <!-- ⚠️ Boş bırakılan alan SİLMİYOR, değiştirmiyor. Yazmasaydık
           marka yalnızca sağlayıcıyı değiştirdiğinde anahtarlarını
           farkında olmadan silerdi. -->
      <p class="text-xs text-stone-500 mb-4">
        Boş bıraktığınız alanlar değişmez. Mevcut değerler güvenlik gereği gösterilmez.
      </p>

      <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 font-semibold disabled:opacity-60" :disabled="form.processing">
        Kaydet
      </button>
    </form>
  </PanelDuzeni>
</template>
