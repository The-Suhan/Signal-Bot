<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { createChart, CandlestickSeries, ColorType } from 'lightweight-charts';
import { usePage } from '@inertiajs/vue3';
import { useDarkMode } from '@/composables/useDarkMode';
import axios from 'axios';

const { isDark } = useDarkMode();

// lightweight-charts kendi tema API'sini kullanır (applyOptions) — Tailwind
// dark: sınıfları grafiğin içine (canvas) işlemez, bu yüzden burada elle
// eşleniyor. Mum renkleri (yeşil/kırmızı) her iki temada da aynı bırakıldı
// (evrensel kural), sadece arka plan/ızgara/yazı rengi temaya göre değişir.
const chartTheme = {
    light: {
        layout: {
            background: { type: ColorType.Solid, color: 'transparent' },
            textColor: '#374151', // gray-700
        },
        grid: {
            vertLines: { color: '#f3f4f6' }, // gray-100
            horzLines: { color: '#f3f4f6' },
        },
    },
    dark: {
        layout: {
            background: { type: ColorType.Solid, color: 'transparent' },
            textColor: '#cbd5e1', // slate-300
        },
        grid: {
            vertLines: { color: '#334155' }, // slate-700
            horzLines: { color: '#334155' },
        },
    },
};

const props = defineProps({
    symbol: { type: String, required: true },
    timeframe: { type: String, default: '1m' },
    pollIntervalMs: { type: Number, default: 4000 },
});

const page = usePage();
// Backend UTC saklıyor; burada tarayıcının kendi saat dilimine güvenmek
// yerine açıkça sunucudan gelen displayTimezone'a (Asia/Ashgabat) çeviriyoruz.
const formatLocalTime = (iso) =>
    new Date(iso).toLocaleTimeString('tr-TR', { timeZone: page.props.displayTimezone });

const chartContainer = ref(null);
const lastPrice = ref(null);
const lastPriceAt = ref(null);
const loadError = ref(null);

let chart = null;
let series = null;
let candlePollTimer = null;
let pricePollTimer = null;
let resizeObserver = null;

async function loadCandles() {
    try {
        const { data } = await axios.get(route('market-data.candles'), {
            params: { timeframe: props.timeframe, limit: 300 },
        });
        if (series && data.candles?.length) {
            series.setData(data.candles);
            chart.timeScale().fitContent();
        }
        loadError.value = null;
    } catch (e) {
        loadError.value = 'Mum verisi alınamadı.';
    }
}

async function pollLastPrice() {
    try {
        const { data } = await axios.get(route('market-data.last-price'));
        if (data.price) {
            lastPrice.value = data.price;
            lastPriceAt.value = data.timestamp;
        }
    } catch (e) {
        // sessiz geç — bağlantı geçici kesilmiş olabilir, bir sonraki pollde tekrar denenir
    }
}

onMounted(() => {
    chart = createChart(chartContainer.value, {
        ...chartTheme[isDark.value ? 'dark' : 'light'],
        width: chartContainer.value.clientWidth,
        height: 420,
        timeScale: { timeVisible: true, secondsVisible: false },
    });

    series = chart.addSeries(CandlestickSeries, {
        upColor: '#16a34a',
        downColor: '#dc2626',
        borderVisible: false,
        wickUpColor: '#16a34a',
        wickDownColor: '#dc2626',
    });

    loadCandles();
    pollLastPrice();

    candlePollTimer = setInterval(loadCandles, props.pollIntervalMs * 3);
    pricePollTimer = setInterval(pollLastPrice, props.pollIntervalMs);

    resizeObserver = new ResizeObserver(() => {
        if (chart && chartContainer.value) {
            chart.applyOptions({ width: chartContainer.value.clientWidth });
        }
    });
    resizeObserver.observe(chartContainer.value);
});

// Kullanıcı tema butonuna basınca grafiği yeniden oluşturmadan, canlı
// olarak lightweight-charts'ın kendi applyOptions() API'siyle günceller.
watch(isDark, (dark) => {
    chart?.applyOptions(chartTheme[dark ? 'dark' : 'light']);
});

onBeforeUnmount(() => {
    clearInterval(candlePollTimer);
    clearInterval(pricePollTimer);
    resizeObserver?.disconnect();
    chart?.remove();
});
</script>

<template>
    <div>
        <div class="mb-3 flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ symbol }} · {{ timeframe }}</span>
                <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    <span v-if="lastPrice">{{ Number(lastPrice).toFixed(3) }}</span>
                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                </div>
            </div>
            <div v-if="lastPriceAt" class="text-xs text-gray-400 dark:text-gray-500">
                Son güncelleme: {{ formatLocalTime(lastPriceAt) }}
            </div>
        </div>
        <p v-if="loadError" class="mb-2 text-sm text-red-600 dark:text-red-400">{{ loadError }}</p>
        <div ref="chartContainer" class="w-full" />
    </div>
</template>
