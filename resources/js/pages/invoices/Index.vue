<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DateRangePicker from '@/components/DateRangePicker.vue';
import { index as invoicesIndex } from '@/routes/invoices/index';
import { dashboard } from '@/routes';

type Invoice = {
    id: number;
    invoiceNumber: string | null;
    customerName: string;
    email: string;
    company: string | null;
    address: string;
    amount: number;
    paymentStatus: 'open' | 'reversed' | null;
    paymentMethod: string | null;
    paymentUrl: string | null;
    invoiceDate: string;
    plugAndPayUrl: string;
};

const props = defineProps<{
    invoices: Invoice[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Openstaande facturen', href: invoicesIndex().url },
        ],
    },
});

// ── Filters ──────────────────────────────────────────────────────────────────
const search = ref('');
const statusFilter = ref<'all' | 'open' | 'reversed'>('all');
const amountSort = ref<'asc' | 'desc' | null>(null);
const dateSort = ref<'asc' | 'desc' | null>(null);
const dateRange = ref({ from: '', to: '' });
const page = ref(1);
const perPage = 25;

// Modal
const activeInvoice = ref<Invoice | null>(null);
function openModal(invoice: Invoice) { activeInvoice.value = invoice; }
function closeModal() { activeInvoice.value = null; }

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

function setStatus(value: typeof statusFilter.value) {
    statusFilter.value = value;
    page.value = 1;
}

function onSearch() { page.value = 1; }
function onDateChange() { page.value = 1; }

watch(dateRange, () => { page.value = 1; }, { deep: true });

