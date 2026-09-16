<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();

const props = defineProps({
    insights: { type: Object, required: true },
    filters: { type: Object, default: () => ({ status: "open", type: "" }) },
    summary: { type: Array, default: () => [] },
});

const successMessage = computed(() => page.props.flash?.success ?? null);

const processing = ref(null);

/*
|--------------------------------------------------------------------------
| Présentation
|--------------------------------------------------------------------------
*/

const typeLabels = {
    sla_breached: "SLA dépassé",
    sla_at_risk: "SLA à risque",
    ticket_stale: "Ticket oublié",
    ticket_unassigned: "Sans responsable",
    client_waiting: "Client sans réponse",
    unhappy_client: "Client mécontent",
    missed_call: "Appel manqué",
    repeated_requests: "Demandes répétées",
    agent_overloaded: "Agent surchargé",
};

const severityStyles = {
    critical: "border-rose-200 bg-rose-50",
    high: "border-orange-200 bg-orange-50",
    medium: "border-amber-200 bg-amber-50",
    low: "border-line bg-white",
};

const severityBadges = {
    critical: "bg-rose-600 text-white",
    high: "bg-orange-500 text-white",
    medium: "bg-amber-400 text-amber-950",
    low: "bg-night-100 text-night-600",
};

const severityLabels = {
    critical: "critique",
    high: "élevée",
    medium: "moyenne",
    low: "faible",
};

const label = (type) => typeLabels[type] ?? type;

/*
 * Compteurs par type, pour les filtres rapides.
 */
const typeCounts = computed(() => {
    const counts = {};

    props.summary.forEach((row) => {
        counts[row.type] = (counts[row.type] ?? 0) + Number(row.total);
    });

    return counts;
});

const filterBy = (params) => {
    router.get(
        route("autopilot.insights"),
        { ...props.filters, ...params },
        { preserveState: true, preserveScroll: true },
    );
};

const acknowledge = (insight) => {
    processing.value = insight.id;

    router.post(
        route("autopilot.insights.acknowledge", insight.id),
        {},
        { preserveScroll: true, onFinish: () => (processing.value = null) },
    );
};

const resolve = (insight) => {
    processing.value = insight.id;

    router.post(
        route("autopilot.insights.resolve", insight.id),
        {},
        { preserveScroll: true, onFinish: () => (processing.value = null) },
    );
};

/*
 * Lien vers l'objet concerné, quand il en existe un.
 */
const subjectLink = (insight) => {
    if (insight.subject_type === "ticket") {
        return route("tickets.show", insight.subject_id);
    }

    if (insight.subject_type === "conversation") {
        return route("conversations.show", insight.subject_id);
    }

    if (insight.subject_type === "call") {
        return route("calls.show", insight.subject_id);
    }

    if (insight.subject_type === "client") {
        return route("clients.show", insight.subject_id);
    }

    return null;
};
</script>

<template>
    <Head title="Alertes" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-night-900">
                        Ce qui demande votre attention
                    </h1>
                    <p class="mt-1 text-sm text-night-400">
                        Détecté automatiquement sur les tickets, les
                        conversations et les appels.
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

            <!-- Filtres -->

            <div class="mt-6 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        !filters.type
                            ? 'bg-night-800 text-white'
                            : 'bg-white text-night-600 ring-1 ring-line hover:bg-canvas-sunken'
                    "
                    @click="filterBy({ type: '' })"
                >
                    Tout
                </button>

                <button
                    v-for="(count, type) in typeCounts"
                    :key="type"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        filters.type === type
                            ? 'bg-night-800 text-white'
                            : 'bg-white text-night-600 ring-1 ring-line hover:bg-canvas-sunken'
                    "
                    @click="filterBy({ type })"
                >
                    {{ label(type) }}
                    <span class="ml-1 text-xs opacity-80">({{ count }})</span>
                </button>

                <button
                    type="button"
                    class="ml-auto rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        filters.status === 'resolved'
                            ? 'bg-night-800 text-white'
                            : 'bg-white text-night-600 ring-1 ring-line hover:bg-canvas-sunken'
                    "
                    @click="
                        filterBy({
                            status:
                                filters.status === 'resolved'
                                    ? 'open'
                                    : 'resolved',
                        })
                    "
                >
                    {{
                        filters.status === "resolved"
                            ? "Voir les actives"
                            : "Voir les clôturées"
                    }}
                </button>
            </div>

            <!-- Liste -->

            <div class="mt-5 space-y-3">
                <article
                    v-for="insight in insights.data"
                    :key="insight.id"
                    class="rounded-2xl border p-5 shadow-lift"
                    :class="
                        severityStyles[insight.severity] ??
                        'border-line bg-white'
                    "
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-md px-2 py-0.5 text-xs font-semibold"
                                    :class="
                                        severityBadges[insight.severity] ??
                                        'bg-night-100 text-night-600'
                                    "
                                >
                                    {{
                                        severityLabels[insight.severity] ??
                                        insight.severity
                                    }}
                                </span>

                                <span
                                    class="rounded-md bg-white/70 px-2 py-0.5 text-xs font-medium text-night-500 ring-1 ring-line"
                                >
                                    {{ label(insight.type) }}
                                </span>

                                <span
                                    v-if="insight.status === 'acknowledged'"
                                    class="text-xs text-night-400"
                                >
                                    prise en compte
                                </span>
                            </div>

                            <h2 class="mt-2 font-semibold text-night-900">
                                {{ insight.title }}
                            </h2>

                            <p
                                v-if="insight.detail"
                                class="mt-1 text-sm leading-relaxed text-night-600"
                            >
                                {{ insight.detail }}
                            </p>

                            <p class="mt-2 text-xs text-night-400">
                                {{ insight.detected_at }}
                                <template v-if="insight.agent">
                                    · agent : {{ insight.agent }}
                                </template>
                                <template v-if="insight.client">
                                    · client : {{ insight.client }}
                                </template>
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <Link
                                v-if="subjectLink(insight)"
                                :href="subjectLink(insight)"
                                class="rounded-lg bg-night-800 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-night-700"
                            >
                                Ouvrir
                            </Link>

                            <button
                                v-if="insight.status === 'open'"
                                type="button"
                                :disabled="processing === insight.id"
                                class="rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken disabled:opacity-50"
                                @click="acknowledge(insight)"
                            >
                                Je m'en occupe
                            </button>

                            <button
                                v-if="insight.status !== 'resolved'"
                                type="button"
                                :disabled="processing === insight.id"
                                class="rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken disabled:opacity-50"
                                @click="resolve(insight)"
                            >
                                Clôturer
                            </button>
                        </div>
                    </div>
                </article>

                <div
                    v-if="!insights.data.length"
                    class="rounded-2xl border border-dashed border-line-strong bg-white px-6 py-14 text-center"
                >
                    <p class="text-sm font-medium text-night-700">
                        Aucune alerte active.
                    </p>
                    <p class="mt-1 text-sm text-night-400">
                        Tout est à jour côté tickets, conversations et appels.
                    </p>
                </div>
            </div>

            <!-- Pagination -->

            <div
                v-if="insights.links && insights.links.length > 3"
                class="mt-6 flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in insights.links"
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
