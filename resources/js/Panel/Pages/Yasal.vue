<script setup>
/*
 | Yasal metinler. (4.5B)
 |
 | ⚠️ TASLAK ve YAYIN AYRI (1A.4): düzenlemek yayınlamak değil.
 | `legal_document_versions` salt-ekleme — yayınlamak yeni satır demek ve
 | eski sürüm silinmiyor, çünkü siparişler o sürüme bağlı (1D-K2).
 */
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const props = defineProps({ belgeler: Array })

const taslaklar = reactive({})
props.belgeler.forEach((b) => { taslaklar[b.tur] = b.taslak ?? '' })

function kaydet(tur) { router.post(`/yonetim/yasal/${tur}`, { icerik: taslaklar[tur] }) }
function yayinla(tur) { router.post(`/yonetim/yasal/${tur}/yayinla`) }
</script>

<template>
  <Head title="Yasal metinler" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-2">Yasal metinler</h1>
    <p class="text-sm text-stone-600 mb-6">
      Metinler yayınlanmadan mağazanız satışa açılamaz. Yayınlanan her sürüm saklanır —
      siparişler onayladıkları sürüme bağlıdır.
    </p>

    <div v-for="b in belgeler" :key="b.tur" class="rounded-xl bg-white border border-stone-200 p-5 mb-5">
      <div class="flex items-center gap-3 mb-3">
        <h2 class="font-semibold">{{ b.ad }}</h2>

        <span v-if="b.yayin_surumu" class="text-xs text-stone-600">
          yayında: sürüm {{ b.yayin_surumu }} · {{ b.yayin_tarihi }}
        </span>
        <span v-else class="text-xs text-amber-700">henüz yayınlanmadı</span>

        <!-- ⚠️ "Yayınlanmamış değişiklik" AYRI bir uyarı: marka
             değişikliğini yayınladığını sanmasın. -->
        <span v-if="b.yayinlanmamis_degisiklik" class="ml-auto text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-0.5">
          yayınlanmamış değişiklik var
        </span>
      </div>

      <textarea
        v-model="taslaklar[b.tur]"
        rows="10"
        class="w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm"
        placeholder="Metni buraya yazın"
      />

      <div class="flex gap-2 mt-3">
        <button type="button" class="rounded-lg border border-stone-300 px-4 py-2 text-sm" @click="kaydet(b.tur)">
          Taslağı kaydet
        </button>
        <button type="button" class="rounded-lg bg-orange-600 text-white px-4 py-2 text-sm font-semibold" @click="yayinla(b.tur)">
          Yayınla
        </button>
        <a v-if="b.yayin_surumu" :href="`/yasal/${b.tur}`" target="_blank" rel="noopener"
           class="ml-auto text-sm text-stone-600 underline self-center">
          vitrinde gör
        </a>
      </div>
    </div>
  </PanelDuzeni>
</template>
