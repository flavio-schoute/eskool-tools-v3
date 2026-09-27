<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DateRangePicker from '@/components/DateRangePicker.vue';
import { index as invoicesIndex } from '@/routes/invoices/index';
import { dashboard } from '@/routes';

type Transaction = {
    id: string;
    type: 'chargeback' | 'refund';
    paymentId: string;
    description: string | null;
    customerName: string | null;
    email: string | null;
    amount: number;
    currency: string;
    reason: string | null;
    status: string | null;
    paymentMethod: string | null;
    date: string;
    dashboardUrl: string | null;
};

const props = defineProps<{
    transactions: Transaction[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Chargebacks & refunds', href: invoicesIndex().url },
        ],
    },
});

// ── Filters ──────────────────────────────────────────────────────────────────
const search = ref('');
const typeFilter = ref<'all' | Transaction['type']>('all');
const amountSort = ref<'asc' | 'desc' | null>(null);
const dateSort = ref<'asc' | 'desc' | null>(null);
const dateRange = ref({ from: '', to: '' });
const page = ref(1);
const perPage = 25;

// Modal
const activeTransaction = ref<Transaction | null>(null);
function openModal(transaction: Transaction) { activeTransaction.value = transaction; }
function closeModal() { activeTransaction.value = null; }

function toggleAmountSort() {
    amountSort.value = amountSort.value === 'asc' ? 'desc' : amountSort.value === 'desc' ? null : 'asc';
    if (amountSort.value) dateSort.value = null;
    page.value = 1;
}

function toggleDateSort() {
    dateSort.value = dateSort.value === 'asc' ? 'desc' : dateSort.value === 'desc' ? null : 'asc';
    if (dateSort.value) amountSort.value = null;
    page.value = 1;
}

function setType(value: typeof typeFilter.value) {
    typeFilter.value = value;
    page.value = 1;
}

function onSearch() { page.value = 1; }
function onDateChange() { page.value = 1; }

watch(dateRange, () => { page.value = 1; }, { deep: true });

// ── Derived list ──────────────────────────────────────────────────────────────
const filtered = computed(() => {
    let list = props.transactions;

    if (typeFilter.value !== 'all') {
        list = list.filter((t) => t.type === typeFilter.value);
    }

    if (search.value.trim()) {
        const q = search.value.trim().toLowerCase();
        list = list.filter(
            (t) => [t.customerName, t.email, t.description, t.paymentId].some((value) => value?.toLowerCase().includes(q)),
        );
    }

    if (dateRange.value.from) {
        list = list.filter((t) => t.date >= dateRange.value.from);
    }

    if (dateRange.value.to) {
        list = list.filter((t) => t.date <= dateRange.value.to);
    }

    if (amountSort.value) {
        list = [...list].sort((a, b) =>
            amountSort.value === 'asc' ? a.amount - b.amount : b.amount - a.amount,
        );
    } else if (dateSort.value) {
        list = [...list].sort((a, b) =>
            dateSort.value === 'asc'
                ? a.date.localeCompare(b.date)
                : b.date.localeCompare(a.date),
        );
    }

    return list;
});

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));

const paginated = computed(() => {
    const p = Math.min(page.value, totalPages.value);
    return filtered.value.slice((p - 1) * perPage, p * perPage);
});

const from = computed(() => (Math.min(page.value, totalPages.value) - 1) * perPage + 1);
const to = computed(() => Math.min(Math.min(page.value, totalPages.value) * perPage, filtered.value.length));

const pageNumbers = computed(() => {
    const current = Math.min(page.value, totalPages.value);
    const last = totalPages.value;
    const delta = 2;
    const range: (number | '...')[] = [];

    const start = Math.max(2, current - delta);
    const end = Math.min(last - 1, current + delta);

    range.push(1);
    if (start > 2) range.push('...');
    for (let i = start; i <= end; i++) range.push(i);
    if (end < last - 1) range.push('...');
    if (last > 1) range.push(last);

    return range;
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatAmount(amount: number, currency = 'EUR'): string {
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency }).format(amount);
}

const typeLabels: Record<Transaction['type'], string> = {
    chargeback: 'Chargeback',
    refund: 'Refund',
};

const typeClasses: Record<Transaction['type'], string> = {
    chargeback: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    refund: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
};
</script>

