<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';

const links = [
    { to: '/', label: 'Scan' },
    { to: '/scans', label: 'History' },
];
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <RouterLink to="/" class="text-lg font-semibold tracking-tight">Fraud Scanner</RouterLink>
                <nav class="flex gap-1 text-sm font-medium">
                    <RouterLink
                        v-for="link in links"
                        :key="link.to"
                        :to="link.to"
                        class="rounded-md px-3 py-1.5 text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                        exact-active-class="bg-slate-900 text-white hover:bg-slate-900 hover:text-white"
                    >
                        {{ link.label }}
                    </RouterLink>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6">
            <RouterView />
        </main>
    </div>
</template>
