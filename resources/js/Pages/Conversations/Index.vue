<script setup>
import {
    computed,
    ref,
    onMounted,
    onBeforeUnmount,
} from "vue";

import {
    Head,
    Link,
    router,
    usePage,
} from "@inertiajs/vue3";

const page = usePage();

/*
|--------------------------------------------------------------------------
| Conversations
|--------------------------------------------------------------------------
*/

const conversations = computed(() => {
    return (
        page.props.conversations ?? {
            data: [],
            links: [],
            meta: {},
            total: 0,
        }
    );
});

/*
|--------------------------------------------------------------------------
| Agents
|--------------------------------------------------------------------------
*/

const agents = computed(() => {
    return page.props.agents ?? [];
});

/*
|--------------------------------------------------------------------------
| Filtres
|--------------------------------------------------------------------------
*/

const filters = computed(() => {
    return page.props.filters ?? {};
});

const search = ref(filters.value.search ?? "");

const status = ref(filters.value.status ?? "");

const priority = ref(filters.value.priority ?? "");

const aiEnabled = ref(filters.value.ai_enabled ?? "");

const assignedTo = ref(filters.value.assigned_to ?? "");

/*
|--------------------------------------------------------------------------
| Actualisation automatique
|--------------------------------------------------------------------------
*/

let refreshInterval = null;

const refreshing = ref(false);

const refreshConversations = () => {
    if (refreshing.value) {
        return;
    }

    refreshing.value = true;

    router.reload({
        only: ["conversations"],
        preserveState: true,
        preserveScroll: true,

        onFinish: () => {
            refreshing.value = false;
        },
    });
};

