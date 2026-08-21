<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import CopySignalButton from '@/Components/CopySignalButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    activeSignals: { type: Array, required: true },
    historySignals: { type: Object, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
    parameterComparison: { type: Object, default: null },
});

const page = usePage();
const directionLabel = (d) => (d === 'buy' ? '🟢 ALIŞ' : '🔴 SATIŞ');
const fmt = (v) => (v === null || v === undefined ? '—' : Number(v).toFixed(3));
// Backend UTC saklıyor; gösterirken açıkça sunucudan gelen displayTimezone'a
// (Asia/Ashgabat) çeviriyoruz — tarayıcının kendi saat dilimine güvenmiyoruz.
const fmtDate = (v) =>
    v ? new Date(v).toLocaleString('tr-TR', { timeZone: page.props.displayTimezone }) : '—';

const statusLabel = {
    pending: 'Bekliyor',
    triggered: 'Tetiklendi',
    closed_tp: 'TP',
    closed_sl: 'SL',
    expired: 'Süresi Doldu',
};

const periodOptions = [
    { value: 'today', label: 'Bugün' },
    { value: 'week', label: 'Bu Hafta' },
    { value: 'month', label: 'Bu Ay' },
    { value: 'all', label: 'Tümü' },
];

function setPeriod(period) {
    router.get(
        route('signals.index'),
        { period },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            // Sadece geçmiş tablosu ve filtre state'i yeniden istenir —
            // aktif sinyaller ve özet kartlar tekrar sorgulanmaz.
            only: ['historySignals', 'filters'],
        },
    );
}

function signalToText(s) {
    const lines = [
        `${s.symbol} ${s.direction === 'buy' ? 'ALIŞ' : 'SATIŞ'}`,
        `Strateji: ${s.strategy?.name ?? '—'}`,
        `Entry: ${fmt(s.entry_price)}`,
        `SL: ${fmt(s.sl_price)} (${s.sl_pips} pip)`,
        `TP: ${fmt(s.tp_price)} (${s.tp_pips} pip)`,
    ];

    if (s.status === 'closed_tp') {
        lines.push(`Durum: TP (+${s.tp_pips} pip)`);
        lines.push(`Kapandı: ${fmtDate(s.closed_at)}`);
    } else if (s.status === 'closed_sl') {
        lines.push(`Durum: SL (-${s.sl_pips} pip)`);
        lines.push(`Kapandı: ${fmtDate(s.closed_at)}`);
    } else if (s.status === 'expired') {
        lines.push('Durum: Süresi Doldu');
        lines.push(`Kapandı: ${fmtDate(s.closed_at)}`);
    } else {
        lines.push(`Durum: ${statusLabel[s.status] ?? s.status}`);
        lines.push(`Oluşturuldu: ${fmtDate(s.created_at)}`);
    }

    return lines.join('\n');
}

function fmtPips(v) {
    if (v === null || v === undefined) return '—';
    return `${v > 0 ? '+' : ''}${v} pip`;
}

function fmtWinRate(v) {
    return v === null || v === undefined ? '—' : `%${v}`;
}
</script>

