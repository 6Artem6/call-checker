<script setup>
import { ref, nextTick } from "vue";

// Props для настройки
defineProps({
    inputName: {
        type: String,
        default: "file-input", // Имя input по умолчанию
    },
    placeholder: {
        type: String,
        default: "Перетащите аудиофайлы сюда", // Текст-заполнитель
    },
    buttonText: {
        type: String,
        default: "Выберите аудиофайл", // Текст кнопки
    },
    containerClass: {
        type: String,
        default:
            "file flex flex-col items-center justify-center p-6 border-2 border-dashed border-gray-300 rounded-lg", // Классы контейнера
    },
    inputClass: {
        type: String,
        default: "hidden", // Классы для input
    },
    buttonClass: {
        type: String,
        default:
            "mt-4 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none", // Классы для кнопки
    },
});

const files = ref([]);
const fileInput = ref(null);

// Проверка типа файла
const isAudioFile = (file) => {
    return file.type.startsWith("audio/");
};

// Обработчик перетаскивания файлов
const handleDrop = (event) => {
    clearFiles();

    const droppedFiles = event.dataTransfer.files;

    if (droppedFiles.length > 0) {
        for (let i = 0; i < droppedFiles.length; i++) {
            if (isAudioFile(droppedFiles[i])) {
                files.value.push(droppedFiles[i]);
            } else {
                alert(`Файл "${droppedFiles[i].name}" не является аудиофайлом.`);
            }
        }
    }
};

// Обработчик выбора файлов через input
const handleFileChange = (event) => {
    clearFiles();

    const selectedFiles = event.target.files;

    if (selectedFiles.length > 0) {
        for (let i = 0; i < selectedFiles.length; i++) {
            if (isAudioFile(selectedFiles[i])) {
                files.value.push(selectedFiles[i]);
            } else {
                alert(`Файл "${selectedFiles[i].name}" не является аудиофайлом.`);
            }
        }
    }
};

// Функция для активации скрытого input
const triggerFileInput = (event) => {
    event.preventDefault();
    nextTick(() => {
        if (fileInput.value) {
            fileInput.value.click();
        } else {
            console.error("Ошибка: input не найден.");
        }
    });
};

// Функция для очистки массива файлов
const clearFiles = () => {
    files.value = [];
};

// Функция для удаления отдельного файла
const removeFile = (index) => {
    files.value.splice(index, 1);
};
</script>

<template>
    <div
        :class="containerClass"
        @dragover.prevent
        @drop.prevent="handleDrop"
    >
        <p v-if="files.length === 0" class="text-lg text-gray-500">
            {{ placeholder }}
        </p>
        <div v-else class="w-full flex flex-col gap-4">
            <p class="text-lg text-green-500">Выбрано файлов: {{ files.length }}</p>

            <!-- Список файлов с кнопкой удаления -->
            <ul class="file-list flex flex-col gap-2">
                <li
                    v-for="(file, index) in files"
                    :key="index"
                    class="flex items-center justify-between bg-gray-100 p-2 rounded shadow-sm"
                >
                    <span class="text-sm text-gray-700 truncate">{{ file.name }}</span>
                    <button
                        @click="removeFile(index)"
                        class="text-red-500 hover:text-red-700 focus:outline-none"
                    >
                        ✖
                    </button>
                </li>
            </ul>
        </div>

        <!-- Input для выбора файла (скрытый) -->
        <input
            :name="inputName"
            type="file"
            accept="audio/*"
            :class="inputClass"
            ref="fileInput"
            @change="handleFileChange"
            multiple
        />

        <button
            @submit.prevent=""
            :class="buttonClass"
            @click="triggerFileInput"
        >
            {{ buttonText }}
        </button>
    </div>
</template>


<style scoped>
/* Добавьте стили для лучшего отображения области перетаскивания */
div.file {
    min-height: 200px;
    width: 100%;
    background-color: #f9fafb;
    transition: background-color 0.3s ease;
}
div.file:hover {
    background-color: #f3f4f6;
}
</style>