// ── Derived list ──────────────────────────────────────────────────────────────
const filtered = computed(() => {
    let list = props.invoices;

    if (statusFilter.value !== 'all') {
        list = list.filter((i) => i.paymentStatus === statusFilter.value);
    }

    if (search.value.trim()) {
        const q = search.value.trim().toLowerCase();
        list = list.filter(
            (i) => i.customerName.toLowerCase().includes(q) || i.email.toLowerCase().includes(q),
        );
    }

    if (dateRange.value.from) {
        list = list.filter((i) => i.invoiceDate >= dateRange.value.from);
    }

    if (dateRange.value.to) {
        list = list.filter((i) => i.invoiceDate <= dateRange.value.to);
    }

    if (amountSort.value) {
        list = [...list].sort((a, b) =>
            amountSort.value === 'asc' ? a.amount - b.amount : b.amount - a.amount,
        );
    } else if (dateSort.value) {
        list = [...list].sort((a, b) =>
            dateSort.value === 'asc'
                ? a.invoiceDate.localeCompare(b.invoiceDate)
                : b.invoiceDate.localeCompare(a.invoiceDate),
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
function formatAmount(amount: number): string {
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount);
}
</script>

<template>
    <Head title="Openstaande facturen" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Openstaande facturen</h1>
            <p class="text-muted-foreground text-sm">Gestorneerde en onbetaalde facturen uit Plug &amp; Pay.</p>
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
                    placeholder="Zoek op naam of e-mail…"
                    class="w-64 rounded-lg border border-sidebar-border/70 bg-transparent py-2 pl-9 pr-3 text-sm outline-none placeholder:text-muted-foreground focus:border-sidebar-border focus:ring-1 focus:ring-sidebar-border dark:border-sidebar-border"
                    @input="onSearch"
                />
            </div>

            <!-- Status filter -->
            <div class="flex items-center rounded-lg border border-sidebar-border/70 p-0.5 dark:border-sidebar-border">
                <button
                    v-for="opt in [
                        { value: 'all', label: 'Alles' },
                        { value: 'open', label: 'Onbetaald' },
                        { value: 'reversed', label: 'Gestorneerd' },
                    ]"
                    :key="opt.value"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="statusFilter === opt.value
                        ? 'bg-sidebar-accent text-foreground'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="setStatus(opt.value as any)"
                >
                    {{ opt.label }}
                </button>
            </div>

            <!-- Datumrange -->
            <DateRangePicker v-model="dateRange" placeholder="Periode kiezen" />

            <!-- Teller -->
            <span class="text-muted-foreground ml-auto text-sm">
                {{ filtered.length }} {{ filtered.length === 1 ? 'factuur' : 'facturen' }}
            </span>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-sidebar-border/70 text-left dark:border-sidebar-border">
                        <th class="px-4 py-3 font-medium">Factuurnummer</th>
                        <th class="px-4 py-3 font-medium">Klant</th>
                        <th class="px-4 py-3 font-medium">E-mail</th>
                        <th class="px-4 py-3 font-medium">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 transition-colors"
                                :class="amountSort ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                                @click="toggleAmountSort"
                            >
                                Bedrag (ex. btw)
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
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 transition-colors"
                                :class="dateSort ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                                @click="toggleDateSort"
                            >
                                Factuurdatum
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
                        <td colspan="7" class="text-muted-foreground px-4 py-8 text-center">Geen facturen gevonden.</td>
                    </tr>
                    <tr
                        v-for="invoice in paginated"
                        :key="invoice.id"
                        class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3 font-mono">
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground flex-shrink-0 transition-colors"
                                    :aria-label="'Details van ' + (invoice.invoiceNumber ?? invoice.id)"
                                    @click="openModal(invoice)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="8" cy="8" r="6.5" />
                                        <path d="M8 7v4M8 5.5v.5" />
                                    </svg>
                                </button>
                                <a
                                    :href="invoice.plugAndPayUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group inline-flex items-start gap-0.5 transition-colors hover:text-blue-600 dark:hover:text-blue-400"
                                >
                                    <span>{{ invoice.invoiceNumber ?? '—' }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" class="mt-0.5 h-2.5 w-2.5 shrink-0 opacity-40 transition-opacity group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4.5 1.5H2a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5V7.5M7 1.5h3.5m0 0v3.5m0-3.5L4.5 7" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ invoice.customerName }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ invoice.email }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ formatAmount(invoice.amount) }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400': invoice.paymentStatus === 'open',
                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': invoice.paymentStatus === 'reversed',
                                }"
                            >
                                {{ invoice.paymentStatus === 'open' ? 'Onbetaald' : 'Gestorneerd' }}
                            </span>
                        </td>
                        <td class="text-muted-foreground px-4 py-3">{{ invoice.invoiceDate }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-lg border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
                                @click="openModal(invoice)"
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
            <div v-if="activeInvoice" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModal">
                <div class="fixed inset-0 bg-black/50" @click="closeModal" />

                <div class="relative z-10 w-full max-w-lg rounded-xl border border-sidebar-border bg-background shadow-xl dark:border-sidebar-border">
                    <div class="flex items-center justify-between border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <div>
                            <h2 class="text-base font-semibold">{{ activeInvoice.invoiceNumber ?? 'Factuur #' + activeInvoice.id }}</h2>
                            <p class="text-muted-foreground text-xs">{{ activeInvoice.invoiceDate }}</p>
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
                            :class="{
                                'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400': activeInvoice.paymentStatus === 'open',
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': activeInvoice.paymentStatus === 'reversed',
                            }"
                        >
                            {{ activeInvoice.paymentStatus === 'open' ? 'Onbetaald' : 'Gestorneerd' }}
                        </span>

                        <div>
                            <h3 class="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wide">Klantgegevens</h3>
                            <dl class="space-y-1.5 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Naam</dt>
                                    <dd class="font-medium">{{ activeInvoice.customerName }}</dd>
                                </div>
                                <div v-if="activeInvoice.company" class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Bedrijf</dt>
                                    <dd class="font-medium">{{ activeInvoice.company }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">E-mail</dt>
                                    <dd><a :href="'mailto:' + activeInvoice.email" class="text-blue-600 hover:underline dark:text-blue-400">{{ activeInvoice.email }}</a></dd>
                                </div>
                                <div v-if="activeInvoice.address" class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Adres</dt>
                                    <dd class="text-right font-medium">{{ activeInvoice.address }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wide">Factuur</h3>
                            <dl class="space-y-1.5 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Bedrag (ex. btw)</dt>
                                    <dd class="font-medium tabular-nums">{{ formatAmount(activeInvoice.amount) }}</dd>
                                </div>
                                <div v-if="activeInvoice.paymentMethod" class="flex justify-between gap-4">
                                    <dt class="text-muted-foreground">Betaalmethode</dt>
                                    <dd class="font-medium capitalize">{{ activeInvoice.paymentMethod }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div v-if="activeInvoice.paymentUrl">
                            <h3 class="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wide">Betaallink</h3>
                            <div class="flex items-center gap-2 rounded-lg border border-sidebar-border/70 bg-sidebar-accent/30 px-3 py-2 dark:border-sidebar-border">
                                <span class="text-muted-foreground min-w-0 flex-1 truncate font-mono text-xs">{{ activeInvoice.paymentUrl }}</span>
                                <a :href="activeInvoice.paymentUrl" target="_blank" rel="noopener noreferrer" class="text-muted-foreground hover:text-foreground flex-shrink-0 transition-colors" aria-label="Betaallink openen">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4.5 1.5H2a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5V7.5M7 1.5h3.5m0 0v3.5m0-3.5L4.5 7" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <a :href="activeInvoice.plugAndPayUrl" target="_blank" rel="noopener noreferrer" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm transition-colors">
                            Bekijk in Plug &amp; Pay
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4.5 1.5H2a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h8a.5.5 0 0 0 .5-.5V7.5M7 1.5h3.5m0 0v3.5m0-3.5L4.5 7" />
                            </svg>
                        </a>
                        <button type="button" class="inline-flex items-center rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background transition-opacity hover:opacity-80">
                            Actie starten
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
