<script setup>
import { usePage } from '@inertiajs/vue3'

const appUrl = usePage().props.appUrl

const url = 'https://view.officeapps.live.com/op/embed.aspx?src=' +
    encodeURIComponent(appUrl + '/docs/ПОЛИТИКА КОМПАНИИ В ОТНОШЕНИИ ОБРАБОТКИ ПЕРСОНАЛЬНЫХ ДАННЫХ.docx');
</script>

<template>
  <div class="h-screen w-screen">
    <iframe
        :src="url"
        width="100%"
        height="100%"
    ></iframe>
  </div>
</template>
