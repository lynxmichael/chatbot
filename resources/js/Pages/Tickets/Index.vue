<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";
import StateBadge from "@/Components/UI/StateBadge.vue";

const props = defineProps({
    tickets: { type: Object, required: true },
    statistics: { type: Object, default: () => ({}) },
    agents: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
});

/*
|--------------------------------------------------------------------------
| Filtres
|--------------------------------------------------------------------------
|
| La recherche est temporisée : sans cela, chaque frappe déclenchait une
| requête. Les listes déroulantes, elles, s'appliquent immédiatement.
|
*/

const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");
const priority = ref(props.filters.priority ?? "");
const category = ref(props.filters.category ?? "");
const assignedTo = ref(props.filters.assigned_to ?? "");
const channel = ref(props.filters.channel ?? "");

const applyFilters = () => {
    router.get(
        route("tickets.index"),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            priority: priority.value || undefined,
            category: category.value || undefined,
            assigned_to: assignedTo.value || undefined,
            channel: channel.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let searchTimer = null;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

watch([status, priority, category, assignedTo, channel], applyFilters);

const resetFilters = () => {
    search.value = "";
    status.value = "";
    priority.value = "";
    category.value = "";
    assignedTo.value = "";
    channel.value = "";
};

const activeFilters = computed(
    () =>
        [
            search.value,
            status.value,
            priority.value,
            category.value,
            assignedTo.value,
            channel.value,
        ].filter(Boolean).length,
);

/*
|--------------------------------------------------------------------------
| Actualisation automatique
|--------------------------------------------------------------------------
|
| Suspendue quand l'onglet passe en arrière-plan : interroger le serveur
| toutes les dix secondes pour un écran que personne ne regarde est une
| dépense pure.
|
*/

const refreshing = ref(false);

let refreshInterval = null;

const refreshTickets = () => {
    if (refreshing.value || document.hidden) {
        return;
    }

    refreshing.value = true;

    router.reload({
        only: ["tickets", "statistics"],
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            refreshing.value = false;
        },
    });
};

onMounted(() => {
    refreshInterval = setInterval(refreshTickets, 10000);
});

onBeforeUnmount(() => {
    clearInterval(refreshInterval);
    clearTimeout(searchTimer);
});

/*
|--------------------------------------------------------------------------
| Statistiques
|--------------------------------------------------------------------------
|
| Cliquables : chaque tuile est un filtre. C'est le raccourci le plus
| utilisé au quotidien.
|
*/

const stat = (key) => Number(props.statistics?.[key] ?? 0);

const tiles = computed(() => [
    { key: "open", label: "Ouverts", value: stat("open"), filter: { status: "open" }, tone: "text-sky-600" },
    { key: "in_progress", label: "En cours", value: stat("in_progress"), filter: { status: "in_progress" }, tone: "text-brand-600" },
    { key: "urgent", label: "Urgents", value: stat("urgent"), filter: { priority: "urgent" }, tone: "text-rose-600" },
    { key: "unassigned", label: "Sans agent", value: stat("unassigned"), filter: {}, tone: "text-amber-600" },
    { key: "resolved", label: "Résolus", value: stat("resolved"), filter: { status: "resolved" }, tone: "text-emerald-600" },
    { key: "today", label: "Aujourd'hui", value: stat("today"), filter: {}, tone: "text-night-700" },
]);

const applyTile = (tile) => {
    if (!Object.keys(tile.filter).length) {
        return;
    }

    resetFilters();

    if (tile.filter.status) {
        status.value = tile.filter.status;
    }

    if (tile.filter.priority) {
        priority.value = tile.filter.priority;
    }
};

/*
|--------------------------------------------------------------------------
| Présentation
|--------------------------------------------------------------------------
|
| Les libellés d'état viennent de lib/tokens.js, pas de tables recopiées
| ici : une couleur de statut doit dire la même chose sur toutes les pages.
|
*/

const categoryLabels = {
    facturation: "Facturation",
    livraison: "Livraison",
    technique: "Technique",
    reclamation: "Réclamation",
    remboursement: "Remboursement",
    commande: "Commande",
    compte: "Compte",
    general: "Général",
};

const categoryLabel = (value) => categoryLabels[value] ?? value ?? "—";

const clientName = (ticket) => {
    const client = ticket.client;

    if (!client) {
        return "Client inconnu";
    }

    const name = `${client.first_name ?? ""} ${client.last_name ?? ""}`.trim();

    return name || client.email || client.phone || "Client inconnu";
};

const agentName = (ticket) =>
    ticket.assigned_agent?.name ?? ticket.assignedAgent?.name ?? null;

const formatDate = (value) => {
    if (!value) {
        return "—";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "—";
    }

    return date.toLocaleString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    });
};

