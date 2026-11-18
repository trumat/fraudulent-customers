<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, ApiError, errorMessage } from '@/api';
import CustomerTable from '@/components/CustomerTable.vue';
import ErrorAlert from '@/components/ErrorAlert.vue';
import { formatDateTime } from '@/format';
import type { Scan } from '@/types/models';

const scan = ref<Scan | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);
const scanning = ref(false);
const scanError = ref<string | null>(null);

/** Restores the last scan, so a page refresh keeps the results. */
async function loadLatest(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const latest = await api.getLatestScan();

        // A scan run while this request was pending is newer; keep it.
        if (scan.value === null || latest.id >= scan.value.id) {
            scan.value = latest;
        }
    } catch (error) {
        if (!(error instanceof ApiError && error.status === 404)) {
            loadError.value = errorMessage(error);
        }
    } finally {
        loading.value = false;
    }
}

async function runScan(): Promise<void> {
    scanning.value = true;
    scanError.value = null;

    try {
        scan.value = await api.runScan();
        loadError.value = null;
    } catch (error) {
        // The previous result stays visible.
        scanError.value = errorMessage(error);
    } finally {
        scanning.value = false;
    }
}

onMounted(loadLatest);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Fraud scan</h1>
                <p class="text-sm text-slate-500">Fetches every customer from the customers API and flags fraudulent ones.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-wait disabled:opacity-60"
                :disabled="scanning"
                @click="runScan"
            >
                <span v-if="scanning" class="size-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true" />
                {{ scanning ? 'Scanning…' : 'Run fraud scan' }}
            </button>
        </div>

        <ErrorAlert v-if="scanError" :message="scanError" :retrying="scanning" @retry="runScan" />

        <p v-if="loading" class="text-sm text-slate-500">Loading the last scan…</p>
        <ErrorAlert v-else-if="loadError && !scan" :message="loadError" @retry="loadLatest" />

        <template v-else-if="scan">
            <p class="text-sm text-slate-600">
                Scan #{{ scan.id }} · {{ formatDateTime(scan.scanned_at) }} ·
                {{ scan.total_customers }} customers ·
                <span class="font-semibold text-red-700">{{ scan.fraudulent_count }} fraudulent</span>
            </p>
            <CustomerTable :customers="scan.customers ?? []" />
        </template>

        <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium">No scans yet</p>
            <p class="text-sm text-slate-500">Run your first scan to see the customers here.</p>
        </div>
    </div>
</template>
