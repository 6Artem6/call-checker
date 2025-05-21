<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
// import { useRoute } from "vue-router";
import WaveSurfer from "wavesurfer.js";
import RegionsPlugin from "wavesurfer.js/dist/plugins/regions.js";
import TimelinePlugin from 'wavesurfer.js/dist/plugins/timeline.esm.js'

const page = usePage();
// Получение параметра маршрута
// const route = useRoute();

// Рефы для данных
const model = ref({ value: { request_id: null, file_id: null } });
const segments = ref([]);
const chunks = ref([]);
const instructions = ref([]);
const analysisText = ref("");
const fileUrl = ref("");
const backUrl = ref("");

let wavesurfer = null;
const regions = RegionsPlugin.create();  // Убедимся, что RegionsPlugin инициализируется правильно
let activeRegion = null;
let isPlaying = ref(false); // Флаг для отслеживания состояния воспроизведения
let loop = ref(false); // Флаг для управления зацикливанием

// Заполнение данных
model.value = page.props.model;
segments.value = page.props.segments;
chunks.value = page.props.chunks;
instructions.value = page.props.instructions;
analysisText.value = page.props.analysisText;
fileUrl.value = "/file/" + model.value.file_id;
backUrl.value = "/file-list/" +  model.value.request_id;

const formatTime = (time) => {
    return new Date(time).toISOString().substring(14, 19);
};

// Инициализация WaveSurfer и плагина Regions
const initWaveSurfer = () => {
    wavesurfer = WaveSurfer.create({
        container: "#waveform",
        waveColor: "#7C3AED",
        progressColor: "#4C1D95",
        url: fileUrl.value,
        plugins: [
            regions, // Регистрация плагина Regions
            TimelinePlugin.create()
        ],
    });

    // Получаем плагин после инициализации
    wavesurfer.on("decode", () => {
        segments.value.forEach((segment) => {
            regions.addRegion({
                start: segment.start,
                end: segment.end,
                content: segment.label,
                color: segment.speaker === 1 ? "rgba(255, 0, 0, 0.3)" : "rgba(0, 0, 255, 0.3)",
                drag: false,
                resize: false,
            });
        });
    });

    // Обработка клика на регион
    regions.on("region-clicked", (region, e) => {
        e.stopPropagation(); // предотвращаем клик по волне
        activeRegion = region;

        // Проверяем зацикливание
        if (loop.value) {
            region.play();  // Воспроизведение региона
            region.once('finish', () => {
                region.play();  // Зацикливаем воспроизведение
            });
        } else {
            region.play();  // Воспроизведение региона без зацикливания
        }

        // Обновляем флаг состояния воспроизведения
        isPlaying.value = true; // Устанавливаем флаг воспроизведения в true при клике на регион

        region.setOptions({
            color: `rgba(${Math.random() * 255}, ${Math.random() * 255}, ${Math.random() * 255}, 0.5)`,
        });
    });

    regions.on('region-in', (region) => {
        console.log('region-in', region)
        if (!loop.value) {
            activeRegion = region
        }
    })
    regions.on('region-out', (region) => {
        console.log('region-out', region)
        if (activeRegion === region) {
            if (loop.value) {
                region.play()
            } else {
                activeRegion = null
            }
        }
    })

    // Событие окончания воспроизведения региона
    wavesurfer.on('finish', () => {
        if (activeRegion) {
            if (loop.value) {
                // Если зацикливание включено, то воспроизводим регион снова
                activeRegion.play();
            } else {
                // Если не зацикливаем, останавливаем воспроизведение
                isPlaying.value = false;
            }
        }
    });

    // Обновление масштаба (zoom) при изменении
    wavesurfer.once('decode', () => {
        document.querySelector('input[type="range"]').oninput = (e) => {
            const minPxPerSec = Number(e.target.value);
            wavesurfer.zoom(minPxPerSec);
        };
    });
};

// Функция для перевода времени в секунды
const timeToSeconds = (milliseconds) => {
    return milliseconds / 1000;
};

