<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
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
| Agents
|--------------------------------------------------------------------------
*/

const agents = computed(() => {
    return (
        page.props.agents ?? {
            data: [],
            links: [],
            total: 0,
        }
    );
});

/*
|--------------------------------------------------------------------------
| Flash messages
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
| Chargement
|--------------------------------------------------------------------------
*/

const processing = ref(false);
const refreshing = ref(false);

/*
|--------------------------------------------------------------------------
| Statistiques
|--------------------------------------------------------------------------
*/

const totalAgents = computed(() => {
    return (
        agents.value.total ??
        agents.value.data?.length ??
        0
    );
});

const activeAgents = computed(() => {
    return (agents.value.data ?? []).filter(
        (agent) => agent.is_active
    ).length;
});

const inactiveAgents = computed(() => {
    return (agents.value.data ?? []).filter(
        (agent) => !agent.is_active
    ).length;
});

const totalAssignedConversations = computed(() => {
    return (agents.value.data ?? []).reduce(
        (total, agent) =>
            total +
            Number(agent.conversations_count ?? 0),
        0
    );
});

/*
|--------------------------------------------------------------------------
| Actualisation automatique
|--------------------------------------------------------------------------
*/

let refreshInterval = null;

const refreshAgents = () => {
    if (refreshing.value || processing.value) {
        return;
    }

    refreshing.value = true;

    router.reload({
        only: ["agents"],
        preserveState: true,
        preserveScroll: true,

        onFinish: () => {
            refreshing.value = false;
        },
    });
};