/*
 * État du SLA : dépassé, proche, ou sans objet une fois le ticket clos.
 */
const slaState = (ticket) => {
    if (
        !ticket.sla_due_at ||
        ["resolved", "closed"].includes(ticket.status)
    ) {
        return { tone: "text-night-400", label: "—" };
    }

    const due = new Date(ticket.sla_due_at);

    if (Number.isNaN(due.getTime())) {
        return { tone: "text-night-400", label: "—" };
    }

    const minutes = (due.getTime() - Date.now()) / 60000;

    if (minutes < 0) {
        return { tone: "text-rose-600 font-semibold", label: "Dépassé" };
    }

    if (minutes < 60) {
        return {
            tone: "text-amber-600 font-semibold",
            label: `${Math.round(minutes)} min`,
        };
    }

    return { tone: "text-night-500", label: formatDate(ticket.sla_due_at) };
};

const hasTickets = computed(() => props.tickets.data.length > 0);
</script>

<template>
    <Head title="Tickets" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Tickets"
                description="Les demandes qui nécessitent un suivi."
            >
                <template #actions>
                    <span
                        v-if="refreshing"
                        class="self-center text-xs text-night-400"
                    >
                        actualisation…
                    </span>

                    <Link
                        :href="route('tickets.create')"
                        class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"
                    >
                        Nouveau ticket
                    </Link>
                </template>
            </PageHeader>

            <FlashMessages />

            <!-- Statistiques -->

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <button
                    v-for="tile in tiles"
                    :key="tile.key"
                    type="button"
                    class="rounded-xl border border-line bg-white px-4 py-3 text-left transition hover:border-line-strong hover:bg-canvas-sunken"
                    @click="applyTile(tile)"
                >
                    <p class="text-xs text-night-400">{{ tile.label }}</p>
                    <p
                        class="mt-1 text-2xl font-semibold tabular-nums"
                        :class="tile.tone"
                    >
                        {{ tile.value }}
                    </p>
                </button>
            </div>

            <!-- Filtres -->

            <SurfaceCard>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <label class="block text-xs font-medium text-night-500">
                            Rechercher
                        </label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Numéro, sujet, client…"
                            class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Statut
                        </label>
                        <select
                            v-model="status"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Tous</option>
                            <option value="open">Ouvert</option>
                            <option value="pending">En attente</option>
                            <option value="in_progress">En cours</option>
                            <option value="resolved">Résolu</option>
                            <option value="closed">Fermé</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Priorité
                        </label>
                        <select
                            v-model="priority"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Toutes</option>
                            <option value="urgent">Urgente</option>
                            <option value="high">Élevée</option>
                            <option value="normal">Normale</option>
                            <option value="low">Faible</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Catégorie
                        </label>
                        <select
                            v-model="category"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Toutes</option>
                            <option
                                v-for="item in categories"
                                :key="item"
                                :value="item"
                            >
                                {{ categoryLabel(item) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Agent
                        </label>
                        <select
                            v-model="assignedTo"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Tous</option>
                            <option
                                v-for="agent in agents"
                                :key="agent.id"
                                :value="agent.id"
                            >
                                {{ agent.name }}
                            </option>
                        </select>
                    </div>

                    <button
                        v-if="activeFilters"
                        type="button"
                        class="rounded-xl border border-line px-4 py-2 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                        @click="resetFilters"
                    >
                        Effacer ({{ activeFilters }})
                    </button>
                </div>
            </SurfaceCard>

            <!-- Liste -->

            <SurfaceCard flush>
                <div v-if="hasTickets">
                    <!-- Écran large -->

                    <table class="hidden w-full lg:table">
                        <thead>
                            <tr
                                class="border-b border-line text-left text-xs font-medium text-night-400"
                            >
                                <th class="px-6 py-3">Ticket</th>
                                <th class="px-3 py-3">Client</th>
                                <th class="px-3 py-3">Catégorie</th>
                                <th class="px-3 py-3">Priorité</th>
                                <th class="px-3 py-3">Statut</th>
                                <th class="px-3 py-3">Agent</th>
                                <th class="px-3 py-3">SLA</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-line">
                            <tr
                                v-for="ticket in tickets.data"
                                :key="ticket.id"
                                class="cursor-pointer transition hover:bg-canvas-sunken"
                                @click="
                                    router.visit(
                                        route('tickets.show', ticket.id),
                                    )
                                "
                            >
                                <td class="px-6 py-4">
                                    <p
                                        class="font-mono text-xs text-night-400"
                                    >
                                        {{ ticket.ticket_number }}
                                    </p>
                                    <p
                                        class="mt-0.5 max-w-xs truncate font-medium text-night-900"
                                    >
                                        {{ ticket.subject }}
                                    </p>
                                </td>

                                <td class="px-3 py-4 text-sm text-night-600">
                                    {{ clientName(ticket) }}
                                </td>

                                <td class="px-3 py-4 text-sm text-night-500">
                                    {{ categoryLabel(ticket.category) }}
                                </td>

                                <td class="px-3 py-4">
                                    <StateBadge
                                        kind="priority"
                                        :value="ticket.priority"
                                        dense
                                    />
                                </td>

                                <td class="px-3 py-4">
                                    <StateBadge
                                        kind="status"
                                        :value="ticket.status"
                                        dense
                                    />
                                </td>

                                <td class="px-3 py-4 text-sm">
                                    <span
                                        v-if="agentName(ticket)"
                                        class="text-night-600"
                                    >
                                        {{ agentName(ticket) }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-amber-600"
                                    >
                                        non attribué
                                    </span>
                                </td>

                                <td
                                    class="px-3 py-4 text-sm"
                                    :class="slaState(ticket).tone"
                                >
                                    {{ slaState(ticket).label }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Mobile -->

                    <div class="divide-y divide-line lg:hidden">
                        <Link
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            :href="route('tickets.show', ticket.id)"
                            class="block px-5 py-4 transition hover:bg-canvas-sunken"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-xs text-night-400">
                                        {{ ticket.ticket_number }}
                                    </p>
                                    <p
                                        class="mt-0.5 truncate font-medium text-night-900"
                                    >
                                        {{ ticket.subject }}
                                    </p>
                                    <p
                                        class="mt-1 truncate text-sm text-night-500"
                                    >
                                        {{ clientName(ticket) }}
                                    </p>
                                </div>

                                <StateBadge
                                    kind="status"
                                    :value="ticket.status"
                                    dense
                                />
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <StateBadge
                                    kind="priority"
                                    :value="ticket.priority"
                                    dense
                                />

                                <span class="text-xs text-night-400">
                                    {{ categoryLabel(ticket.category) }}
                                </span>

                                <span
                                    class="ml-auto text-xs"
                                    :class="slaState(ticket).tone"
                                >
                                    {{ slaState(ticket).label }}
                                </span>
                            </div>
                        </Link>
                    </div>
                </div>

                <EmptyState
                    v-else
                    icon="🎫"
                    title="Aucun ticket"
                    :description="
                        activeFilters
                            ? 'Aucun ticket ne correspond à ces filtres.'
                            : 'Les demandes nécessitant un suivi apparaîtront ici.'
                    "
                />
            </SurfaceCard>

            <!-- Pagination -->

            <div
                v-if="tickets.links && tickets.links.length > 3"
                class="flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in tickets.links"
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
