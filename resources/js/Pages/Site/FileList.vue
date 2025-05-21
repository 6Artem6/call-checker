<script setup>
import {onMounted, ref} from "vue";
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const page = usePage();

const request = ref();
request.value = page.props.request;

const formatTime = (time) => {
    time *= 1000;
    return new Date(time).toISOString().substring(11, 19);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="'Просмотр файлов запроса #' + request.request_id" />

        <div class="w-full p-6">
            <h1 class="text-2xl text-white font-semibold mb-4">{{ `Просмотр файлов запроса #${request.request_id}` }}</h1>

            <div class="flex justify-center">
                <div class="w-full max-w-4xl">
                    <div class="bg-light text-dark shadow-md rounded-lg">
                        <div class="flex justify-between items-center bg-gray-100 p-4 rounded-t-lg">
                            <span class="font-semibold text-lg">{{ 'Файлы' }}</span>
                            <a href="/request-list" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-700">
                                Назад
                            </a>
                        </div>

                        <!-- Файлы -->
                        <div v-for="(file, index) in request.files" :key="file.id" class="p-4 border-b border-gray-200">
                            <div class="text-lg font-medium mb-2">
                                {{ `${index + 1}. ${file.file_name} (${formatTime(file.file_time)}) - ${file.status.status_name}` }}
                            </div>
                            <div v-html="file.view_html" class="mb-2"></div>

                            <div class="flex justify-between items-center">
                                <a :href="`/file-info/${file.file_id}`" class="bg-blue-500 text-white text-sm py-1 px-4 rounded hover:bg-blue-600">
                                    Информация
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.bg-light {
    background-color: #f9fafb;
}
.text-dark {
    color: #333;
}
</style>
