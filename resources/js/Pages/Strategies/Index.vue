<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    strategies: { type: Array, required: true },
});

const page = usePage();

// Her strateji kartı için ayrı bir form state'i (parametreler bilinen EMA
// Cross alanlarıyla düzenlenir; farklı bir strateji sınıfı eklendiğinde
// parameters objesindeki anahtarlar aynen korunur).
const forms = reactive(
    Object.fromEntries(
        props.strategies.map((s) => [
            s.id,
            useForm({
                is_active: s.is_active,
                parameters: { ...s.parameters },
            }),
        ]),
    ),
);

function toggle(strategy) {
    const form = forms[strategy.id];
    form.is_active = !form.is_active;
    form.patch(route('strategies.update', strategy.id), { preserveScroll: true });
}

function saveParameters(strategy) {
    forms[strategy.id].patch(route('strategies.update', strategy.id), {
        preserveScroll: true,
    });
}

function runBacktest(strategy) {
    useForm({ days: 30 }).post(route('strategies.backtest', strategy.id), {
        preserveScroll: true,
    });
}

const fmtPct = (v) => (v === null || v === undefined ? '—' : `%${Number(v).toFixed(2)}`);
const fmtR = (v) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(2)}R`);
</script>

<template>
    <Head title="Stratejiler" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Strateji Performansı
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    {{ page.props.flash.success }}
                </div>

                <div v-if="strategies.length === 0" class="rounded-lg bg-white p-6 text-center text-gray-400 shadow-sm">
                    Henüz kayıtlı strateji yok.
                </div>

                <div
                    v-for="strategy in strategies"
                    :key="strategy.id"
                    class="overflow-hidden rounded-lg bg-white shadow-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b px-6 py-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900">{{ strategy.name }}</h3>
                                <span
                                    v-if="strategy.is_active"
                                    class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"
                                >
                                    AKTİF
                                </span>
                            </div>
                            <p class="text-xs text-gray-400">{{ strategy.class }}</p>
                        </div>

                        <button
                            type="button"
                            @click="toggle(strategy)"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="strategy.is_active ? 'bg-green-500' : 'bg-gray-300'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                :class="strategy.is_active ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-6 p-6 lg:grid-cols-2">
                        <!-- Backtest metrikleri -->
                        <div>
                            <h4 class="mb-3 text-sm font-medium text-gray-500">
                                Son Backtest Sonucu
                            </h4>
                            <div v-if="strategy.latest_backtest" class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                                <div class="rounded bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Win Rate</div>
                                    <div class="font-semibold">{{ fmtPct(strategy.latest_backtest.win_rate) }}</div>
                                </div>
                                <div class="rounded bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Expectancy</div>
                                    <div class="font-semibold">{{ fmtR(strategy.latest_backtest.expectancy) }}</div>
                                </div>
                                <div class="rounded bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Max Drawdown</div>
                                    <div class="font-semibold">{{ fmtR(strategy.latest_backtest.max_drawdown) }}</div>
                                </div>
                                <div class="rounded bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Toplam Sinyal</div>
                                    <div class="font-semibold">{{ strategy.latest_backtest.total_signals }}</div>
                                </div>
                                <div class="rounded bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Kazanan / Kaybeden</div>
                                    <div class="font-semibold">
                                        {{ strategy.latest_backtest.wins }} / {{ strategy.latest_backtest.losses }}
                                    </div>
                                </div>
                                <div class="rounded bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">Kullanılan Sinyal</div>
                                    <div class="font-semibold">{{ strategy.signals_count }}</div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-400">Henüz backtest çalıştırılmadı.</p>

                            <SecondaryButton class="mt-4" @click="runBacktest(strategy)">
                                Son 30 Gün için Backtest Çalıştır
                            </SecondaryButton>
                        </div>

                        <!-- Parametre ayarları -->
                        <div>
                            <h4 class="mb-3 text-sm font-medium text-gray-500">Parametreler</h4>
                            <div class="space-y-3">
                                <div v-for="(value, key) in forms[strategy.id].parameters" :key="key">
                                    <InputLabel :for="`${strategy.id}-${key}`" :value="key" />
                                    <TextInput
                                        :id="`${strategy.id}-${key}`"
                                        v-model="forms[strategy.id].parameters[key]"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                </div>
                            </div>
                            <PrimaryButton
                                class="mt-4"
                                :disabled="forms[strategy.id].processing"
                                @click="saveParameters(strategy)"
                            >
                                Parametreleri Kaydet
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
