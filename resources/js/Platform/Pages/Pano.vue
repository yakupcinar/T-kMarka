<script setup>
import { Head, Link } from '@inertiajs/vue3'
import YonetimDuzeni from '../Layouts/YonetimDuzeni.vue'

defineProps({ sayimlar: Object, toplam: Number })

const durumAdi = {
  provisioning: 'Kuruluyor', trial: 'Deneme', active: 'Aktif',
  past_due: 'Ödeme gecikti', suspended: 'Askıda', closed: 'Kapalı',
}
</script>

<template>
  <Head title="Pano" />

  <YonetimDuzeni>
    <h1 class="text-2xl font-bold mb-6">Pano</h1>

    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="rounded-xl bg-white border border-slate-200 p-5">
        <div class="text-3xl font-bold">{{ toplam }}</div>
        <div class="text-sm text-slate-600">toplam marka</div>
      </div>

      <!-- ⚠️ Yalnızca GERÇEKTEN VAR OLAN durumlar gösteriliyor. Bütün
           durumlar sıfırla listelenseydi pano, hiç yaşanmamış durumlarla
           dolar ve gerçek sayılar kaybolurdu. -->
      <div v-for="(adet, durum) in sayimlar" :key="durum" class="rounded-xl bg-white border border-slate-200 p-5">
        <div class="text-3xl font-bold">{{ adet }}</div>
        <div class="text-sm text-slate-600">{{ durumAdi[durum] ?? durum }}</div>
      </div>
    </div>

    <Link href="/yonetim/markalar" class="text-sm text-slate-700 hover:underline">Markaları görüntüle →</Link>
  </YonetimDuzeni>
</template>
