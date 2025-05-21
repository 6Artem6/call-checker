<script setup>
import axios from "axios";
import {onMounted, ref} from "vue";
import {Head, usePage} from '@inertiajs/vue3';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileUploader from '@/Components/FileUploader.vue';

const page = usePage();

const themes = ref();
const selectedTheme = ref("");
const instructions = ref([]);
const showCreateForm = ref(false);
const newInstructionText = ref("");

themes.value = page.props.theme_list;

const fetchInstructions = async () => {
    if (!selectedTheme.value) {
        instructions.value = [];
        return;
    }

    try {
        const response = await axios.post("/instruction-list", {
            id: selectedTheme.value,
        });
        if (response.data.status && response.data.data.length > 0) {
            instructions.value = response.data.data.map((item) => ({
                ...item,
                is_set: 0,
            }));
        }
    } catch (error) {
        console.error("Fetch instructions error:", error);
    }
};

const createInstruction = async () => {
    try {
        const response = await axios.post("/instruction-create", {
            theme_id: selectedTheme.value,
            instruction_text: newInstructionText.value,
        });

        if (response.data.status) {
            instructions.value.push({
                id: response.data.instruction.instruction_id,
                text: response.data.instruction.instruction_text,
                is_set: 1,
            });
            showCreateForm.value = false;
            newInstructionText.value = "";
        } else {
            console.error("Error creating instruction:", response.data.data);
        }
    } catch (error) {
        console.error("Create instruction error:", error);
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Отправка запроса" />

        <div class="w-full md:w-1/2 p-6">
            <h1 class="text-2xl text-white font-semibold mb-4">Отправка запроса</h1>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="_token" :value="page.props.csrf_token">

                <FileUploader
                    inputName="upload_files[]"
                    placeholder="Перетащите аудиофайлы сюда или выберите"
                    buttonText="Добавить файлы"
                    containerClass="file border p-4 rounded-lg bg-gray-50 text-center"
                    buttonClass="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600"
                    inputClass="hidden"
                />

                <div class="my-4">
                    <button
                        type="submit"
                        class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        {{ 'Создать' }}
                    </button>
                </div>

                <!-- Выбор темы -->
                <div class="mb-4">
                    <label for="theme_id" class="block text-lg font-medium text-gray-700 dark:text-gray-300">
                        {{ 'Выберите тему' }}
                    </label>
                    <select
                        id="theme_id"
                        name="theme_id"
                        v-model="selectedTheme"
                        @change="fetchInstructions"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="">{{ 'Выберите тему' }}</option>
                        <option v-for="(theme, id) in themes" :key="id" :value="id">{{ theme }}</option>
                    </select>
                </div>

                <!-- Таблица инструкций -->
                <div class="py-5">
                    <div class="overflow-auto" style="max-height: 500px;">
                        <table id="instruction-table" class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-center">{{ 'Инструкция' }}</th>
                                <th class="px-4 py-2 text-center">{{ 'Использовать' }}</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <tr v-for="instruction in instructions" :key="instruction.id"
                                class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                <td class="px-6 py-4 text-center">{{ instruction.text }}</td>
                                <td class="px-6 py-4 text-center">
                                    <div class="btn-group">
                                        <input type="hidden" :name="`Instruction[${instruction.id}][is_set]`" value="0" />
                                        <input
                                            type="radio"
                                            :id="`instruction-${instruction.id}-is_set-yes`"
                                            :name="`Instruction[${instruction.id}][is_set]`"
                                            value="1"
                                            class="btn-check"
                                            v-model="instruction.is_set"
                                        />
                                        <label
                                            class="btn btn-outline-success"
                                            :for="`instruction-${instruction.id}-is_set-yes`"
                                        >
                                            ✔
                                        </label>
                                        <input
                                            type="radio"
                                            :id="`instruction-${instruction.id}-is_set-no`"
                                            :name="`Instruction[${instruction.id}][is_set]`"
                                            value="0"
                                            class="btn-check"
                                            v-model="instruction.is_set"
                                        />
                                        <label
                                            class="btn btn-outline-danger"
                                            :for="`instruction-${instruction.id}-is_set-no`"
                                        >
                                            ✘
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>

            <!-- Кнопка для создания новой инструкции -->
            <div>
                <button
                    class="w-full bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                    @click="showCreateForm = !showCreateForm"
                >
                    {{ showCreateForm ? 'Скрыть форму' : 'Создать инструкцию' }}
                </button>
            </div>

            <!-- Форма создания новой инструкции -->
            <div v-if="showCreateForm" class="mt-4">
                <form @submit.prevent="createInstruction" class="space-y-4">
                    <div>
                        <label for="instruction_text" class="block text-lg font-medium text-gray-700">
                            {{ 'Текст инструкции' }}
                        </label>
                        <input
                            type="text"
                            v-model="newInstructionText"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="Введите текст инструкции"
                        />
                    </div>
                    <button
                        type="submit"
                        class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        {{ 'Создать' }}
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