<template>
    <Head title="Chargebacks & refunds" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Chargebacks &amp; refunds</h1>
            <p class="text-muted-foreground text-sm">Chargebacks en terugbetalingen uit Mollie.</p>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Zoekbalk -->
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="text-muted-foreground pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                </svg>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Zoek op naam, e-mail of omschrijving…"
                    class="w-64 rounded-lg border border-sidebar-border/70 bg-transparent py-2 pl-9 pr-3 text-sm outline-none placeholder:text-muted-foreground focus:border-sidebar-border focus:ring-1 focus:ring-sidebar-border dark:border-sidebar-border"
                    @input="onSearch"
                />
            </div>

            <!-- Status filter -->
            <div class="flex items-center rounded-lg border border-sidebar-border/70 p-0.5 dark:border-sidebar-border">
                <button
                    v-for="opt in [
                        { value: 'all', label: 'Alles' },
                        { value: 'chargeback', label: 'Chargebacks' },
                        { value: 'refund', label: 'Refunds' },
                    ]"
                    :key="opt.value"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="typeFilter === opt.value
                        ? 'bg-sidebar-accent text-foreground'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="setType(opt.value as any)"
                >
                    {{ opt.label }}
                </button>
            </div>

            <!-- Datumrange -->
            <DateRangePicker v-model="dateRange" placeholder="Periode kiezen" />

            <!-- Teller -->
            <span class="text-muted-foreground ml-auto text-sm">
                {{ filtered.length }} {{ filtered.length === 1 ? 'transactie' : 'transacties' }}
            </span>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-sidebar-border/70 text-left dark:border-sidebar-border">
                        <th class="px-4 py-3 font-medium">Omschrijving</th>
                        <th class="px-4 py-3 font-medium">Klant</th>
                        <th class="px-4 py-3 font-medium">E-mail</th>
                        <th class="px-4 py-3 font-medium">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 transition-colors"
                                :class="amountSort ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                                @click="toggleAmountSort"
                            >
                                Bedrag
                                <span class="flex flex-col gap-px leading-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 5" class="h-2 w-2" fill="currentColor" :class="amountSort === 'asc' ? 'opacity-100' : 'opacity-25'">
                                        <path d="M4 0 8 5H0z" />
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 5" class="h-2 w-2 rotate-180" fill="currentColor" :class="amountSort === 'desc' ? 'opacity-100' : 'opacity-25'">
                                        <path d="M4 0 8 5H0z" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 transition-colors"
                                :class="dateSort ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                                @click="toggleDateSort"
                            >
                                Datum
                                <span class="flex flex-col gap-px leading-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 5" class="h-2 w-2" fill="currentColor" :class="dateSort === 'asc' ? 'opacity-100' : 'opacity-25'">
                                        <path d="M4 0 8 5H0z" />
                                    </svg>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 5" class="h-2 w-2 rotate-180" fill="currentColor" :class="dateSort === 'desc' ? 'opacity-100' : 'opacity-25'">
                                        <path d="M4 0 8 5H0z" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="paginated.length === 0">
                        <td colspan="7" class="text-muted-foreground px-4 py-8 text-center">Geen chargebacks of refunds gevonden.</td>
                    </tr>
                    <tr
                        v-for="transaction in paginated"
                        :key="transaction.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground flex-shrink-0 transition-colors"
                                    :aria-label="'Details van ' + transaction.id"
                                    @click="openModal(transaction)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="8" cy="8" r="6.5" />
                                        <path d="M8 7v4M8 5.5v.5" />
                                    </svg>
                                </button>
                                <a
                                    v-if="transaction.dashboardUrl"
                                    :href="transaction.dashboardUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group inline-flex items-start gap-0.5 transition-colors hover:text-blue-600 dark:hover:text-blue-400"
                                >
                                    <span>{{ transaction.description ?? transaction.paymentId }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" class="mt-0.5 h-2.5 w-2.5 shrink-0 opacity-40 transition-opacity group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4.5 1.5H2a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5V7.5M7 1.5h3.5m0 0v3.5m0-3.5L4.5 7" />
                                    </svg>
                                </a>
                                <span v-else>{{ transaction.description ?? transaction.paymentId }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ transaction.customerName ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ transaction.email ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ formatAmount(transaction.amount, transaction.currency) }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="typeClasses[transaction.type]"
                            >
                                {{ typeLabels[transaction.type] }}
                            </span>
                        </td>
                        <td class="text-muted-foreground px-4 py-3">{{ transaction.date }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
                                @click="openModal(transaction)"
                            >
                                Actie starten
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginatie -->
            <div
                v-if="filtered.length > 0"
                class="flex items-center justify-between border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <span class="text-muted-foreground text-sm">
                    {{ from }}–{{ to }} van {{ filtered.length }}
                </span>

                <nav v-if="totalPages > 1" class="flex items-center gap-1" aria-label="Paginatie">
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-sidebar-border/70 text-sm transition-colors dark:border-sidebar-border"
                        :class="page > 1 ? 'hover:bg-sidebar-accent' : 'opacity-40 cursor-not-allowed'"
                        :disabled="page <= 1"
                        @click="page > 1 && page--"
                    >&larr;</button>

                    <template v-for="(num, i) in pageNumbers" :key="i">
                        <span v-if="num === '...'" class="text-muted-foreground inline-flex h-8 w-8 items-center justify-center text-sm">&hellip;</span>
                        <button
                            v-else
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border text-sm transition-colors"
                            :class="num === Math.min(page, totalPages)
                                ? 'bg-sidebar-accent border-sidebar-border font-semibold'
                                : 'border-sidebar-border/70 hover:bg-sidebar-accent dark:border-sidebar-border'"
                            @click="page = num as number"
                        >{{ num }}</button>
                    </template>

                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-sidebar-border/70 text-sm transition-colors dark:border-sidebar-border"
                        :class="page < totalPages ? 'hover:bg-sidebar-accent' : 'opacity-40 cursor-not-allowed'"
                        :disabled="page >= totalPages"
                        @click="page < totalPages && page++"
                    >&rarr;</button>
                </nav>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <Teleport defer to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="activeTransaction" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModal">
                <div class="fixed inset-0 bg-black/50" @click="closeModal" />

                <div class="relative z-10 w-full max-w-lg rounded-xl border border-sidebar-border bg-background shadow-xl dark:border-sidebar-border">
                    <div class="flex items-center justify-between border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <div>
                            <h2 class="text-base font-semibold">{{ activeTransaction.description ?? activeTransaction.paymentId }}</h2>
                            <p class="text-muted-foreground text-xs">{{ activeTransaction.date }}</p>
                        </div>
                        <button type="button" class="text-muted-foreground hover:text-foreground transition-colors" aria-label="Sluiten" @click="closeModal">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-5 px-6 py-5">
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="typeClasses[activeTransaction.type]"
                        >
                            {{ typeLabels[activeTransaction.type] }}
                        </span>

                        <div>
                            <h3 class="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wide">Klantgegevens</h3>
                            <dl class="space-y-1.5 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Naam</dt>
                                    <dd class="font-medium">{{ activeTransaction.customerName ?? '—' }}</dd>
                                </div>
                                <div v-if="activeTransaction.email" class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">E-mail</dt>
                                    <dd><a :href="'mailto:' + activeTransaction.email" class="text-blue-600 hover:underline dark:text-blue-400">{{ activeTransaction.email }}</a></dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wide">{{ typeLabels[activeTransaction.type] }}</h3>
                            <dl class="space-y-1.5 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Bedrag</dt>
                                    <dd class="font-medium tabular-nums">{{ formatAmount(activeTransaction.amount, activeTransaction.currency) }}</dd>
                                </div>
                                <div v-if="activeTransaction.reason" class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Reden</dt>
                                    <dd class="text-right font-medium">{{ activeTransaction.reason }}</dd>
                                </div>
                                <div v-if="activeTransaction.status" class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Status</dt>
                                    <dd class="font-medium capitalize">{{ activeTransaction.status }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Betaling</dt>
                                    <dd class="font-mono font-medium">{{ activeTransaction.paymentId }}</dd>
                                </div>
                                <div v-if="activeTransaction.paymentMethod" class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Betaalmethode</dt>
                                    <dd class="font-medium capitalize">{{ activeTransaction.paymentMethod }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <a v-if="activeTransaction.dashboardUrl" :href="activeTransaction.dashboardUrl" target="_blank" rel="noopener noreferrer" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm transition-colors">
                            Bekijk in Mollie
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4.5 1.5H2a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5V7.5M7 1.5h3.5m0 0v3.5m0-3.5L4.5 7" />
                            </svg>
                        </a>
                        <span v-else />
                        <button type="button" class="inline-flex items-center rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background transition-opacity hover:opacity-80">
                            Actie starten
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
