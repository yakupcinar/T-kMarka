<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const sayfa = usePage()
const kullanici = computed(() => sayfa.props.auth?.user ?? null)
const izinler = computed(() => sayfa.props.auth?.permissions ?? [])
</script>

<template>
  <Head title="Pano" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-2">Hoş geldiniz{{ kullanici ? ', ' + kullanici.name : '' }}</h1>

    <!-- ⚠️ 4C İSKELET: sayaç ve özetler kendi bloklarında gelecek
         (4D katalog, 4E sipariş). Sahte veri gösterilmiyor — çalışıyor
         gibi görünen boş bir pano, eksik olduğu belli olandan kötüdür. -->
    <p class="text-stone-600 mb-6">
      Panel iskeleti ayakta. Ürün ve sipariş ekranları sıradaki bloklarda geliyor.
    </p>

    <div class="rounded-xl bg-white border border-stone-200 p-5">
      <h2 class="font-semibold mb-2">Yetkileriniz</h2>
      <p v-if="kullanici?.is_owner" class="text-sm text-stone-600 mb-2">
        Mağaza sahibisiniz — bütün yetkilere sahipsiniz.
      </p>
      <ul class="text-sm text-stone-600 grid grid-cols-2 gap-x-6">
        <li v-for="izin in izinler" :key="izin"><code>{{ izin }}</code></li>
      </ul>
    </div>
  </PanelDuzeni>
</template>
