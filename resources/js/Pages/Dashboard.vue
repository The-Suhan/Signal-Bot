<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PriceChart from '@/Components/PriceChart.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    symbol: String,
    activeStrategy: Object,
    activeSignalsCount: Number,
    latestSignal: Object,
});

const directionLabel = (d) => (d === 'buy' ? 'ALIŞ' : 'SATIŞ');
const statusLabel = {
    pending: 'Bekliyor',
    triggered: 'Tetiklendi',
    closed_tp: 'TP ile kapandı',
    closed_sl: 'SL ile kapandı',
    expired: 'Süresi doldu',
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Özet kartlar -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Aktif Strateji</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ activeStrategy?.name ?? 'Yok' }}
                        </div>
                        <Link
                            :href="route('strategies.index')"
                            class="mt-2 inline-block text-sm text-indigo-600 hover:underline"
                        >
                            Stratejileri yönet →
                        </Link>
                    </div>

                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Açık Sinyal Sayısı</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ activeSignalsCount }}
                        </div>
                        <Link
                            :href="route('signals.index')"
                            class="mt-2 inline-block text-sm text-indigo-600 hover:underline"
                        >
                            Sinyalleri görüntüle →
                        </Link>
                    </div>

                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Son Sinyal</div>
                        <template v-if="latestSignal">
                            <div class="mt-1 text-lg font-semibold text-gray-900">
                                {{ directionLabel(latestSignal.direction) }} @ {{ Number(latestSignal.entry_price).toFixed(3) }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ statusLabel[latestSignal.status] ?? latestSignal.status }}
                            </div>
                        </template>
                        <div v-else class="mt-1 text-gray-400">Henüz sinyal yok</div>
                    </div>
                </div>

                <!-- Canlı grafik -->
                <div class="overflow-hidden rounded-lg bg-white p-6 shadow-sm">
                    <PriceChart :symbol="symbol" timeframe="1m" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