onMounted(() => {
    refreshInterval = setInterval(() => {
        refreshConversations();
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
| Appliquer les filtres
|--------------------------------------------------------------------------
*/

const applyFilters = () => {
    router.get(
        route("conversations.index"),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            priority: priority.value || undefined,
            ai_enabled: aiEnabled.value || undefined,
            assigned_to: assignedTo.value || undefined,
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
    aiEnabled.value = "";
    assignedTo.value = "";

    router.get(
        route("conversations.index"),
        {},
        {
            preserveState: false,
            replace: true,
        }
    );
};

/*
|--------------------------------------------------------------------------
| Initiale client
|--------------------------------------------------------------------------
*/

const getInitial = (name) => {
    return name?.charAt(0)?.toUpperCase() ?? "?";
};

/*
|--------------------------------------------------------------------------
| Statut
|--------------------------------------------------------------------------
*/

const statusLabel = (value) => {
    const labels = {
        open: "Ouverte",
        pending: "En attente",
        resolved: "Résolue",
        closed: "Fermée",
    };

    return labels[value] ?? value;
};

const statusClass = (value) => {
    const classes = {
        open: "bg-green-100 text-green-700",
        pending: "bg-yellow-100 text-yellow-700",
        resolved: "bg-blue-100 text-blue-700",
        closed: "bg-gray-100 text-gray-600",
    };

    return classes[value] ?? "bg-gray-100 text-gray-600";
};

/*
|--------------------------------------------------------------------------
| Priorité
|--------------------------------------------------------------------------
*/

const priorityLabel = (value) => {
    const labels = {
        low: "Faible",
        normal: "Normale",
        high: "Élevée",
        urgent: "Urgente",
    };

    return labels[value] ?? value;
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
| Canal
|--------------------------------------------------------------------------
*/

const channelLabel = (value) => {
    const labels = {
        web: "Web",
        whatsapp: "WhatsApp",
        email: "Email",
        phone: "Téléphone",
        widget: "Widget",
        chat: "Chat",
        social: "Réseaux sociaux",
    };

    return labels[value] ?? value;
};

const channelClass = (value) => {
    const classes = {
        web: "bg-indigo-100 text-indigo-700",
        whatsapp: "bg-green-100 text-green-700",
        email: "bg-purple-100 text-purple-700",
        phone: "bg-orange-100 text-orange-700",
        widget: "bg-cyan-100 text-cyan-700",
        chat: "bg-sky-100 text-sky-700",
        social: "bg-pink-100 text-pink-700",
    };

    return classes[value] ?? "bg-gray-100 text-gray-600";
};

/*
|--------------------------------------------------------------------------
| Agent
|--------------------------------------------------------------------------
*/

const getAgentName = (conversation) => {
    return (
        conversation.assigned_agent?.name ??
        conversation.assignedAgent?.name ??
        "Non attribuée"
    );
};

const getAgentBadgeClass = (conversation) => {
    if (
        conversation.assigned_agent?.name ||
        conversation.assignedAgent?.name
    ) {
        return "bg-emerald-100 text-emerald-700";
    }

    return "bg-gray-100 text-gray-500";
};

/*
|--------------------------------------------------------------------------
| Total
|--------------------------------------------------------------------------
*/

const conversationsTotal = computed(() => {
    return (
        conversations.value.total ??
        conversations.value.meta?.total ??
        conversations.value.data?.length ??
        0
    );
});
</script>

<template>
    <Head title="Conversations" />

    <div class="min-h-screen bg-gray-50">
        <!-- ==========================================================
             NAVIGATION
        =========================================================== -->

        <nav class="border-b border-gray-200 bg-white">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8"
            >
                <div
                    class="flex items-center gap-6 overflow-x-auto"
                >
                    <Link
                        :href="route('dashboard')"
                        class="whitespace-nowrap text-xl font-bold text-gray-900"
                    >
                        AI Service Client
                    </Link>

                    <Link
                        :href="route('dashboard')"
                        class="whitespace-nowrap text-sm font-medium text-gray-600 transition hover:text-indigo-600"
                    >
                        Tableau de bord
                    </Link>

                    <Link
                        :href="route('conversations.index')"
                        class="whitespace-nowrap text-sm font-semibold text-indigo-600"
                    >
                        Conversations
                    </Link>

                    <Link
                        :href="route('clients.index')"
                        class="whitespace-nowrap text-sm font-medium text-gray-600 transition hover:text-indigo-600"
                    >
                        Clients
                    </Link>

                    <Link
                        :href="route('agents.index')"
                        class="whitespace-nowrap text-sm font-medium text-gray-600 transition hover:text-indigo-600"
                    >
                        Agents
                    </Link>
                </div>

                <div
                    class="ml-4 hidden shrink-0 items-center gap-4 sm:flex"
                >
                    <span class="text-sm text-gray-600">
                        {{
                            $page.props.auth?.user?.name ??
                            "Administrateur"
                        }}
                    </span>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700"
                    >
                        Déconnexion
                    </Link>
                </div>
            </div>
        </nav>

        <!-- ==========================================================
             CONTENU
        =========================================================== -->

        <main
            class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"
        >
            <!-- ======================================================
                 EN-TÊTE
            ======================================================= -->

            <div
                class="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1
                        class="text-3xl font-bold text-gray-900"
                    >
                        Conversations
                    </h1>

                    <p class="mt-2 text-gray-500">
                        Gérez les demandes et échanges avec vos clients.
                    </p>
                </div>

                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-gray-700"
                >
                    ← Tableau de bord
                </Link>
            </div>

            <!-- ======================================================
                 FILTRES
            ======================================================= -->

            <div
                class="mb-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
            >
                <div class="mb-4">
                    <h2
                        class="text-base font-semibold text-gray-900"
                    >
                        Rechercher et filtrer
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Affinez la liste des conversations.
                    </p>
                </div>

                <div
                    class="grid gap-4 md:grid-cols-2 lg:grid-cols-5"
                >
                    <!-- RECHERCHE -->

                    <div>
                        <label
                            for="search"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Recherche
                        </label>

                        <input
                            id="search"
                            v-model="search"
                            type="text"
                            placeholder="Client, email ou sujet..."
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- STATUT -->

                    <div>
                        <label
                            for="status"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Statut
                        </label>

                        <select
                            id="status"
                            v-model="status"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">
                                Tous les statuts
                            </option>

                            <option value="open">
                                Ouverte
                            </option>

                            <option value="pending">
                                En attente
                            </option>

                            <option value="resolved">
                                Résolue
                            </option>

                            <option value="closed">
                                Fermée
                            </option>
                        </select>
                    </div>

                    <!-- PRIORITÉ -->

                    <div>
                        <label
                            for="priority"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Priorité
                        </label>

                        <select
                            id="priority"
                            v-model="priority"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">
                                Toutes les priorités
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

                    <!-- IA -->

                    <div>
                        <label
                            for="ai_enabled"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Intelligence artificielle
                        </label>

                        <select
                            id="ai_enabled"
                            v-model="aiEnabled"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">
                                Toutes
                            </option>

                            <option value="1">
                                IA active
                            </option>

                            <option value="0">
                                IA désactivée
                            </option>
                        </select>
                    </div>

                    <!-- AGENT -->

                    <div>
                        <label
                            for="assigned_to"
                            class="mb-1 block text-sm font-medium text-gray-700"
                        >
                            Agent
                        </label>

                        <select
                            id="assigned_to"
                            v-model="assignedTo"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">
                                Tous les agents
                            </option>

                            <option
                                v-for="agent in agents"
                                :key="agent.id"
                                :value="agent.id"
                            >
                                {{ agent.name }}
                                {{
                                    agent.role === "owner"
                                        ? " (Responsable)"
                                        : ""
                                }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- BOUTONS -->

                <div class="mt-5 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                        @click="applyFilters"
                    >
                        🔎 Rechercher
                    </button>

                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        @click="resetFilters"
                    >
                        Réinitialiser
                    </button>
                </div>
            </div>

            <!-- ======================================================
                 LISTE
            ======================================================= -->

            <div
                class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100"
            >
                <!-- ENTÊTE -->

                <div
                    class="flex flex-col gap-3 border-b border-gray-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-lg font-bold text-gray-900"
                        >
                            Liste des conversations
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Les échanges de votre organisation
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
                            {{ conversationsTotal }}
                        </span>
                    </div>
                </div>

                <!-- AUCUNE CONVERSATION -->

                <div
                    v-if="!conversations.data?.length"
                    class="px-6 py-16 text-center"
                >
                    <div class="text-5xl">
                        💬
                    </div>

                    <h3
                        class="mt-4 text-lg font-semibold text-gray-900"
                    >
                        Aucune conversation trouvée
                    </h3>

                    <p class="mt-2 text-sm text-gray-500">
                        Essayez de modifier vos critères de recherche.
                    </p>
                </div>

                <!-- CONVERSATIONS -->

                <div
                    v-else
                    class="divide-y divide-gray-100"
                >
                    <Link
                        v-for="conversation in conversations.data"
                        :key="conversation.id"
                        :href="
                            route(
                                'conversations.show',
                                conversation.id
                            )
                        "
                        class="block px-6 py-5 transition hover:bg-gray-50"
                    >
                        <div
                            class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <!-- CLIENT -->

                            <div
                                class="flex min-w-0 items-center gap-4"
                            >
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700"
                                >
                                    {{
                                        getInitial(
                                            conversation.client?.name
                                        )
                                    }}
                                </div>

                                <div class="min-w-0">
                                    <h3
                                        class="truncate font-semibold text-gray-900"
                                    >
                                        {{
                                            conversation.client?.name ??
                                            "Client inconnu"
                                        }}
                                    </h3>

                                    <p
                                        class="mt-1 truncate text-sm font-medium text-gray-700"
                                    >
                                        {{
                                            conversation.subject ??
                                            "Sans sujet"
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            conversation.client?.email
                                        "
                                        class="mt-1 truncate text-xs text-gray-400"
                                    >
                                        {{
                                            conversation.client.email
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- BADGES -->

                            <div
                                class="flex flex-wrap items-center gap-2 lg:justify-end"
                            >
                                <!-- CANAL -->

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        channelClass(
                                            conversation.channel
                                        )
                                    "
                                >
                                    {{
                                        channelLabel(
                                            conversation.channel
                                        )
                                    }}
                                </span>

                                <!-- STATUT -->

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        statusClass(
                                            conversation.status
                                        )
                                    "
                                >
                                    {{
                                        statusLabel(
                                            conversation.status
                                        )
                                    }}
                                </span>

                                <!-- PRIORITÉ -->

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        priorityClass(
                                            conversation.priority
                                        )
                                    "
                                >
                                    {{
                                        priorityLabel(
                                            conversation.priority
                                        )
                                    }}
                                </span>

                                <!-- IA -->

                                <span
                                    v-if="
                                        conversation.ai_enabled
                                    "
                                    class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-700"
                                >
                                    🤖 IA active
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500"
                                >
                                    IA désactivée
                                </span>

                                <!-- AGENT -->

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        getAgentBadgeClass(
                                            conversation
                                        )
                                    "
                                >
                                    👤
                                    {{ getAgentName(conversation) }}
                                </span>
                            </div>
                        </div>

                        <!-- DATE -->

                        <div
                            class="mt-4 flex flex-col gap-1 text-xs text-gray-400 sm:flex-row sm:justify-end sm:gap-2"
                        >
                            <span>
                                Dernière activité :
                            </span>

                            <span>
                                {{
                                    conversation.last_message_at ??
                                    "Aucune activité"
                                }}
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- PAGINATION -->

                <div
                    v-if="
                        conversations.links?.length > 3
                    "
                    class="flex flex-wrap items-center justify-center gap-2 border-t border-gray-200 px-6 py-5"
                >
                    <template
                        v-for="(
                            link, index
                        ) in conversations.links"
                        :key="index"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="
                                link.active
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                            "
                        >
                            <span
                                v-html="link.label"
                            ></span>
                        </Link>

                        <span
                            v-else
                            class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400"
                        >
                            <span
                                v-html="link.label"
                            ></span>
                        </span>
                    </template>
                </div>
            </div>
        </main>
    </div>
</template>
