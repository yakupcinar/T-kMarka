<script setup>
/*
 | Kontrol düzlemi düzeni. (4F)
 |
 | ⚠️ Marka panelinden GÖRSEL OLARAK DA farklı (koyu üst bar): iki panel
 | birbirine benzeseydi, iki sekme açık çalışan biri hangi markanın
 | verisine baktığını karıştırabilirdi.
 */
import { computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const sayfa = usePage()
const kullanici = computed(() => sayfa.props.auth?.user ?? null)
const bildirim = computed(() => sayfa.props.bildirim ?? {})

function cikisYap() { router.post('/yonetim/cikis') }
</script>

<template>
  <div class="min-h-screen bg-slate-100 text-slate-900">
    <header class="bg-slate-900 text-white">
      <div class="mx-auto max-w-6xl px-5 py-3 flex items-center gap-6">
        <Link href="/yonetim" class="font-black tracking-tight">TıkMarka <span class="text-slate-400">Yönetim</span></Link>

        <nav class="flex gap-4 text-sm">
          <Link href="/yonetim" class="hover:text-slate-300">Pano</Link>
          <Link href="/yonetim/markalar" class="hover:text-slate-300">Markalar</Link>
        </nav>

        <div class="ml-auto flex items-center gap-3 text-sm">
          <span v-if="kullanici" class="text-slate-300">{{ kullanici.name }}</span>
          <button type="button" class="rounded-lg border border-slate-600 px-3 py-1 hover:bg-slate-800" @click="cikisYap">Çıkış</button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-5 py-8">
      <p v-if="bildirim.mesaj" class="mb-4 rounded-lg bg-green-100 border border-green-300 px-4 py-3">{{ bildirim.mesaj }}</p>
      <p v-if="bildirim.hata" class="mb-4 rounded-lg bg-red-100 border border-red-300 px-4 py-3">{{ bildirim.hata }}</p>
      <slot />
    </main>
  </div>
</template>
