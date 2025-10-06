<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import {ref} from "vue";

const page = usePage();

const message = ref("");
message.value = page.props.message;
</script>

<template>
  <GuestLayout>
    <Head title="Страница подключения плагина" />

    <div class="w-full p-6">
      <h1 class="text-2xl text-black dark:text-white font-semibold mb-4">{{ message }}</h1>
    </div>
  </GuestLayout>
</template>
