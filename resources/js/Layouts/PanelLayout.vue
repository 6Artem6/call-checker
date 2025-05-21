<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="flex min-h-screen flex-col bg-gray-100 pt-6 dark:bg-gray-900">
    <nav class="mb-4 flex justify-center gap-6 bg-white py-4 shadow dark:bg-gray-800">
      <Link
          :href="route('payment-create')"
          class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200"
      >
        Новая оплата
      </Link>
      <Link
          :href="route('invoices-index')"
          class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200"
      >
        Созданные счета
      </Link>
      <Link
          :href="route('panel-index')"
          class="text-sm font-medium text-gray-700 hover:underline dark:text-gray-200"
      >
        Панель управления
      </Link>
      <Link
          :href="route('auth-logout')"
          method="post"
          class="text-sm font-medium text-red-600 hover:underline dark:text-red-400"
      >
        Выход
      </Link>
    </nav>

    <div class="flex flex-1 items-center justify-center">
      <div
          class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg dark:bg-gray-800"
      >
        <slot />
      </div>
    </div>
  </div>
</template>
