<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();

const props = defineProps({
    actions: { type: Object, required: true },
    filters: { type: Object, default: () => ({ status: "pending" }) },
    counts: { type: Object, default: () => ({ pending: 0 }) },
});

const successMessage = computed(() => page.props.flash?.success ?? null);
const errorMessage = computed(() => page.props.flash?.error ?? null);

const processing = ref(null);

const statuses = [
    { value: "pending", label: "À valider" },
    { value: "approved", label: "Validées" },
    { value: "rejected", label: "Refusées" },
    { value: "executed", label: "Automatiques" },
    { value: "denied", label: "Bloquées" },
    { value: "all", label: "Tout" },
];

const filterBy = (status) => {
    router.get(
        route("autopilot.approvals"),
        { status },
        { preserveState: true, preserveScroll: true },
    );
};

const approve = (action) => {
    processing.value = action.id;

    router.post(
        route("autopilot.approvals.approve", action.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (processing.value = null),
        },
    );
};

const reject = (action) => {
    const reason = window.prompt(
        "Motif du refus (facultatif) — il sera conservé dans le journal :",
    );

    if (reason === null) {
        return;
    }

    processing.value = action.id;

    router.post(
        route("autopilot.approvals.reject", action.id),
        { reason },
        {
            preserveScroll: true,
            onFinish: () => (processing.value = null),
        },
    );
};

/*
|--------------------------------------------------------------------------
| Présentation
|--------------------------------------------------------------------------
*/

const toolLabels = {
    create_ticket: "Créer un ticket",
    update_ticket: "Modifier un ticket",
    schedule_follow_up: "Programmer une relance",
    escalate_to_human: "Transférer à un conseiller",
    record_insights: "Enregistrer une analyse",
    search_knowledge: "Rechercher une information",
    get_client_profile: "Consulter le dossier client",
    get_order_status: "Consulter une commande",
    get_ticket_status: "Consulter un ticket",
};

const label = (tool) => toolLabels[tool] ?? tool;

const statusStyles = {
    pending: "bg-amber-100 text-amber-800",
    approved: "bg-emerald-100 text-emerald-800",
    executed: "bg-brand-100 text-brand-700",
    rejected: "bg-night-100 text-night-600",
    denied: "bg-night-100 text-night-600",
    failed: "bg-rose-100 text-rose-800",
};

const statusLabels = {
    pending: "à valider",
    approved: "validée",
    executed: "exécutée",
    rejected: "refusée",
    denied: "bloquée",
    failed: "échec",
};

/*
 * Les paramètres bruts de l'IA sont affichés tels quels :
 * un responsable doit voir exactement ce qui sera exécuté.
 */
const entries = (input) => Object.entries(input ?? {});
</script>

<template>
    <Head title="Actions à valider" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-night-900">
                        Actions de l'IA
                    </h1>
                    <p class="mt-1 text-sm text-night-400">
                        Rien n'est exécuté ici tant que vous n'avez pas validé.
                    </p>
                </div>

                <Link
                    :href="route('autopilot.index')"
                    class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:border-line-strong hover:bg-canvas-sunken"
                >
                    Réglages
                </Link>
            </div>

            <div
                v-if="successMessage"
                class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ successMessage }}
            </div>

            <div
                v-if="errorMessage"
                class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            >
                {{ errorMessage }}
            </div>

            <!-- Filtres -->

            <div class="mt-6 flex flex-wrap gap-2">
                <button
                    v-for="status in statuses"
                    :key="status.value"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        filters.status === status.value
                            ? 'bg-night-800 text-white'
                            : 'bg-white text-night-600 ring-1 ring-line hover:bg-canvas-sunken'
                    "
                    @click="filterBy(status.value)"
                >
                    {{ status.label }}
                    <span
                        v-if="status.value === 'pending' && counts.pending"
                        class="ml-1 text-xs opacity-80"
                    >
                        ({{ counts.pending }})
                    </span>
                </button>
            </div>

            <!-- Liste -->

            <div class="mt-5 space-y-3">
                <article
                    v-for="action in actions.data"
                    :key="action.id"
                    class="rounded-2xl border border-line bg-white p-5 shadow-lift"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-night-900">
                                    {{ label(action.tool) }}
                                </h2>

                                <span
                                    class="rounded-md px-2 py-0.5 text-xs font-semibold"
                                    :class="
                                        statusStyles[action.status] ??
                                        'bg-night-100 text-night-600'
                                    "
                                >
                                    {{
                                        statusLabels[action.status] ??
                                        action.status
                                    }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-night-400">
                                {{ action.created_at }}
                                <template v-if="action.client">
                                    · {{ action.client }}
                                </template>
                                <template v-if="action.conversation_id">
                                    ·
                                    <Link
                                        :href="
                                            route(
                                                'conversations.show',
                                                action.conversation_id,
                                            )
                                        "
                                        class="text-brand-600 hover:underline"
                                    >
                                        conversation
                                        #{{ action.conversation_id }}
                                    </Link>
                                </template>
                            </p>
                        </div>

                        <div
                            v-if="action.status === 'pending'"
                            class="flex shrink-0 gap-2"
                        >
                            <button
                                type="button"
                                :disabled="processing === action.id"
                                class="rounded-lg bg-brand-500 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50"
                                @click="approve(action)"
                            >
                                Valider
                            </button>

                            <button
                                type="button"
                                :disabled="processing === action.id"
                                class="rounded-lg border border-line px-3.5 py-2 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken disabled:opacity-50"
                                @click="reject(action)"
                            >
                                Refuser
                            </button>
                        </div>
                    </div>

                    <!-- Paramètres proposés -->

                    <dl
                        v-if="entries(action.input).length"
                        class="mt-4 space-y-2 rounded-xl bg-canvas-sunken px-4 py-3"
                    >
                        <div
                            v-for="[key, value] in entries(action.input)"
                            :key="key"
                            class="grid gap-1 sm:grid-cols-[160px_1fr]"
                        >
                            <dt
                                class="font-mono text-xs text-night-400"
                            >
                                {{ key }}
                            </dt>
                            <dd
                                class="whitespace-pre-line break-words text-sm text-night-700"
                            >
                                {{ value }}
                            </dd>
                        </div>
                    </dl>

                    <p
                        v-if="action.reason"
                        class="mt-3 text-xs text-night-400"
                    >
                        {{ action.reason }}
                        <template v-if="action.reviewer">
                            — {{ action.reviewer }}, {{ action.reviewed_at }}
                        </template>
                    </p>
                </article>

                <div
                    v-if="!actions.data.length"
                    class="rounded-2xl border border-dashed border-line-strong bg-white px-6 py-14 text-center"
                >
                    <p class="text-sm font-medium text-night-700">
                        Rien à valider.
                    </p>
                    <p class="mt-1 text-sm text-night-400">
                        Les actions proposées par l'IA apparaîtront ici.
                    </p>
                </div>
            </div>

            <!-- Pagination -->

            <div
                v-if="actions.links && actions.links.length > 3"
                class="mt-6 flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in actions.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-lg px-3 py-1.5 text-sm transition"
                    :class="[
                        link.active
                            ? 'bg-night-800 text-white'
                            : 'bg-white text-night-600 ring-1 ring-line hover:bg-canvas-sunken',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