// Функция для выделения региона и воспроизведения его при клике на фразу
const playAndHighlightRegion = (startTime, endTime, text) => {
    if (wavesurfer) {
        const startSeconds = timeToSeconds(startTime);
        const endSeconds = timeToSeconds(endTime);
        // activeRegion = region
        // Находим регион, который соответствует фразе
        const region = regions.regions.find((region) => {
            return region.start === startSeconds && region.end === endSeconds;
        });

        if (region) {
            activeRegion = region;
            // Воспроизводим регион
            isPlaying.value = true; // Обновляем состояние кнопки
            // Запуск воспроизведения выбранного региона
            region.play();
            region.setOptions({
                color: `rgba(${Math.random() * 255}, ${Math.random() * 255}, ${Math.random() * 255}, 0.5)`,  // Подсветка региона
            });
        }
    }
};

// Запуск/остановка воспроизведения
const togglePlayPause = () => {
    if (isPlaying.value) {
        wavesurfer.pause();
        isPlaying.value = false; // Обновляем состояние кнопки
    } else {
        wavesurfer.play();
        isPlaying.value = true; // Обновляем состояние кнопки
    }
};

// Завершаем проигрывание при размонтировании компонента
onBeforeUnmount(() => {
    if (wavesurfer) {
        wavesurfer.destroy();
    }
});

onMounted(() => {
    initWaveSurfer();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Просмотр информации о записи" />

        <div class="w-full mx-auto p-6">
            <!-- Заголовок страницы -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-xl text-white font-semibold">Просмотр информации о записи</h1>
                <a
                    :href="backUrl"
                    class="bg-blue-500 hover:bg-blue-700 text-white py-2 px-4 rounded"
                >
                    Назад
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Карточка с транскриптом -->
                <div class="bg-gray-100 rounded shadow p-4">
                    <h2 class="text-lg font-semibold mb-2">Транскрипт</h2>
                    <p class="text-gray-700 mb-4">{{ fileName }}</p>

                    <div id="waveform" class="mt-4"></div>

                    <!-- Кнопки управления -->
                    <div class="mt-4 flex justify-between">
                        <button
                            class="bg-green-500 hover:bg-green-700 text-white py-2 px-4 rounded"
                            @click="togglePlayPause"
                        >
                            {{ isPlaying ? 'Остановить' : 'Запустить' }}
                        </button>

                        <!-- Контрол для зацикливания -->
                        <div>
                            <label>
                                <input
                                    type="checkbox"
                                    :checked="loop"
                                    @click="loop = !loop"
                                />
                                Зациклить регионы
                            </label>
                        </div>

                        <!-- Контрол для зума -->
                        <label style="margin-left: 2em">
                            Зум: <input type="range" min="10" max="1000" value="10"/>
                        </label>
                    </div>

                    <div class="mt-4">
                        <h3 class="text-md font-semibold">Расшифровка</h3>
                        <div>
                            <div
                                v-for="(chunk, index) in chunks"
                                :key="index"
                                class="border-b py-2 cursor-pointer hover:bg-gray-200"
                                @click="() => playAndHighlightRegion(chunk.start_milliseconds, chunk.end_milliseconds, chunk.text)"
                            >
                                <h5 class="text-sm font-medium">
                                    {{ chunk.speaker }} ({{ formatTime(chunk.start_milliseconds) }} -
                                    {{ formatTime(chunk.end_milliseconds) }})
                                </h5>
                                <p class="text-gray-600">
                                    {{ chunk.text }} ({{ chunk.confidence }}%)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Карточка анализа -->
                <div class="bg-gray-100 rounded shadow p-4">
                    <h2 class="text-lg font-semibold mb-2">Анализ</h2>

                    <!-- Инструкции -->
                    <div class="mb-4">
                        <h3 class="text-md font-semibold">Инструкции</h3>
                        <ul class="list-disc ml-5 text-gray-700">
                            <li v-for="(instruction, index) in instructions" :key="index">
                                {{ instruction.instruction.instruction_text }}
                            </li>
                        </ul>
                    </div>

                    <!-- Результат проверки -->
                    <div>
                        <h3 class="text-md font-semibold">Результат проверки</h3>
                        <p class="text-gray-700" v-html="analysisText"></p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
