<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();

const props = defineProps({
    tickets: {
        type: Object,
        required: true,
    },

    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            open: 0,
            pending: 0,
            in_progress: 0,
            resolved: 0,
            closed: 0,
            urgent: 0,
            unassigned: 0,
            today: 0,
        }),
    },

    agents: {
        type: Array,
        default: () => [],
    },

    clients: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
            status: "",
            priority: "",
            category: "",
            assigned_to: "",
            channel: "",
        }),
    },
});

/*
|--------------------------------------------------------------------------
| Filtres
|--------------------------------------------------------------------------
*/

const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");
const priority = ref(props.filters.priority ?? "");
const category = ref(props.filters.category ?? "");
const assignedTo = ref(props.filters.assigned_to ?? "");
const channel = ref(props.filters.channel ?? "");

/*
|--------------------------------------------------------------------------
| État
|--------------------------------------------------------------------------
*/

const refreshing = ref(false);
let refreshInterval = null;

/*
|--------------------------------------------------------------------------
| Messages flash
|--------------------------------------------------------------------------
*/

const successMessage = computed(() => {
    return page.props.flash?.success ?? null;
});

const errorMessage = computed(() => {
    return page.props.flash?.error ?? null;
});

/*
|--------------------------------------------------------------------------
| Statistiques
|--------------------------------------------------------------------------
*/

const totalTickets = computed(() => {
    return (
        props.statistics?.total ??
        props.tickets?.total ??
        props.tickets?.data?.length ??
        0
    );
});

const openTickets = computed(() => {
    return props.statistics?.open ?? 0;
});

const pendingTickets = computed(() => {
    return props.statistics?.pending ?? 0;
});

const inProgressTickets = computed(() => {
    return props.statistics?.in_progress ?? 0;
});

const resolvedTickets = computed(() => {
    return props.statistics?.resolved ?? 0;
});

const urgentTickets = computed(() => {
    return props.statistics?.urgent ?? 0;
});

const unassignedTickets = computed(() => {
    return props.statistics?.unassigned ?? 0;
});

const todayTickets = computed(() => {
    return props.statistics?.today ?? 0;
});

/*
|--------------------------------------------------------------------------
| Appliquer les filtres
|--------------------------------------------------------------------------
*/

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
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

/*
|--------------------------------------------------------------------------
| Réinitialiser les filtres
|--------------------------------------------------------------------------
*/

const resetFilters = () => {
    search.value = "";
    status.value = "";
    priority.value = "";
    category.value = "";
    assignedTo.value = "";
    channel.value = "";

    router.get(
        route("tickets.index"),
        {},
        {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        }
    );
};

/*
|--------------------------------------------------------------------------
| Actualisation automatique
|--------------------------------------------------------------------------
*/

const refreshTickets = () => {
    if (refreshing.value) {
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
    refreshInterval = setInterval(() => {
        refreshTickets();
    }, 5000);
});

onBeforeUnmount(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
});

/*
|--------------------------------------------------------------------------
| Statuts
|--------------------------------------------------------------------------
*/

const statusLabel = (value) => {
    const labels = {
        open: "Ouvert",
        pending: "En attente",
        in_progress: "En cours",
        resolved: "Résolu",
        closed: "Fermé",
    };

    return labels[value] ?? value ?? "—";
};

const statusClass = (value) => {
    const classes = {
        open: "bg-blue-100 text-blue-700",
        pending: "bg-amber-100 text-amber-700",
        in_progress: "bg-indigo-100 text-indigo-700",
        resolved: "bg-emerald-100 text-emerald-700",
        closed: "bg-gray-100 text-gray-600",
    };

    return classes[value] ?? "bg-gray-100 text-gray-600";
};

/*
|--------------------------------------------------------------------------
| Priorités
|--------------------------------------------------------------------------
*/

const priorityLabel = (value) => {
    const labels = {
        low: "Faible",
        normal: "Normale",
        high: "Élevée",
        urgent: "Urgente",
    };

    return labels[value] ?? value ?? "—";
};

