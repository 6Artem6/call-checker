<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const page = usePage()
const message = ref(null)
const type = ref('success')
let timer = null

// следим за обновлением flash в props
watch(
    () => page.props.flash,
    (flash) => {
      if (flash?.success) {
        type.value = 'success'
        message.value = flash.success
      } else if (flash?.error) {
        type.value = 'error'
        message.value = flash.error
      }

      if (message.value) {
        clearTimeout(timer)
        timer = setTimeout(() => (message.value = null), 4000)
      }
    },
    { immediate: true }
)
</script>

<template>
  <div class="flex min-h-screen flex-col bg-gray-100 pt-6 dark:bg-gray-900">
    <nav class="mb-4 flex justify-center gap-6 bg-white py-4 shadow dark:bg-gray-800">
      <Link :href="route('payment-create')" class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200">
        Новая оплата
      </Link>
      <Link :href="route('invoices-index')" class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200">
        Созданные счета
      </Link>
      <Link :href="route('balance')" class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200">
        Баланс
      </Link>
      <Link :href="route('panel-index')" class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200">
        Панель управления
      </Link>
      <Link :href="route('playground-chat-form')" class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200">
        Тест ботов
      </Link>
      <Link :href="route('panel-search')" class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200">
        Поиск сообщений
      </Link>
      <Link :href="route('panel-sync-view')" class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200">
        Синхронизация
      </Link>
      <Link :href="route('auth-logout')" method="post" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400">
        Выход
      </Link>
    </nav>

    <div class="flex flex-1 items-start justify-center px-4">
      <div class="mt-6 w-full max-w-4xl overflow-hidden bg-white px-6 py-4 shadow-md sm:rounded-lg dark:bg-gray-800">
        <slot />
      </div>
    </div>

    <!-- Toast -->
    <transition name="fade">
      <div
          v-if="message"
          class="fixed bottom-6 right-6 px-4 py-2 rounded-lg shadow-lg text-white"
          :class="type === 'success' ? 'bg-green-600' : 'bg-red-600'"
      >
        {{ message }}
      </div>
    </transition>
  </div>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
