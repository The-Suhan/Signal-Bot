<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    activeSignals: { type: Array, required: true },
    historySignals: { type: Object, required: true },
});

const page = usePage();
const directionLabel = (d) => (d === 'buy' ? '🟢 ALIŞ' : '🔴 SATIŞ');
const fmt = (v) => (v === null || v === undefined ? '—' : Number(v).toFixed(3));
// Backend UTC saklıyor; gösterirken açıkça sunucudan gelen displayTimezone'a
// (Asia/Ashgabat) çeviriyoruz — tarayıcının kendi saat dilimine güvenmiyoruz.
const fmtDate = (v) =>
    v ? new Date(v).toLocaleString('tr-TR', { timeZone: page.props.displayTimezone }) : '—';
</script>

<template>
    <Head title="Sinyaller" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Sinyaller
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                <!-- Aktif sinyaller -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b px-6 py-4">
                        <h3 class="font-semibold text-gray-900">Aktif Sinyaller</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Strateji</th>
                                    <th class="px-4 py-3">Yön</th>
                                    <th class="px-4 py-3">Entry</th>
                                    <th class="px-4 py-3">SL</th>
                                    <th class="px-4 py-3">TP</th>
                                    <th class="px-4 py-3">Güven</th>
                                    <th class="px-4 py-3">Durum</th>
                                    <th class="px-4 py-3">Oluşturuldu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="activeSignals.length === 0">
                                    <td colspan="8" class="px-4 py-6 text-center text-gray-400">
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
                                    <td class="px-4 py-3 text-gray-500">{{ fmtDate(s.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Geçmiş sinyaller -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b px-6 py-4">
                        <h3 class="font-semibold text-gray-900">Geçmiş Sinyaller</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Strateji</th>
                                    <th class="px-4 py-3">Yön</th>
                                    <th class="px-4 py-3">Entry</th>
                                    <th class="px-4 py-3">Sonuç</th>
                                    <th class="px-4 py-3">Durum</th>
                                    <th class="px-4 py-3">Kapandı</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-if="historySignals.data.length === 0">
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-400">
                                        Henüz kapanmış sinyal yok.
                                    </td>
                                </tr>
                                <tr v-for="s in historySignals.data" :key="s.id">
                                    <td class="px-4 py-3">{{ s.strategy?.name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ directionLabel(s.direction) }}</td>
                                    <td class="px-4 py-3">{{ fmt(s.entry_price) }}</td>
                                    <td class="px-4 py-3">
                                        <span v-if="s.status === 'closed_tp'" class="text-green-600">+{{ s.tp_pips }} pip</span>
                                        <span v-else-if="s.status === 'closed_sl'" class="text-red-600">-{{ s.sl_pips }} pip</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-4 py-3"><StatusBadge :status="s.status" /></td>
                                    <td class="px-4 py-3 text-gray-500">{{ fmtDate(s.closed_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="historySignals.links?.length > 3" class="flex flex-wrap gap-1 border-t px-6 py-4">
                        <Link
                            v-for="(link, i) in historySignals.links"
                            :key="i"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="rounded px-3 py-1 text-sm"
                            :class="{
                                'bg-indigo-600 text-white': link.active,
                                'text-gray-500 hover:bg-gray-100': !link.active && link.url,
                                'text-gray-300': !link.url,
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