onMounted(() => {
    refreshInterval = setInterval(() => {
        refreshAgents();
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
| Activer / Désactiver
|--------------------------------------------------------------------------
*/

const toggleAgent = (agent) => {
    if (processing.value) {
        return;
    }

    const action = agent.is_active
        ? "désactiver"
        : "activer";

    if (
        !confirm(
            `Voulez-vous ${action} ${agent.name} ?`
        )
    ) {
        return;
    }

    processing.value = true;

    router.patch(
        route("agents.toggle", agent.id),
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                processing.value = false;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Supprimer
|--------------------------------------------------------------------------
*/

const deleteAgent = (agent) => {
    if (processing.value) {
        return;
    }

    if (
        !confirm(
            `Voulez-vous vraiment supprimer l'agent ${agent.name} ?`
        )
    ) {
        return;
    }

    processing.value = true;

    router.delete(
        route("agents.destroy", agent.id),
        {
            preserveScroll: true,

            onFinish: () => {
                processing.value = false;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Initiale
|--------------------------------------------------------------------------
*/

const getInitial = (name) => {
    if (!name) {
        return "?";
    }

    return name
        .trim()
        .charAt(0)
        .toUpperCase();
};

/*
|--------------------------------------------------------------------------
| Rôle
|--------------------------------------------------------------------------
*/

const roleLabel = (role) => {
    return role === "owner"
        ? "Responsable"
        : "Agent";
};

const roleClass = (role) => {
    return role === "owner"
        ? "bg-purple-100 text-purple-700"
        : "bg-blue-100 text-blue-700";
};
</script>

<template>
    <Head title="Gestion des agents" />

    <div class="min-h-screen bg-gray-50">

        <!-- ==========================================================
             NAVIGATION
        =========================================================== -->

        <nav class="bg-white border-b border-gray-200">
            <div
                class="flex items-center justify-between px-4 py-4 mx-auto max-w-7xl sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-6 overflow-x-auto">
                    <Link
                        :href="route('dashboard')"
                        class="text-xl font-bold text-gray-900 whitespace-nowrap"
                    >
                        AI Service Client
                    </Link>

                    <Link
                        :href="route('dashboard')"
                        class="text-sm font-medium text-gray-600 transition whitespace-nowrap hover:text-indigo-600"
                    >
                        Tableau de bord
                    </Link>

                    <Link
                        :href="route('conversations.index')"
                        class="text-sm font-medium text-gray-600 transition whitespace-nowrap hover:text-indigo-600"
                    >
                        Conversations
                    </Link>

                    <Link
                        :href="route('agents.index')"
                        class="text-sm font-semibold text-indigo-600 whitespace-nowrap"
                    >
                        Agents
                    </Link>

                    <Link
                        :href="route('clients.index')"
                        class="text-sm font-medium text-gray-600 transition whitespace-nowrap hover:text-indigo-600"
                    >
                        Clients
                    </Link>
                </div>

                <div class="items-center hidden gap-4 ml-4 sm:flex">
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
                        class="px-4 py-2 text-sm font-medium text-white transition bg-gray-900 rounded-lg hover:bg-gray-700"
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
            class="px-4 py-8 mx-auto max-w-7xl sm:px-6 lg:px-8"
        >

            <!-- EN-TÊTE -->

            <div
                class="flex flex-col gap-4 mb-8 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        Gestion des agents
                    </h1>

                    <p class="mt-2 text-gray-500">
                        Gérez les membres responsables du service client.
                    </p>
                </div>

                <Link
                    :href="route('agents.create')"
                    class="inline-flex items-center justify-center px-5 py-3 text-sm font-semibold text-white transition bg-indigo-600 shadow-sm rounded-xl hover:bg-indigo-700"
                >
                    + Ajouter un agent
                </Link>
            </div>

            <!-- ======================================================
                 FLASH SUCCESS
            ======================================================= -->

            <div
                v-if="successMessage"
                class="px-5 py-4 mb-6 text-sm font-medium border rounded-xl border-emerald-200 bg-emerald-50 text-emerald-700"
            >
                ✅ {{ successMessage }}
            </div>

            <!-- ======================================================
                 FLASH ERROR
            ======================================================= -->

            <div
                v-if="errorMessage"
                class="px-5 py-4 mb-6 text-sm font-medium text-red-700 border border-red-200 rounded-xl bg-red-50"
            >
                ⚠️ {{ errorMessage }}
            </div>

            <!-- ======================================================
                 STATISTIQUES
            ======================================================= -->

            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-4">

                <!-- Total -->

                <div
                    class="p-5 bg-white shadow-sm rounded-2xl ring-1 ring-gray-100"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Total agents
                            </p>

                            <p class="mt-2 text-3xl font-bold text-gray-900">
                                {{ totalAgents }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-center text-xl h-11 w-11 rounded-xl bg-indigo-50"
                        >
                            👥
                        </div>
                    </div>
                </div>

                <!-- Actifs -->

                <div
                    class="p-5 bg-white shadow-sm rounded-2xl ring-1 ring-gray-100"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Agents actifs
                            </p>

                            <p class="mt-2 text-3xl font-bold text-emerald-600">
                                {{ activeAgents }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-center text-xl h-11 w-11 rounded-xl bg-emerald-50"
                        >
                            ✅
                        </div>
                    </div>
                </div>

                <!-- Inactifs -->

                <div
                    class="p-5 bg-white shadow-sm rounded-2xl ring-1 ring-gray-100"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Agents inactifs
                            </p>

                            <p class="mt-2 text-3xl font-bold text-red-600">
                                {{ inactiveAgents }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-center text-xl h-11 w-11 rounded-xl bg-red-50"
                        >
                            ⛔
                        </div>
                    </div>
                </div>

                <!-- Conversations -->

                <div
                    class="p-5 bg-white shadow-sm rounded-2xl ring-1 ring-gray-100"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Conversations attribuées
                            </p>

                            <p class="mt-2 text-3xl font-bold text-orange-600">
                                {{ totalAssignedConversations }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-center text-xl h-11 w-11 rounded-xl bg-orange-50"
                        >
                            💬
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================
                 LISTE
            ======================================================= -->

            <div
                class="overflow-hidden bg-white shadow-sm rounded-2xl ring-1 ring-gray-100"
            >

                <!-- ENTÊTE -->

                <div
                    class="flex flex-col gap-3 px-6 py-5 border-b border-gray-200 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">
                            Membres de l’équipe
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Agents et responsables de votre organisation.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <span
                            v-if="refreshing"
                            class="inline-flex items-center gap-2 px-3 py-1 text-xs font-medium text-indigo-700 rounded-full bg-indigo-50"
                        >
                            <span
                                class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"
                            ></span>

                            Actualisation...
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-2 px-3 py-1 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700"
                        >
                            <span
                                class="w-2 h-2 rounded-full bg-emerald-500"
                            ></span>

                            Temps réel
                        </span>

                        <span
                            class="px-3 py-1 text-sm font-semibold text-indigo-700 bg-indigo-100 rounded-full"
                        >
                            {{ totalAgents }}
                        </span>
                    </div>
                </div>

                <!-- AUCUN AGENT -->

                <div
                    v-if="!agents.data?.length"
                    class="px-6 py-16 text-center"
                >
                    <div class="text-5xl">
                        👥
                    </div>

                    <h3
                        class="mt-4 text-lg font-semibold text-gray-900"
                    >
                        Aucun agent
                    </h3>

                    <p class="mt-2 text-sm text-gray-500">
                        Commencez par ajouter un agent à votre équipe.
                    </p>

                    <Link
                        :href="route('agents.create')"
                        class="mt-5 inline-flex rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                    >
                        Ajouter le premier agent
                    </Link>
                </div>

                <!-- AGENTS -->

                <div
                    v-else
                    class="divide-y divide-gray-100"
                >
                    <div
                        v-for="agent in agents.data"
                        :key="agent.id"
                        class="px-6 py-6 transition hover:bg-gray-50"
                    >
                        <div
                            class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between"
                        >

                            <!-- INFORMATIONS -->

                            <div class="flex items-center min-w-0 gap-4">
                                <div
                                    class="flex items-center justify-center text-lg font-bold text-indigo-700 bg-indigo-100 rounded-full h-14 w-14 shrink-0"
                                >
                                    {{ getInitial(agent.name) }}
                                </div>

                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="font-semibold text-gray-900"
                                        >
                                            {{ agent.name }}
                                        </h3>

                                        <span
                                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="roleClass(agent.role)"
                                        >
                                            {{ roleLabel(agent.role) }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-1 text-sm text-gray-500 truncate"
                                    >
                                        {{ agent.email }}
                                    </p>

                                    <p class="mt-2 text-xs text-gray-400">
                                        ID utilisateur :
                                        {{ agent.id }}
                                    </p>
                                </div>
                            </div>

                            <!-- STATISTIQUES -->

                            <div
                                class="flex flex-wrap items-center gap-3"
                            >
                                <div
                                    class="px-4 py-3 rounded-xl bg-gray-50"
                                >
                                    <p class="text-xs text-gray-500">
                                        Conversations
                                    </p>

                                    <p
                                        class="mt-1 text-lg font-bold text-gray-900"
                                    >
                                        {{ agent.conversations_count ?? 0 }}
                                    </p>
                                </div>

                                <span
                                    v-if="agent.is_active"
                                    class="rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-medium text-emerald-700"
                                >
                                    ● Actif
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-red-100 px-3 py-1.5 text-xs font-medium text-red-700"
                                >
                                    ● Inactif
                                </span>
                            </div>

                            <!-- ACTIONS -->

                            <div class="flex flex-wrap gap-2">

                                <Link
                                    :href="
                                        route(
                                            'agents.edit',
                                            agent.id
                                        )
                                    "
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    Modifier
                                </Link>

                                <button
                                    v-if="agent.role === 'agent'"
                                    type="button"
                                    :disabled="processing"
                                    @click="toggleAgent(agent)"
                                    class="rounded-lg border px-4 py-2.5 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="
                                        agent.is_active
                                            ? 'border-yellow-300 bg-yellow-50 text-yellow-700 hover:bg-yellow-100'
                                            : 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                    "
                                >
                                    {{
                                        agent.is_active
                                            ? "Désactiver"
                                            : "Activer"
                                    }}
                                </button>

                                <button
                                    v-if="agent.role === 'agent'"
                                    type="button"
                                    :disabled="processing"
                                    @click="deleteAgent(agent)"
                                    class="rounded-lg border border-red-300 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================================================
                     PAGINATION
                =================================================== -->

                <div
                    v-if="agents.links?.length > 3"
                    class="flex flex-wrap items-center justify-center gap-2 px-6 py-5 border-t border-gray-200"
                >
                    <template
                        v-for="(link, index) in agents.links"
                        :key="index"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                            class="px-3 py-2 text-sm border rounded-lg"
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
                            class="px-3 py-2 text-sm text-gray-400 border border-gray-200 rounded-lg"
                        >
                            <span
                                v-html="link.label"
                            ></span>
                        </span>
                    </template>
                </div>
            </div>

            <!-- ======================================================
                 INFORMATION
            ======================================================= -->

            <div
                class="p-5 mt-6 border border-indigo-100 rounded-2xl bg-indigo-50"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex items-center justify-center w-10 h-10 bg-indigo-100 shrink-0 rounded-xl"
                    >
                        ℹ️
                    </div>

                    <div>
                        <h3 class="font-semibold text-indigo-900">
                            Gestion des agents
                        </h3>

                        <p class="mt-1 text-sm leading-6 text-indigo-800">
                            Un agent désactivé ne pourra plus être utilisé
                            pour les nouvelles affectations. Les conversations
                            déjà attribuées restent conservées.
                        </p>
                    </div>
                </div>
            </div>

        </main>
    </div>
</template>
