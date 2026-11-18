<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, errorMessage } from '@/api';
import CustomerTable from '@/components/CustomerTable.vue';
import ErrorAlert from '@/components/ErrorAlert.vue';
import { formatDateTime } from '@/format';
import type { Scan } from '@/types/models';

interface ScanDetail {
    scan: Scan | null;
    error: string | null;
}

const scans = ref<Scan[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const expandedId = ref<number | null>(null);
/** Scans with their customers, loaded on first expand. */
const details = ref<Record<number, ScanDetail>>({});

async function loadScans(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        scans.value = await api.listScans();
    } catch (error) {
        loadError.value = errorMessage(error);
    } finally {
        loading.value = false;
    }
}

async function loadDetail(id: number): Promise<void> {
    details.value[id] = { scan: null, error: null };

    try {
        details.value[id] = { scan: await api.getScan(id), error: null };
    } catch (error) {
        details.value[id] = { scan: null, error: errorMessage(error) };
    }
}

function toggle(id: number): void {
    if (expandedId.value === id) {
        expandedId.value = null;

        return;
    }

    expandedId.value = id;

    const detail = details.value[id];

    if (detail === undefined || detail.error !== null) {
        void loadDetail(id);
    }
}

onMounted(loadScans);
</script>

<template>
    <div class="space-y-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Scan history</h1>
            <p class="text-sm text-slate-500">Every completed scan. Expand one to see its customers.</p>
        </div>

        <p v-if="loading" class="text-sm text-slate-500">Loading scans…</p>
        <ErrorAlert v-else-if="loadError" :message="loadError" @retry="loadScans" />

        <div v-else-if="scans.length === 0" class="rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="font-medium">No scans yet</p>
            <p class="text-sm text-slate-500">
                <RouterLink to="/" class="underline">Run a scan</RouterLink> and it will show up here.
            </p>
        </div>

        <ul v-else class="divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white">
            <li v-for="scan in scans" :key="scan.id">
                <button
                    type="button"
                    class="flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-left text-sm hover:bg-slate-50"
                    :aria-expanded="expandedId === scan.id"
                    @click="toggle(scan.id)"
                >
                    <span class="w-3 text-slate-400" aria-hidden="true">{{ expandedId === scan.id ? '▾' : '▸' }}</span>
                    <span class="font-medium">Scan #{{ scan.id }}</span>
                    <span class="text-slate-600">{{ formatDateTime(scan.scanned_at) }}</span>
                    <span class="ml-auto text-slate-600">{{ scan.total_customers }} customers</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="scan.fraudulent_count > 0 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'"
                    >
                        {{ scan.fraudulent_count }} fraudulent
                    </span>
                </button>

                <div v-if="expandedId === scan.id" class="border-t border-slate-200 bg-slate-50 p-4">
                    <ErrorAlert
                        v-if="details[scan.id]?.error"
                        :message="details[scan.id]!.error!"
                        @retry="loadDetail(scan.id)"
                    />
                    <CustomerTable v-else-if="details[scan.id]?.scan" :customers="details[scan.id]!.scan!.customers ?? []" />
                    <p v-else class="text-sm text-slate-500">Loading customers…</p>
                </div>
            </li>
        </ul>
    </div>
</template>
