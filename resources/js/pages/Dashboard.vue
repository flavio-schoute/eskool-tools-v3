<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DateRangePicker from '@/components/DateRangePicker.vue';
import { dashboard } from '@/routes';
import invoices from '@/routes/invoices/index';

// ── Types ─────────────────────────────────────────────────────────────────────
type TransactionStat = {
    type: 'chargeback' | 'refund';
    amount: number;
    date: string;
};

type TransactionStatsPayload = {
    transactions: TransactionStat[];
} | null;

type Payment = {
    amount: number;
    paidAt: string;
};

type CashStatsPayload = {
    payments: Payment[];
} | null;

type Greeting = {
    firstName: string;
    isNewUser: boolean;
    quote: { text: string; author: string };
};

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps<{
    greeting: Greeting;
    transactionStats: TransactionStatsPayload;
    cashStats: CashStatsPayload;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

// ── Period filter ─────────────────────────────────────────────────────────────
type Preset = 'week' | 'month' | 'quarter' | 'year' | 'all' | 'custom';
const preset = ref<Preset>('month');
const customRange = ref({ from: '', to: '' });

function toISO(d: Date): string {
    return d.toISOString().slice(0, 10);
}

const dateRange = computed<{ from: string; to: string }>(() => {
    const now = new Date();
    const today = toISO(now);

    if (preset.value === 'week') {
        const day = now.getDay() === 0 ? 6 : now.getDay() - 1;
        const mon = new Date(now);
        mon.setDate(now.getDate() - day);
        return { from: toISO(mon), to: today };
    }
    if (preset.value === 'month') {
        return { from: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`, to: today };
    }
    if (preset.value === 'quarter') {
        const q = Math.floor(now.getMonth() / 3);
        const from = new Date(now.getFullYear(), q * 3, 1);
        return { from: toISO(from), to: today };
    }
    if (preset.value === 'year') {
        return { from: `${now.getFullYear()}-01-01`, to: today };
    }
    if (preset.value === 'custom') {
        return customRange.value;
    }
    return { from: '', to: '' };
});

function setPreset(p: Preset) {
    preset.value = p;
}

function onCustomRange(val: { from: string; to: string }) {
    customRange.value = val;
    preset.value = 'custom';
}

const presets: { key: Preset; label: string }[] = [
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Maand' },
    { key: 'quarter', label: 'Kwartaal' },
    { key: 'year', label: 'Jaar' },
    { key: 'all', label: 'Alles' },
];

// ── Computed stats ─────────────────────────────────────────────────────────────
const filteredTransactions = computed<TransactionStat[]>(() => {
    if (!props.transactionStats) return [];
    const { from, to } = dateRange.value;
    return props.transactionStats.transactions.filter((transaction) => {
        if (from && transaction.date < from) return false;
        if (to && transaction.date > to) return false;
        return true;
    });
});

const refunds = computed(() => filteredTransactions.value.filter((t) => t.type === 'refund'));
const chargebacks = computed(() => filteredTransactions.value.filter((t) => t.type === 'chargeback'));

const refundAmount = computed(() => refunds.value.reduce((s, t) => s + t.amount, 0));
const chargebackAmount = computed(() => chargebacks.value.reduce((s, t) => s + t.amount, 0));
const totalLost = computed(() => refundAmount.value + chargebackAmount.value);
const avgTransactionAmount = computed(() =>
    filteredTransactions.value.length ? totalLost.value / filteredTransactions.value.length : 0,
);

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(amount);
}

// ── Cash binnengehaald ────────────────────────────────────────────────────────
const filteredPayments = computed<Payment[]>(() => {
    if (!props.cashStats) return [];
    const { from, to } = dateRange.value;
    return props.cashStats.payments.filter((p) => {
        if (from && p.paidAt < from) return false;
        if (to && p.paidAt > to) return false;
        return true;
    });
});

const cashCollected = computed(() => filteredPayments.value.reduce((s, p) => s + p.amount, 0));

// ── Pipeline stages (placeholder — CRM data) ──────────────────────────────────
const pipeline = [
    { label: 'Te verwerken',      count: 11, color: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',      dot: 'bg-slate-400' },
    { label: 'Reminder 1',        count: 23, color: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300', dot: 'bg-yellow-400' },
    { label: 'Reminder 2',        count: 8,  color: 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300', dot: 'bg-orange-400' },
    { label: 'Wacht op betaling', count: 7,  color: 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',         dot: 'bg-blue-400' },
    { label: 'Disputes',          count: 2,  color: 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',             dot: 'bg-red-500' },
    { label: 'Ready voor incasso',count: 4,  color: 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300', dot: 'bg-purple-500' },
    { label: 'Incasso',           count: 23, color: 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300', dot: 'bg-violet-500' },
    { label: 'Afgerond',          count: 68, color: 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',     dot: 'bg-green-500' },
];

const totalPipeline = computed(() => pipeline.reduce((s, p) => s + p.count, 0));
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 pb-8">

        <div class="flex items-center justify-between gap-4 rounded-2xl border border-sidebar-border/70 bg-gradient-to-r from-sidebar-accent/60 to-transparent px-5 py-3 dark:border-sidebar-border">
            <p class="text-sm font-semibold whitespace-nowrap">
                <template v-if="greeting.isNewUser">🎉 Welkom, wat goed dat je er bent, {{ greeting.firstName }}! &nbsp;<span class="font-normal text-muted-foreground">Laten we beginnen!</span></template>
                <template v-else>👋 Hey {{ greeting.firstName }}, welkom terug! &nbsp;<span class="font-normal text-muted-foreground">Fijne werkdag!</span></template>
            </p>
            <p class="text-muted-foreground/70 hidden truncate text-right text-xs italic sm:block">
                "{{ greeting.quote.text }}" &mdash; {{ greeting.quote.author }}
            </p>
        </div>

        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <p class="text-muted-foreground text-sm">Overzicht debiteurenbeheer, chargebacks &amp; refunds</p>
            </div>

            <!-- Period filter -->
            <div class="flex items-center gap-2">
                <div class="flex items-center rounded-lg border border-sidebar-border/70 p-0.5 dark:border-sidebar-border">
                    <button
                        v-for="p in presets"
                        :key="p.key"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="preset === p.key
                            ? 'bg-sidebar-accent text-foreground'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="setPreset(p.key)"
                    >
                        {{ p.label }}
                    </button>
                </div>
                <DateRangePicker
                    :model-value="preset === 'custom' ? customRange : { from: '', to: '' }"
                    placeholder="Eigen periode"
                    @update:model-value="onCustomRange"
                />
            </div>
        </div>

        <!-- ── KPI cards ───────────────────────────────────────────────────── -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

            <!-- Cash binnengehaald — coming soon -->
            <div class="relative overflow-hidden rounded-2xl border border-dashed border-sidebar-border/70 bg-gradient-to-br from-green-50/40 to-emerald-50/40 p-5 dark:border-sidebar-border dark:from-green-950/10 dark:to-emerald-950/10">
                <!-- blur overlay -->
                <div class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-2xl backdrop-blur-[2px]">
                    <div class="flex items-center gap-2 rounded-full border border-sidebar-border/60 bg-white/80 px-3 py-1.5 shadow-sm dark:bg-sidebar/80">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3.5 w-3.5 text-muted-foreground">
                            <path fill-rule="evenodd" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7V4.5A3.5 3.5 0 0 0 8 1Zm2 6V4.5a2 2 0 1 0-4 0V7h4Z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs font-semibold text-foreground">Coming soon</span>
                    </div>
                </div>
                <!-- ghost content -->
                <div class="mb-3 flex items-center justify-between opacity-30">
                    <span class="text-sm font-medium text-green-700 dark:text-green-400">Cash binnengehaald</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-green-600 dark:text-green-400">
                            <path d="M10.75 10.818v2.614A3.13 3.13 0 0 0 11.888 13c.482-.315.612-.648.612-.875 0-.227-.13-.56-.612-.875a3.13 3.13 0 0 0-1.138-.432ZM8.33 8.62c.053.055.115.11.184.164.208.16.46.284.736.363V6.603a2.45 2.45 0 0 0-.35.13c-.14.065-.27.143-.386.233-.365.284-.594.551-.594.846 0 .295.23.562.594.846Z" />
                            <path fill-rule="evenodd" d="M9.99 2C5.578 2 2 5.58 2 10s3.578 8 7.99 8C14.418 18 18 14.42 18 10S14.418 2 9.99 2Zm.01 1.5c3.58 0 6.5 2.914 6.5 6.5 0 3.586-2.92 6.5-6.5 6.5A6.505 6.505 0 0 1 3.5 10c0-3.586 2.914-6.5 6.5-6.5ZM10 6.75a.75.75 0 0 1 .75.75v.338a3.63 3.63 0 0 1 1.288.535c.505.33.962.874.962 1.627s-.457 1.296-.962 1.627a3.63 3.63 0 0 1-1.288.535v1.601a2.494 2.494 0 0 0 .547-.22c.358-.196.673-.499.773-.949a.75.75 0 0 1 1.46.348c-.197.828-.77 1.43-1.396 1.774a3.99 3.99 0 0 1-1.384.426v.35a.75.75 0 0 1-1.5 0v-.35a3.99 3.99 0 0 1-1.384-.426c-.626-.344-1.199-.946-1.396-1.774a.75.75 0 0 1 1.46-.348c.1.45.415.753.773.949.175.096.36.169.547.22V11.15a3.629 3.629 0 0 1-1.288-.535C6.957 10.296 6.5 9.753 6.5 9s.457-1.296.962-1.627A3.629 3.629 0 0 1 8.75 6.838V7.5a.75.75 0 0 1-1.5 0v-.75A.75.75 0 0 1 7.998 6h.002Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <div class="opacity-30">
                    <div class="text-2xl font-bold text-green-700 dark:text-green-400">€ –</div>
                    <div class="mt-0.5 text-xs text-green-600/70 dark:text-green-500">betalingen (incl. btw)</div>
                </div>
            </div>

            <!-- Refunds -->
            <div class="relative overflow-hidden rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-orange-50 to-amber-50 p-5 dark:border-sidebar-border dark:from-orange-950/30 dark:to-amber-950/30">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-sm font-medium text-orange-700 dark:text-orange-400">Refunds</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-orange-600 dark:text-orange-400">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2V6h10a2 2 0 0 0-2-2H4Zm2 6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-4Zm6 4a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <template v-if="transactionStats">
                    <div class="text-2xl font-bold text-orange-700 dark:text-orange-400">{{ formatCurrency(refundAmount) }}</div>
                    <div class="mt-0.5 text-xs text-orange-600/70 dark:text-orange-500">{{ refunds.length }} {{ refunds.length === 1 ? 'refund' : 'refunds' }}</div>
                </template>
                <template v-else>
                    <div class="h-8 w-28 animate-pulse rounded-md bg-orange-100 dark:bg-orange-900/40" />
                    <div class="mt-1.5 h-3 w-16 animate-pulse rounded bg-orange-100 dark:bg-orange-900/40" />
                </template>
            </div>

            <!-- Chargebacks -->
            <div class="relative overflow-hidden rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-red-50 to-rose-50 p-5 dark:border-sidebar-border dark:from-red-950/30 dark:to-rose-950/30">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-sm font-medium text-red-700 dark:text-red-400">Chargebacks</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-red-600 dark:text-red-400">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <template v-if="transactionStats">
                    <div class="text-2xl font-bold text-red-700 dark:text-red-400">{{ formatCurrency(chargebackAmount) }}</div>
                    <div class="mt-0.5 text-xs text-red-600/70 dark:text-red-500">{{ chargebacks.length }} {{ chargebacks.length === 1 ? 'chargeback' : 'chargebacks' }}</div>
                </template>
                <template v-else>
                    <div class="h-8 w-28 animate-pulse rounded-md bg-red-100 dark:bg-red-900/40" />
                    <div class="mt-1.5 h-3 w-16 animate-pulse rounded bg-red-100 dark:bg-red-900/40" />
                </template>
            </div>

            <!-- Totaal chargebacks & refunds -->
            <div class="relative overflow-hidden rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-blue-50 to-indigo-50 p-5 dark:border-sidebar-border dark:from-blue-950/30 dark:to-indigo-950/30">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-400">Totaal teruggeboekt</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/50">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-blue-600 dark:text-blue-400">
                            <path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.24a1 1 0 0 0 0 1.962l1.192.24a1 1 0 0 1 .785.785l.24 1.192a1 1 0 0 0 1.962 0l.24-1.192a1 1 0 0 1 .785-.785l1.192-.24a1 1 0 0 0 0-1.962l-1.192-.24a1 1 0 0 1-.785-.785l-.24-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.684a1 1 0 0 1 .633.632l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.683a1 1 0 0 0 0-1.898l-2.051-.683a1 1 0 0 1-.633-.633L6.95 5.684ZM13.949 13.684a1 1 0 0 0-1.898 0l-.184.551a1 1 0 0 1-.632.633l-.551.183a1 1 0 0 0 0 1.898l.551.183a1 1 0 0 1 .633.633l.183.551a1 1 0 0 0 1.898 0l.184-.551a1 1 0 0 1 .632-.633l.551-.183a1 1 0 0 0 0-1.898l-.551-.184a1 1 0 0 1-.633-.632l-.183-.551Z" />
                        </svg>
                    </div>
                </div>
                <template v-if="transactionStats">
                    <div class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ formatCurrency(totalLost) }}</div>
                    <div class="mt-0.5 text-xs text-blue-600/70 dark:text-blue-500">{{ filteredTransactions.length }} {{ filteredTransactions.length === 1 ? 'transactie' : 'transacties' }} totaal</div>
                </template>
                <template v-else>
                    <div class="h-8 w-28 animate-pulse rounded-md bg-blue-100 dark:bg-blue-900/40" />
                    <div class="mt-1.5 h-3 w-16 animate-pulse rounded bg-blue-100 dark:bg-blue-900/40" />
                </template>
            </div>
        </div>

        <!-- ── Pipeline ────────────────────────────────────────────────────── -->
        <div class="rounded-2xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold">Debiteurenbeheer pipeline</h2>
                    <p class="text-muted-foreground text-xs">{{ totalPipeline }} dossiers in behandeling &nbsp;·&nbsp; <span class="text-yellow-600 dark:text-yellow-400">Placeholder — CRM koppeling volgt</span></p>
                </div>
                <Link
                    :href="invoices.index().url"
                    class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm transition-colors"
                >
                    Alle chargebacks &amp; refunds
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3.5 w-3.5">
                        <path fill-rule="evenodd" d="M6.22 4.22a.75.75 0 0 1 1.06 0l3.25 3.25a.75.75 0 0 1 0 1.06l-3.25 3.25a.75.75 0 0 1-1.06-1.06L8.94 8 6.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </Link>
            </div>

            <!-- Progress bar -->
            <div class="mb-5 flex h-2 w-full overflow-hidden rounded-full">
                <div v-for="(stage, i) in pipeline" :key="i"
                    class="transition-all"
                    :class="[
                        i === 0 ? 'bg-slate-400' :
                        i === 1 ? 'bg-yellow-400' :
                        i === 2 ? 'bg-orange-400' :
                        i === 3 ? 'bg-blue-400' :
                        i === 4 ? 'bg-red-500' :
                        i === 5 ? 'bg-purple-500' :
                        i === 6 ? 'bg-violet-500' : 'bg-green-500'
                    ]"
                    :style="{ width: (stage.count / totalPipeline * 100) + '%' }"
                />
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
                <div
                    v-for="stage in pipeline"
                    :key="stage.label"
                    class="rounded-xl p-3 transition-all hover:scale-105"
                    :class="stage.color"
                >
                    <div class="flex items-center gap-1.5 mb-2">
                        <div class="h-2 w-2 rounded-full flex-shrink-0" :class="stage.dot" />
                        <span class="text-xs font-medium leading-tight">{{ stage.label }}</span>
                    </div>
                    <div class="text-2xl font-bold tabular-nums">{{ stage.count }}</div>
                </div>
            </div>
        </div>

        <!-- ── Extra inzichten ─────────────────────────────────────────────── -->
        <div class="grid gap-4 sm:grid-cols-3">

            <!-- Gem. bedrag -->
            <div class="rounded-2xl border border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <div class="mb-1 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="text-muted-foreground h-4 w-4">
                        <path d="M13.024 9.25c.47 0 .827-.433.637-.863a4 4 0 0 0-4.094-2.364c-.468.05-.665.576-.43.984l1.08 1.868a.75.75 0 0 0 .649.375h2.158ZM7.84 7.758c-.236-.408-.79-.5-1.068-.12A3.982 3.982 0 0 0 6 10c0 .884.287 1.7.772 2.363.278.38.832.287 1.068-.12l1.078-1.868a.75.75 0 0 0 0-.75L7.839 7.758ZM9.138 12.993c-.235.408-.039.934.43.984a4 4 0 0 0 4.094-2.364c.19-.43-.168-.863-.638-.863h-2.158a.75.75 0 0 0-.65.375l-1.078 1.868Z" />
                        <path fill-rule="evenodd" d="M14.13 4.347A8 8 0 1 1 5.87 15.653 8 8 0 0 1 14.13 4.347Zm-1.168 1.154a6.5 6.5 0 1 0-5.924 11 6.5 6.5 0 0 0 5.924-11Z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-muted-foreground text-sm font-medium">Gem. bedrag</span>
                </div>
                <template v-if="transactionStats">
                    <div class="text-2xl font-bold">{{ filteredTransactions.length ? formatCurrency(avgTransactionAmount) : '—' }}</div>
                    <p class="text-muted-foreground mt-0.5 text-xs">per chargeback / refund (incl. btw)</p>
                </template>
                <template v-else>
                    <div class="mt-2 h-7 w-32 animate-pulse rounded-md bg-sidebar-accent" />
                </template>
            </div>

            <!-- Oudste openstaande factuur — coming soon -->
            <div class="relative overflow-hidden rounded-2xl border border-dashed border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <div class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-2xl backdrop-blur-[2px]">
                    <div class="flex items-center gap-2 rounded-full border border-sidebar-border/60 bg-white/80 px-3 py-1.5 shadow-sm dark:bg-sidebar/80">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3.5 w-3.5 text-muted-foreground">
                            <path fill-rule="evenodd" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7V4.5A3.5 3.5 0 0 0 8 1Zm2 6V4.5a2 2 0 1 0-4 0V7h4Z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs font-semibold text-foreground">Coming soon</span>
                    </div>
                </div>
                <div class="mb-1 flex items-center gap-2 opacity-30">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="text-muted-foreground h-4 w-4">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-muted-foreground text-sm font-medium">Oudste openstaande factuur</span>
                </div>
                <div class="opacity-30">
                    <div class="text-2xl font-bold">– dagen</div>
                    <p class="text-muted-foreground mt-0.5 text-xs">oud</p>
                </div>
            </div>

            <!-- Kritieke facturen — coming soon -->
            <div class="relative overflow-hidden rounded-2xl border border-dashed border-sidebar-border/70 p-5 dark:border-sidebar-border">
                <div class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-2xl backdrop-blur-[2px]">
                    <div class="flex items-center gap-2 rounded-full border border-sidebar-border/60 bg-white/80 px-3 py-1.5 shadow-sm dark:bg-sidebar/80">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3.5 w-3.5 text-muted-foreground">
                            <path fill-rule="evenodd" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V7A1.5 1.5 0 0 0 3 8.5v5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 11.5 7V4.5A3.5 3.5 0 0 0 8 1Zm2 6V4.5a2 2 0 1 0-4 0V7h4Z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-xs font-semibold text-foreground">Coming soon</span>
                    </div>
                </div>
                <div class="mb-1 flex items-center gap-2 opacity-30">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-red-500">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-muted-foreground text-sm font-medium">Kritieke facturen (&gt; 30 dagen)</span>
                </div>
                <div class="opacity-30">
                    <div class="text-2xl font-bold">–</div>
                    <p class="text-muted-foreground mt-0.5 text-xs">facturen vereisen aandacht</p>
                </div>
            </div>
        </div>

    </div>
</template>
