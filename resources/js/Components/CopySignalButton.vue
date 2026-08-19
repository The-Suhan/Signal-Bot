<script setup>
import { ref } from 'vue';

const props = defineProps({
    text: { type: String, required: true },
});

const copied = ref(false);
let resetTimer = null;

async function copy() {
    try {
        await navigator.clipboard.writeText(props.text);
        copied.value = true;
        clearTimeout(resetTimer);
        resetTimer = setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch (e) {
        // Panoya erişim reddedildiyse (izin vb.) sessizce geç — buton
        // tekrar denenebilir durumda kalır.
    }
}
</script>

<template>
    <button
        type="button"
        @click="copy"
        class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-slate-700 dark:hover:text-gray-200"
        title="Panoya kopyala"
    >
        <template v-if="copied">
            <span class="text-green-600 dark:text-green-400">Kopyalandı ✓</span>
        </template>
        <template v-else>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <span>Kopyala</span>
        </template>
    </button>
</template>