<template>
    <Head title="Sinyaller" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                Sinyaller
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                <!-- Özet performans kartları -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div
                        v-for="(card, key) in { week: 'Bu Hafta', month: 'Bu Ay', allTime: 'Tüm Zamanlar' }"
                        :key="key"
                        class="rounded-lg bg-white p-5 shadow-sm dark:bg-slate-800"
                    >
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ card }}</div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ fmtWinRate(stats[key].win_rate) }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">win rate</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ stats[key].wins }} kazanç / {{ stats[key].losses }} kayıp</span>
                            <span v-if="stats[key].expired > 0">{{ stats[key].expired }} süresi doldu</span>
                        </div>
                        <div
                            class="mt-2 text-sm font-medium"
                            :class="stats[key].total_pips >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ fmtPips(stats[key].total_pips) }}
                        </div>
                    </div>
                </div>

                <!-- Parametre değişikliği öncesi/sonrası karşılaştırma (bilgi amaçlı) -->
                <div
                    v-if="parameterComparison"
                    class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-slate-800"
                >
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                            Parametre Değişikliği Karşılaştırması
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ fmtDate(parameterComparison.changed_at) }} itibarıyla: {{ parameterComparison.summary }}
                            — bilgi amaçlı, sadece gözlem içindir.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                        <div
                            v-for="(card, key) in { before: 'Eski Dönem (değişiklikten önce)', after: 'Yeni Dönem (değişiklikten sonra)' }"
                            :key="key"
                            class="rounded-lg p-4"
                            :class="key === 'after' ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'bg-gray-50 dark:bg-slate-900/50'"
                        >
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ card }}</div>
                            <div class="mt-2 flex items-baseline gap-2">
                                <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ fmtWinRate(parameterComparison[key].win_rate) }}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">win rate</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ parameterComparison[key].wins }} kazanç / {{ parameterComparison[key].losses }} kayıp</span>
                                <span v-if="parameterComparison[key].expired > 0">{{ parameterComparison[key].expired }} süresi doldu</span>
                            </div>
                            <div
                                class="mt-2 text-sm font-medium"
                                :class="parameterComparison[key].total_pips >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                {{ fmtPips(parameterComparison[key].total_pips) }}
                            </div>
                            <p v-if="parameterComparison[key].total === 0" class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                Bu dönemde henüz kapanmış sinyal yok.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Aktif sinyaller -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-slate-800">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Aktif Sinyaller</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-slate-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Strateji</th>
                                    <th class="px-4 py-3">Yön</th>
                                    <th class="px-4 py-3">Entry</th>
                                    <th class="px-4 py-3">SL</th>
                                    <th class="px-4 py-3">TP</th>
                                    <th class="px-4 py-3">Güven</th>
                                    <th class="px-4 py-3">Durum</th>
                                    <th class="px-4 py-3">Oluşturuldu</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-gray-200">
                                <tr v-if="activeSignals.length === 0">
                                    <td colspan="9" class="px-4 py-6 text-center text-gray-400 dark:text-gray-500">
                                        Şu anda aktif sinyal yok.
                                    </td>
                                </tr>
                                <tr v-for="s in activeSignals" :key="s.id">
                                    <td class="px-4 py-3">{{ s.strategy?.name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ directionLabel(s.direction) }}</td>
                                    <td class="px-4 py-3">{{ fmt(s.entry_price) }}</td>
                                    <td class="px-4 py-3">{{ fmt(s.sl_price) }}</td>
                                    <td class="px-4 py-3">{{ fmt(s.tp_price) }}</td>
                                    <td class="px-4 py-3">{{ s.confidence_pct ? `%${s.confidence_pct}` : '—' }}</td>
                                    <td class="px-4 py-3"><StatusBadge :status="s.status" /></td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ fmtDate(s.created_at) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <CopySignalButton :text="signalToText(s)" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Geçmiş sinyaller -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Geçmiş Sinyaller</h3>
                        <div class="flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-slate-900/60">
                            <button
                                v-for="opt in periodOptions"
                                :key="opt.value"
                                type="button"
                                @click="setPeriod(opt.value)"
                                class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                                :class="filters.period === opt.value
                                    ? 'bg-white text-gray-900 shadow-sm dark:bg-slate-700 dark:text-gray-100'
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-slate-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Strateji</th>
                                    <th class="px-4 py-3">Yön</th>
                                    <th class="px-4 py-3">Entry</th>
                                    <th class="px-4 py-3">Sonuç</th>
                                    <th class="px-4 py-3">Durum</th>
                                    <th class="px-4 py-3">Kapandı</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-700 dark:text-gray-200">
                                <tr v-if="historySignals.data.length === 0">
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400 dark:text-gray-500">
                                        Bu dönemde kapanmış sinyal yok.
                                    </td>
                                </tr>
                                <tr v-for="s in historySignals.data" :key="s.id">
                                    <td class="px-4 py-3">{{ s.strategy?.name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ directionLabel(s.direction) }}</td>
                                    <td class="px-4 py-3">{{ fmt(s.entry_price) }}</td>
                                    <td class="px-4 py-3">
                                        <span v-if="s.status === 'closed_tp'" class="text-green-600 dark:text-green-400">+{{ s.tp_pips }} pip</span>
                                        <span v-else-if="s.status === 'closed_sl'" class="text-red-600 dark:text-red-400">-{{ s.sl_pips }} pip</span>
                                        <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                    </td>
                                    <td class="px-4 py-3"><StatusBadge :status="s.status" /></td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ fmtDate(s.closed_at) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <CopySignalButton :text="signalToText(s)" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="historySignals.links?.length > 3" class="flex flex-wrap gap-1 border-t border-gray-200 px-6 py-4 dark:border-slate-700">
                        <Link
                            v-for="(link, i) in historySignals.links"
                            :key="i"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="rounded px-3 py-1 text-sm"
                            :class="{
                                'bg-indigo-600 text-white': link.active,
                                'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-700': !link.active && link.url,
                                'text-gray-300 dark:text-gray-600': !link.url,
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
