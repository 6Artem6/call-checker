<script setup>

import GuestLayout from '@/Layouts/GuestLayout.vue';
import {onMounted} from "vue";
import {Head} from "@inertiajs/vue3";
</script>

<template>
    <GuestLayout>
        <Head title="Главная страница" />

        <div class="site-index">
            <div class="text-center text-white bg-transparent mt-5 mb-5">
                <h1 class="text-4xl font-semibold">Добро пожаловать</h1>
                <p class="text-xl mt-2 mb-4">на платформу для проверки качества звонков!</p>
                <template v-if="!this.$page.props.auth.user">
                    <!-- Кнопка для гостей -->
                    <a
                        :href="route('login')"
                        class="inline-block bg-blue-500 text-white py-2 px-6 rounded-lg text-lg"
                    >
                        {{ 'Войти' }}
                    </a>
                </template>
                <template v-else>
                    <!-- Кнопка для авторизованных пользователей -->
                    <a
                        :href="route('request-send')"
                        class="inline-block bg-blue-500 text-white py-2 px-6 rounded-lg text-lg"
                    >
                        {{ 'Рабочее место' }}
                    </a>
                </template>

            </div>
        </div>

    </GuestLayout>
</template>