const priorityClass = (value) => {
    const classes = {
        low: "bg-gray-100 text-gray-600",
        normal: "bg-blue-100 text-blue-700",
        high: "bg-orange-100 text-orange-700",
        urgent: "bg-red-100 text-red-700",
    };

    return classes[value] ?? "bg-gray-100 text-gray-600";
};

/*
|--------------------------------------------------------------------------
| Catégories
|--------------------------------------------------------------------------
*/

const categoryLabel = (value) => {
    const labels = {
        general: "Générale",
        technical: "Technique",
        billing: "Facturation",
        complaint: "Réclamation",
        request: "Demande",
        network: "Réseau",
        account: "Compte",
        other: "Autre",
    };

    return labels[value] ?? value ?? "—";
};

/*
|--------------------------------------------------------------------------
| Canaux
|--------------------------------------------------------------------------
*/

const channelLabel = (value) => {
    const labels = {
        web: "Web",
        widget: "Widget",
        whatsapp: "WhatsApp",
        email: "Email",
        phone: "Téléphone",
    };

    return labels[value] ?? value ?? "—";
};

const channelClass = (value) => {
    const classes = {
        web: "bg-indigo-100 text-indigo-700",
        widget: "bg-cyan-100 text-cyan-700",
        whatsapp: "bg-green-100 text-green-700",
        email: "bg-purple-100 text-purple-700",
        phone: "bg-orange-100 text-orange-700",
    };

    return classes[value] ?? "bg-gray-100 text-gray-600";
};

/*
|--------------------------------------------------------------------------
| Client
|--------------------------------------------------------------------------
*/

const getClientName = (ticket) => {
    const client = ticket?.client;

    if (!client) {
        return "Client inconnu";
    }

    if (client.full_name) {
        return client.full_name;
    }

    const fullName = `${client.first_name ?? ""} ${
        client.last_name ?? ""
    }`.trim();

    return fullName || "Client inconnu";
};

const getClientContact = (ticket) => {
    const client = ticket?.client;

    if (!client) {
        return "—";
    }

    return client.phone ?? client.email ?? "—";
};

/*
|--------------------------------------------------------------------------
| Agent
|--------------------------------------------------------------------------
*/

const getAgentName = (ticket) => {
    const agent =
        ticket?.assigned_agent ??
        ticket?.assignedAgent ??
        null;

    if (!agent) {
        return "Non attribué";
    }

    if (agent.name) {
        return agent.name;
    }

    const fullName = `${agent.first_name ?? ""} ${
        agent.last_name ?? ""
    }`.trim();

    return fullName || agent.email || "Agent";
};

const agentClass = (ticket) => {
    const agent =
        ticket?.assigned_agent ??
        ticket?.assignedAgent ??
        null;

    if (agent) {
        return "bg-emerald-100 text-emerald-700";
    }

    return "bg-gray-100 text-gray-500";
};

/*
|--------------------------------------------------------------------------
| Dates
|--------------------------------------------------------------------------
*/

const formatDate = (date) => {
    if (!date) {
        return "—";
    }

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) {
        return "—";
    }

    return parsedDate.toLocaleString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

/*
|--------------------------------------------------------------------------
| SLA
|--------------------------------------------------------------------------
*/

const slaLabel = (ticket) => {
    if (!ticket?.sla_due_at) {
        return "Non défini";
    }

    return formatDate(ticket.sla_due_at);
};

const slaClass = (ticket) => {
    if (
        !ticket?.sla_due_at ||
        ticket.status === "resolved" ||
        ticket.status === "closed"
    ) {
        return "bg-gray-100 text-gray-600";
    }

    const dueDate = new Date(ticket.sla_due_at);

    if (Number.isNaN(dueDate.getTime())) {
        return "bg-gray-100 text-gray-600";
    }

    if (dueDate.getTime() < Date.now()) {
        return "bg-red-100 text-red-700";
    }

    return "bg-amber-100 text-amber-700";
};

