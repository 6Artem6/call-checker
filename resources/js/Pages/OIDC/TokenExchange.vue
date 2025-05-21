<script setup>
import { onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const { access_token, refresh_token, expires_in } = usePage().props

onMounted(async () => {
  // Сохраняем refresh_token и время окончания
  localStorage.setItem('refresh_token', refresh_token)
  localStorage.setItem('token_expires_at', Date.now() + expires_in * 1000)

  // Устанавливаем access_token через бекенд (HttpOnly cookie)
  await fetch(route('auth-set-token'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ access_token }),
    credentials: 'include'
  })

  // Переход в личный кабинет
  router.visit('/panel')
})
</script>

<template>
  <GuestLayout>
    <Head title="Авторизация" />
    <div>Авторизация…</div>
  </GuestLayout>
</template>
