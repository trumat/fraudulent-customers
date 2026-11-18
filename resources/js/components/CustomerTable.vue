<script setup lang="ts">
import { ref } from 'vue';
import type { Customer } from '@/types/models';

defineProps<{ customers: Customer[] }>();

const expanded = ref(new Set<number>());

function toggle(id: number): void {
    if (!expanded.value.delete(id)) {
        expanded.value.add(id);
    }
}

/** The recorded fields that aren't already columns of the table. */
function details(customer: Customer): [string, string | number | null][] {
    return [
        ['External ID', customer.external_id],
        ['BSN', customer.bsn],
        ['First name', customer.first_name],
        ['Last name', customer.last_name],
        ['Email', customer.email],
        ['Tag', customer.tag],
        ['Street', customer.street],
        ['Postcode', customer.postcode],
        ['City', customer.city],
        ['Products', customer.products.length > 0 ? customer.products.join(', ') : null],
        ['Last invoice', customer.last_invoice_date],
        ['Last login', customer.last_login_at],
    ];
}
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2 font-medium">Customer</th>
                    <th class="px-3 py-2 font-medium">Phone</th>
                    <th class="px-3 py-2 font-medium">IP address</th>
                    <th class="px-3 py-2 font-medium">IBAN</th>
                    <th class="px-3 py-2 font-medium">Date of birth</th>
                    <th class="px-3 py-2 font-medium">Result</th>
                </tr>
            </thead>
            <tbody v-for="customer in customers" :key="customer.id" class="border-t border-slate-200">
                <tr
                    class="cursor-pointer align-top"
                    :class="customer.is_fraudulent ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-slate-50'"
                    @click="toggle(customer.id)"
                >
                    <td class="px-3 py-2">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 text-left font-medium"
                            :aria-expanded="expanded.has(customer.id)"
                        >
                            <span class="w-3 text-slate-400" aria-hidden="true">{{ expanded.has(customer.id) ? '▾' : '▸' }}</span>
                            {{ customer.full_name }}
                        </button>
                    </td>
                    <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ customer.phone_number ?? '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ customer.ip_address ?? '—' }}</td>
                    <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ customer.iban ?? '—' }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ customer.date_of_birth ?? '—' }}</td>
                    <td class="px-3 py-2">
                        <span
                            v-if="customer.is_fraudulent"
                            class="inline-block rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white"
                        >
                            Fraudulent
                        </span>
                        <span v-else class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                            Clean
                        </span>
                        <ul v-if="customer.fraud_reasons.length > 0" class="mt-1 list-disc space-y-0.5 pl-4 text-xs text-red-800">
                            <li v-for="reason in customer.fraud_reasons" :key="reason">{{ reason }}</li>
                        </ul>
                    </td>
                </tr>
                <tr v-if="expanded.has(customer.id)" :class="customer.is_fraudulent ? 'bg-red-50' : 'bg-slate-50'">
                    <td colspan="6" class="px-3 pt-1 pb-3 pl-8">
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-1 text-xs sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="[label, value] in details(customer)" :key="label" class="flex gap-2">
                                <dt class="w-24 shrink-0 text-slate-500">{{ label }}</dt>
                                <dd class="break-all">{{ value ?? '—' }}</dd>
                            </div>
                        </dl>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