/*
|--------------------------------------------------------------------------
| Numéro du ticket
|--------------------------------------------------------------------------
*/

const ticketNumber = (ticket) => {
    return ticket?.ticket_number ?? `#${ticket?.id ?? ""}`;
};

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

const paginationLinks = computed(() => {
    return props.tickets?.links ?? [];
});
const getAgentDisplayName = (agent) => {
    if (!agent) {
        return "Agent";
    }

    if (agent.name) {
        return agent.name;
    }

    const fullName = `${agent.first_name ?? ""} ${
        agent.last_name ?? ""
    }`.trim();

    if (fullName) {
        return fullName;
    }

    return agent.email ?? "Agent";
};
</script>

<template>
    <Head title="Tickets" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        Tickets / Réclamations
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Gérez les demandes, incidents et réclamations de vos
                        clients.
                    </p>
                </div>

                <Link
                    :href="route('tickets.create')"
                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                >
                    + Nouveau ticket
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- FLASH SUCCESS -->
                <div
                    v-if="successMessage"
                    class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700"
                >
                    {{ successMessage }}
                </div>

                <!-- FLASH ERROR -->
                <div
                    v-if="errorMessage"
                    class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700"
                >
                    {{ errorMessage }}
                </div>

                <!-- STATISTIQUES -->
                <div
                    class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8"
                >
                    <div
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <p class="text-xs font-medium text-gray-500">
                            Total
                        </p>

                        <p class="mt-2 text-2xl font-bold text-gray-900">
                            {{ totalTickets }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <p class="text-xs font-medium text-blue-600">
                            Ouverts
                        </p>

                        <p class="mt-2 text-2xl font-bold text-blue-700">
                            {{ openTickets }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <p class="text-xs font-medium text-amber-600">
                            En attente
                        </p>

                        <p class="mt-2 text-2xl font-bold text-amber-700">
                            {{ pendingTickets }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <p class="text-xs font-medium text-indigo-600">
                            En cours
                        </p>

                        <p class="mt-2 text-2xl font-bold text-indigo-700">
                            {{ inProgressTickets }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <p class="text-xs font-medium text-emerald-600">
                            Résolus
                        </p>

                        <p class="mt-2 text-2xl font-bold text-emerald-700">
                            {{ resolvedTickets }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <p class="text-xs font-medium text-red-600">
                            Urgents
                        </p>

                        <p class="mt-2 text-2xl font-bold text-red-700">
                            {{ urgentTickets }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <p class="text-xs font-medium text-orange-600">
                            Non attribués
                        </p>

                        <p class="mt-2 text-2xl font-bold text-orange-700">
                            {{ unassignedTickets }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100"
                    >
                        <p class="text-xs font-medium text-violet-600">
                            Aujourd'hui
                        </p>

                        <p class="mt-2 text-2xl font-bold text-violet-700">
                            {{ todayTickets }}
                        </p>
                    </div>
                </div>

                <!-- FILTRES -->
                <div
                    class="mb-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
                >
                    <div class="mb-5">
                        <h2 class="text-base font-semibold text-gray-900">
                            Rechercher et filtrer
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Retrouvez rapidement un ticket ou une réclamation.
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                    >
                        <!-- RECHERCHE -->
                        <div>
                            <label
                                for="ticket-search"
                                class="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Recherche
                            </label>

                            <input
                                id="ticket-search"
                                v-model="search"
                                type="text"
                                placeholder="N° ticket, sujet, client..."
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <!-- STATUT -->
                        <div>
                            <label
                                for="ticket-status"
                                class="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Statut
                            </label>

                            <select
                                id="ticket-status"
                                v-model="status"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">
                                    Tous les statuts
                                </option>

                                <option value="open">
                                    Ouvert
                                </option>

                                <option value="pending">
                                    En attente
                                </option>

                                <option value="in_progress">
                                    En cours
                                </option>

                                <option value="resolved">
                                    Résolu
                                </option>

                                <option value="closed">
                                    Fermé
                                </option>
                            </select>
                        </div>

                        <!-- PRIORITE -->
                        <div>
                            <label
                                for="ticket-priority"
                                class="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Priorité
                            </label>

                            <select
                                id="ticket-priority"
                                v-model="priority"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">
                                    Toutes
                                </option>

                                <option value="low">
                                    Faible
                                </option>

                                <option value="normal">
                                    Normale
                                </option>

                                <option value="high">
                                    Élevée
                                </option>

                                <option value="urgent">
                                    Urgente
                                </option>
                            </select>
                        </div>

                        <!-- CATEGORIE -->
                        <div>
                            <label
                                for="ticket-category"
                                class="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Catégorie
                            </label>

                            <select
                                id="ticket-category"
                                v-model="category"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">
                                    Toutes
                                </option>

                                <option value="general">
                                    Générale
                                </option>

                                <option value="technical">
                                    Technique
                                </option>

                                <option value="billing">
                                    Facturation
                                </option>

                                <option value="complaint">
                                    Réclamation
                                </option>

                                <option value="request">
                                    Demande
                                </option>

                                <option value="network">
                                    Réseau
                                </option>

                                <option value="account">
                                    Compte
                                </option>

                                <option value="other">
                                    Autre
                                </option>
                            </select>
                        </div>

                        <!-- AGENT -->
                        <div>
                            <label
                                for="ticket-agent"
                                class="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Agent
                            </label>

                            <select
                                id="ticket-agent"
                                v-model="assignedTo"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">
                                    Tous les agents
                                </option>

                                <option
                                    v-for="agent in agents"
                                    :key="agent.id"
                                    :value="agent.id"
                                >
                                   {{ getAgentDisplayName(agent) }}
                                </option>
                            </select>
                        </div>

                        <!-- CANAL -->
                        <div>
                            <label
                                for="ticket-channel"
                                class="mb-1 block text-sm font-medium text-gray-700"
                            >
                                Canal
                            </label>

                            <select
                                id="ticket-channel"
                                v-model="channel"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">
                                    Tous les canaux
                                </option>

                                <option value="web">
                                    Web
                                </option>

                                <option value="widget">
                                    Widget
                                </option>

                                <option value="whatsapp">
                                    WhatsApp
                                </option>

                                <option value="email">
                                    Email
                                </option>

                                <option value="phone">
                                    Téléphone
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                            @click="applyFilters"
                        >
                            Rechercher
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                            @click="resetFilters"
                        >
                            Réinitialiser
                        </button>
                    </div>
                </div>

                <!-- LISTE -->
                <div
                    class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-gray-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">
                                Liste des tickets
                            </h2>

                            <p class="mt-1 text-sm text-gray-500">
                                Suivez chaque demande jusqu'à sa résolution.
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <span
                                v-if="refreshing"
                                class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700"
                            >
                                <span
                                    class="h-2 w-2 animate-pulse rounded-full bg-indigo-500"
                                ></span>

                                Actualisation...
                            </span>

                            <span
                                v-else
                                class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-emerald-500"
                                ></span>

                                Temps réel
                            </span>

                            <span
                                class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700"
                            >
                                {{ totalTickets }}
                            </span>
                        </div>
                    </div>

                    <!-- AUCUN TICKET -->
                    <div
                        v-if="!tickets.data?.length"
                        class="px-6 py-16 text-center"
                    >
                        <div class="text-5xl">
                            🎫
                        </div>

                        <h3
                            class="mt-4 text-lg font-semibold text-gray-900"
                        >
                            Aucun ticket trouvé
                        </h3>

                        <p class="mt-2 text-sm text-gray-500">
                            Modifiez vos filtres ou créez un nouveau ticket.
                        </p>

                        <Link
                            :href="route('tickets.create')"
                            class="mt-5 inline-flex rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                        >
                            + Créer un ticket
                        </Link>
                    </div>

                    <!-- DESKTOP -->
                    <div
                        v-else
                        class="hidden overflow-x-auto lg:block"
                    >
                        <table
                            class="min-w-full divide-y divide-gray-200"
                        >
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Ticket
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Client
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Catégorie
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Priorité
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Statut
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Agent
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        SLA
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-gray-100 bg-white"
                            >
                                <tr
                                    v-for="ticket in tickets.data"
                                    :key="ticket.id"
                                    class="transition hover:bg-gray-50"
                                >
                                    <!-- TICKET -->
                                    <td class="px-6 py-4">
                                        <Link
                                            :href="
                                                route(
                                                    'tickets.show',
                                                    ticket.id
                                                )
                                            "
                                            class="font-semibold text-indigo-600 hover:text-indigo-800"
                                        >
                                            {{ ticketNumber(ticket) }}
                                        </Link>

                                        <p
                                            class="mt-1 max-w-xs truncate text-sm font-medium text-gray-900"
                                        >
                                            {{ ticket.subject || "Sans sujet" }}
                                        </p>

                                        <div
                                            class="mt-2 flex flex-wrap gap-1.5"
                                        >
                                            <span
                                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="
                                                    channelClass(
                                                        ticket.channel
                                                    )
                                                "
                                            >
                                                {{
                                                    channelLabel(
                                                        ticket.channel
                                                    )
                                                }}
                                            </span>

                                            <span
                                                v-if="ticket.conversation_id"
                                                class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-medium text-violet-700"
                                            >
                                                Conversation
                                            </span>
                                        </div>
                                    </td>

                                    <!-- CLIENT -->
                                    <td class="px-6 py-4">
                                        <p
                                            class="font-medium text-gray-900"
                                        >
                                            {{ getClientName(ticket) }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-gray-500"
                                        >
                                            {{ getClientContact(ticket) }}
                                        </p>
                                    </td>

                                    <!-- CATEGORIE -->
                                    <td class="px-6 py-4">
                                        <span
                                            class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
                                        >
                                            {{
                                                categoryLabel(
                                                    ticket.category
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <!-- PRIORITE -->
                                    <td class="px-6 py-4">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-medium"
                                            :class="
                                                priorityClass(
                                                    ticket.priority
                                                )
                                            "
                                        >
                                            {{
                                                priorityLabel(
                                                    ticket.priority
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <!-- STATUT -->
                                    <td class="px-6 py-4">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-medium"
                                            :class="
                                                statusClass(
                                                    ticket.status
                                                )
                                            "
                                        >
                                            {{
                                                statusLabel(
                                                    ticket.status
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <!-- AGENT -->
                                    <td class="px-6 py-4">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-medium"
                                            :class="agentClass(ticket)"
                                        >
                                            👤 {{ getAgentName(ticket) }}
                                        </span>
                                    </td>

                                    <!-- SLA -->
                                    <td class="px-6 py-4">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-medium"
                                            :class="slaClass(ticket)"
                                        >
                                            ⏱ {{ slaLabel(ticket) }}
                                        </span>
                                    </td>

                                    <!-- ACTION -->
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-right"
                                    >
                                        <div
                                            class="flex items-center justify-end gap-3"
                                        >
                                            <Link
                                                :href="
                                                    route(
                                                        'tickets.show',
                                                        ticket.id
                                                    )
                                                "
                                                class="font-medium text-indigo-600 hover:text-indigo-900"
                                            >
                                                Voir
                                            </Link>

                                            <Link
                                                :href="
                                                    route(
                                                        'tickets.edit',
                                                        ticket.id
                                                    )
                                                "
                                                class="font-medium text-gray-600 hover:text-gray-900"
                                            >
                                                Modifier
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE -->
                    <div
                        v-if="tickets.data?.length"
                        class="divide-y divide-gray-100 lg:hidden"
                    >
                        <div
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            class="p-5"
                        >
                            <div
                                class="flex items-start justify-between gap-4"
                            >
                                <div class="min-w-0">
                                    <Link
                                        :href="
                                            route(
                                                'tickets.show',
                                                ticket.id
                                            )
                                        "
                                        class="font-bold text-indigo-600"
                                    >
                                        {{ ticketNumber(ticket) }}
                                    </Link>

                                    <p
                                        class="mt-1 font-semibold text-gray-900"
                                    >
                                        {{
                                            ticket.subject ||
                                            "Sans sujet"
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-500"
                                    >
                                        {{ getClientName(ticket) }}
                                    </p>
                                </div>

                                <span
                                    class="shrink-0 rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        priorityClass(
                                            ticket.priority
                                        )
                                    "
                                >
                                    {{
                                        priorityLabel(
                                            ticket.priority
                                        )
                                    }}
                                </span>
                            </div>

                            <div
                                class="mt-4 flex flex-wrap gap-2"
                            >
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        statusClass(
                                            ticket.status
                                        )
                                    "
                                >
                                    {{
                                        statusLabel(
                                            ticket.status
                                        )
                                    }}
                                </span>

                                <span
                                    class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
                                >
                                    {{
                                        categoryLabel(
                                            ticket.category
                                        )
                                    }}
                                </span>

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        channelClass(
                                            ticket.channel
                                        )
                                    "
                                >
                                    {{
                                        channelLabel(
                                            ticket.channel
                                        )
                                    }}
                                </span>

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="agentClass(ticket)"
                                >
                                    👤 {{ getAgentName(ticket) }}
                                </span>

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="slaClass(ticket)"
                                >
                                    ⏱ {{ slaLabel(ticket) }}
                                </span>
                            </div>

                            <div
                                class="mt-4 grid grid-cols-2 gap-3"
                            >
                                <div
                                    class="rounded-xl bg-gray-50 p-3"
                                >
                                    <p
                                        class="text-xs text-gray-500"
                                    >
                                        Canal
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-medium text-gray-800"
                                    >
                                        {{
                                            channelLabel(
                                                ticket.channel
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl bg-gray-50 p-3"
                                >
                                    <p
                                        class="text-xs text-gray-500"
                                    >
                                        Client
                                    </p>

                                    <p
                                        class="mt-1 truncate text-sm font-medium text-gray-800"
                                    >
                                        {{
                                            getClientContact(
                                                ticket
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex gap-3">
                                <Link
                                    :href="
                                        route(
                                            'tickets.show',
                                            ticket.id
                                        )
                                    "
                                    class="inline-flex flex-1 items-center justify-center rounded-xl bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100"
                                >
                                    Voir
                                </Link>

                                <Link
                                    :href="
                                        route(
                                            'tickets.edit',
                                            ticket.id
                                        )
                                    "
                                    class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                                >
                                    Modifier
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- PAGINATION -->
                    <div
                        v-if="paginationLinks.length > 3"
                        class="flex flex-wrap items-center justify-center gap-2 border-t border-gray-200 px-6 py-5"
                    >
                        <template
                            v-for="(link, index) in paginationLinks"
                            :key="index"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                class="rounded-lg border px-3 py-2 text-sm transition"
                                :class="
                                    link.active
                                        ? 'border-indigo-600 bg-indigo-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                "
                            >
                                <span v-html="link.label"></span>
                            </Link>

                            <span
                                v-else
                                class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400"
                            >
                                <span v-html="link.label"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- INFORMATION -->
                <div
                    class="mt-6 rounded-2xl border border-indigo-100 bg-indigo-50 p-5"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100"
                        >
                            🎫
                        </div>

                        <div>
                            <h3
                                class="font-semibold text-indigo-900"
                            >
                                Gestion des réclamations
                            </h3>

                            <p
                                class="mt-1 text-sm leading-6 text-indigo-800"
                            >
                                Les tickets permettent de centraliser les
                                demandes clients, d'attribuer chaque dossier
                                à un agent et de suivre les délais de
                                résolution.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
