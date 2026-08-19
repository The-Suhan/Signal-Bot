<script setup>
defineProps({
    status: { type: String, required: true },
});

const config = {
    pending: { label: 'Bekliyor', class: 'bg-amber-100 text-amber-800 dark:bg-amber-400/10 dark:text-amber-400' },
    triggered: { label: 'Tetiklendi', class: 'bg-blue-100 text-blue-800 dark:bg-blue-400/10 dark:text-blue-400' },
    closed_tp: { label: 'TP', class: 'bg-green-100 text-green-800 dark:bg-green-400/10 dark:text-green-400' },
    closed_sl: { label: 'SL', class: 'bg-red-100 text-red-800 dark:bg-red-400/10 dark:text-red-400' },
    expired: { label: 'Süresi Doldu', class: 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400' },
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="config[status]?.class ?? 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400'"
    >
        {{ config[status]?.label ?? status }}
    </span>
</template>
