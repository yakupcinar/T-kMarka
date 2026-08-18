<script setup>
import { useForm, Head } from '@inertiajs/vue3'

const form = useForm({ email: '', password: '' })

function gonder() {
  form.post('/yonetim/giris', { onFinish: () => form.reset('password') })
}
</script>

<template>
  <Head title="Giriş" />

  <div class="min-h-screen grid place-items-center bg-slate-900 text-slate-900">
    <form class="w-full max-w-sm bg-white rounded-xl p-6" @submit.prevent="gonder">
      <h1 class="text-xl font-bold mb-1">TıkMarka Yönetim</h1>
      <p class="text-sm text-slate-500 mb-5">Kontrol düzlemine giriş.</p>

      <label class="block text-sm mb-3">E-posta
        <input v-model="form.email" type="email" autocomplete="username" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
      </label>

      <label class="block text-sm mb-4">Parola
        <input v-model="form.password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
      </label>

      <!-- ⚠️ Tek mesaj: hesap yok · parola yanlış · hesap kapalı. Ayrılsaydı
           deneyerek hangi e-postaların yönetici olduğu öğrenilirdi. -->
      <p v-if="form.errors.email" class="mb-3 text-sm text-red-700">{{ form.errors.email }}</p>

      <button type="submit" class="w-full rounded-lg bg-slate-900 text-white py-2 font-semibold disabled:opacity-60" :disabled="form.processing">
        {{ form.processing ? 'Giriş yapılıyor…' : 'Giriş yap' }}
      </button>
    </form>
  </div>
</template>
