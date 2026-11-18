<script setup lang="ts">
defineProps<{ message: string; retrying?: boolean }>();
defineEmits<{ retry: [] }>();
</script>

<template>
    <div role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p>{{ message }}</p>
        <button
            type="button"
            class="rounded-md border border-red-300 bg-white px-3 py-1 font-medium hover:bg-red-100 disabled:opacity-50"
            :disabled="retrying"
            @click="$emit('retry')"
        >
            Retry
        </button>
    </div>
</template>
