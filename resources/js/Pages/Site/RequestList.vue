<script setup>
import {onMounted, ref} from "vue";
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';


const page = usePage();

const requests = ref();
requests.value = page.props.requests;
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Список запросов" />

        <div class="w-full p-6">
            <h1 class="text-2xl text-white font-semibold mb-4">{{ 'Просмотр списка запросов' }}</h1>

            <div class="flex justify-center">
                <div class="w-full max-w-4xl">
                    <div class="bg-light text-dark shadow-md rounded-lg">
                        <!-- Запросы -->
                        <div v-for="(request, index) in requests" :key="request.id" class="p-4 border-b border-gray-200">
                            <div class="text-lg font-medium mb-2">
                                {{ `#${request.request_id}. Файлов: ${request.files.length} - ${request.status.status_name}` }}
                            </div>
                            <div v-html="request.view_html" class="mb-2"></div>

                            <div class="flex justify-between items-center">
                                <a :href="`/file-list/${request.request_id}`" class="bg-blue-500 text-white text-sm py-1 px-4 rounded hover:bg-blue-700">
                                    Список файлов
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
.site {
    padding: 20px;
}
.bg-light {
    background-color: #f9fafb;
}
.text-dark {
    color: #333;
}
</style>
