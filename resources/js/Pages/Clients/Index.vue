<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";

import {
    Head,
    Link,
    router,
    usePage,
} from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps({
    clients: {
        type: Object,
        required: true,
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
        }),
    },

    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            today: 0,
            with_conversations: 0,
            without_conversations: 0,
        }),
    },
});

/*
|--------------------------------------------------------------------------
| Recherche
|--------------------------------------------------------------------------
*/

const search = ref(props.filters.search || "");

let searchTimeout = null;

watch(search, (value) => {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        router.get(
            route("clients.index"),
            {
                search: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    }, 400);
});

/*
|--------------------------------------------------------------------------
| Actualisation automatique
|--------------------------------------------------------------------------
*/

const refreshing = ref(false);
const deleting = ref(false);

let refreshInterval = null;

const refreshClients = () => {
    if (refreshing.value || deleting.value) {
        return;
    }

    refreshing.value = true;

    router.reload({
        only: [
            "clients",
            "statistics",
        ],

        preserveState: true,
        preserveScroll: true,

        onFinish: () => {
            refreshing.value = false;
        },
    });
};

onMounted(() => {
    refreshInterval = setInterval(() => {
        refreshClients();
    }, 5000);
});

onBeforeUnmount(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }

    clearTimeout(searchTimeout);
});

/*
|--------------------------------------------------------------------------
| Statistiques
|--------------------------------------------------------------------------
*/

const totalClients = computed(() => {
    return (
        props.statistics?.total ??
        props.clients?.total ??
        props.clients?.data?.length ??
        0
    );
});

const todayClients = computed(() => {
    return props.statistics?.today ?? 0;
});

const clientsWithConversations = computed(() => {
    return props.statistics?.with_conversations ?? 0;
});

const clientsWithoutConversations = computed(() => {
    return props.statistics?.without_conversations ?? 0;
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
| Suppression
|--------------------------------------------------------------------------
*/

const deleteClient = (client) => {
    if (deleting.value) {
        return;
    }

    const fullName = [
        client.first_name,
        client.last_name,
    ]
        .filter(Boolean)
        .join(" ");

    if (
        !confirm(
            `Voulez-vous vraiment supprimer ${fullName || "ce client"} ?`
        )
    ) {
        return;
    }

    deleting.value = true;

    router.delete(
        route("clients.destroy", client.id),
        {
            preserveScroll: true,

            onFinish: () => {
                deleting.value = false;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Initiale client
|--------------------------------------------------------------------------
*/

const getInitial = (client) => {
    const name =
        client?.first_name ||
        client?.last_name ||
        "?";

    return name
        .charAt(0)
        .toUpperCase();
};

/*
|--------------------------------------------------------------------------
| Nom complet
|--------------------------------------------------------------------------
*/

const getFullName = (client) => {
    const name = [
        client?.first_name,
        client?.last_name,
    ]
        .filter(Boolean)
        .join(" ");

    return name || "Client sans nom";
};

/*
|--------------------------------------------------------------------------
| Statut
|--------------------------------------------------------------------------
*/

const statusLabel = (status) => {
    const labels = {
        active: "Actif",
        inactive: "Inactif",
        blocked: "Bloqué",
    };

    return labels[status] ?? status ?? "Inconnu";
};

const statusClass = (status) => {
    const classes = {
        active:
            "bg-emerald-100 text-emerald-700",

        inactive:
            "bg-canvas-sunken text-night-600",

        blocked:
            "bg-rose-100 text-rose-700",
    };

    return (
        classes[status] ??
        "bg-canvas-sunken text-night-600"
    );
};

/*
|--------------------------------------------------------------------------
| Nombre de conversations
|--------------------------------------------------------------------------
*/

const conversationCount = (client) => {
    return Number(
        client?.conversations_count ?? 0
    );
};
</script>

<template>
    <Head title="Clients" />

    <AuthenticatedLayout>
        <!-- ==========================================================
             HEADER
        =========================================================== -->

        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-night-800"
                    >
                        Clients
                    </h2>

                    <p class="mt-1 text-sm text-night-400">
                        Gérez les clients et leur historique de conversations.
                    </p>
                </div>

                <Link
                    :href="route('clients.create')"
                    class="inline-flex items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600"
                >
                    + Nouveau client
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

                <!-- ======================================================
                     FLASH SUCCESS
                ======================================================= -->

                <div
                    v-if="successMessage"
                    class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700"
                >
                    ✅ {{ successMessage }}
                </div>

                <!-- ======================================================
                     FLASH ERROR
                ======================================================= -->

                <div
                    v-if="errorMessage"
                    class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700"
                >
                    ⚠️ {{ errorMessage }}
                </div>

                <!-- ======================================================
                     STATISTIQUES
                ======================================================= -->

                <div
                    class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >

                    <!-- Total -->

                    <div
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-line"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <div>
                                <p class="text-sm font-medium text-night-400">
                                    Total clients
                                </p>

                                <p
                                    class="mt-2 text-3xl font-bold text-night-900"
                                >
                                    {{ totalClients }}
                                </p>
                            </div>

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-xl"
                            >
                                👥
                            </div>
                        </div>
                    </div>

                    <!-- Aujourd'hui -->

                    <div
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-line"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <div>
                                <p class="text-sm font-medium text-night-400">
                                    Nouveaux aujourd’hui
                                </p>

                                <p
                                    class="mt-2 text-3xl font-bold text-emerald-600"
                                >
                                    {{ todayClients }}
                                </p>
                            </div>

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl"
                            >
                                ✨
                            </div>
                        </div>
                    </div>

                    <!-- Avec conversations -->

                    <div
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-line"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <div>
                                <p class="text-sm font-medium text-night-400">
                                    Avec conversations
                                </p>

                                <p
                                    class="mt-2 text-3xl font-bold text-blue-600"
                                >
                                    {{ clientsWithConversations }}
                                </p>
                            </div>

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-xl"
                            >
                                💬
                            </div>
                        </div>
                    </div>

                    <!-- Sans conversations -->

                    <div
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-line"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <div>
                                <p class="text-sm font-medium text-night-400">
                                    Sans conversations
                                </p>

                                <p
                                    class="mt-2 text-3xl font-bold text-orange-600"
                                >
                                    {{ clientsWithoutConversations }}
                                </p>
                            </div>

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-50 text-xl"
                            >
                                📭
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================================================
                     RECHERCHE
                ======================================================= -->

                <div
                    class="mb-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-line"
                >
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
                    >
                        <div class="w-full">
                            <label
                                for="search"
                                class="mb-2 block text-sm font-medium text-night-600"
                            >
                                Rechercher un client
                            </label>

                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-night-300"
                                >
                                    🔎
                                </span>

                                <input
                                    id="search"
                                    v-model="search"
                                    type="text"
                                    placeholder="Nom, téléphone, email ou entreprise..."
                                    class="w-full rounded-xl border-line-strong py-3 pl-11 pr-4 shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                />
                            </div>
                        </div>

                        <!-- Statut temps réel -->

                        <div
                            class="flex shrink-0 items-center"
                        >
                            <span
                                v-if="refreshing"
                                class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-xs font-medium text-brand-700"
                            >
                                <span
                                    class="h-2 w-2 animate-pulse rounded-full bg-brand-500"
                                ></span>

                                Actualisation...
                            </span>

                            <span
                                v-else
                                class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-4 py-2 text-xs font-medium text-emerald-700"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-emerald-500"
                                ></span>

                                Temps réel
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ======================================================
                     TABLEAU
                ======================================================= -->

                <div
                    class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-line"
                >

                    <!-- Entête -->

                    <div
                        class="flex flex-col gap-3 border-b border-line px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                class="text-lg font-bold text-night-900"
                            >
                                Liste des clients
                            </h2>

                            <p class="mt-1 text-sm text-night-400">
                                Vos clients et leur activité récente.
                            </p>
                        </div>

                        <span
                            class="rounded-full bg-brand-100 px-3 py-1 text-sm font-semibold text-brand-700"
                        >
                            {{
                                clients.total ??
                                clients.data?.length ??
                                0
                            }}
                        </span>
                    </div>

                    <!-- ==================================================
                         DESKTOP
                    =================================================== -->

                    <div class="hidden overflow-x-auto lg:block">
                        <table
                            class="min-w-full divide-y divide-line"
                        >
                            <thead class="bg-canvas-sunken">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                                    >
                                        Client
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                                    >
                                        Téléphone
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                                    >
                                        Email
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                                    >
                                        Entreprise
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                                    >
                                        Conversations
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                                    >
                                        Statut
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-night-400"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-line bg-white"
                            >
                                <!-- Aucun client -->

                                <tr
                                    v-if="!clients.data?.length"
                                >
                                    <td
                                        colspan="7"
                                        class="px-6 py-14 text-center"
                                    >
                                        <div class="text-5xl">
                                            👥
                                        </div>

                                        <h3
                                            class="mt-4 text-lg font-semibold text-night-900"
                                        >
                                            Aucun client trouvé
                                        </h3>

                                        <p
                                            class="mt-2 text-sm text-night-400"
                                        >
                                            Modifiez votre recherche ou
                                            créez un nouveau client.
                                        </p>

                                        <Link
                                            :href="route('clients.create')"
                                            class="mt-5 inline-flex rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600"
                                        >
                                            + Ajouter un client
                                        </Link>
                                    </td>
                                </tr>

                                <!-- Clients -->

                                <tr
                                    v-for="client in clients.data"
                                    :key="client.id"
                                    class="transition hover:bg-canvas-sunken"
                                >
                                    <!-- CLIENT -->

                                    <td class="px-6 py-4">
                                        <div
                                            class="flex items-center gap-3"
                                        >
                                            <div
                                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-100 font-bold text-brand-700"
                                            >
                                                {{
                                                    getInitial(client)
                                                }}
                                            </div>

                                            <div class="min-w-0">
                                                <div
                                                    class="font-semibold text-night-900"
                                                >
                                                    {{
                                                        getFullName(
                                                            client
                                                        )
                                                    }}
                                                </div>

                                                <div
                                                    class="mt-1 text-xs text-night-300"
                                                >
                                                    ID #{{ client.id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- TELEPHONE -->

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-sm text-night-500"
                                    >
                                        {{ client.phone || "—" }}
                                    </td>

                                    <!-- EMAIL -->

                                    <td
                                        class="px-6 py-4 text-sm text-night-500"
                                    >
                                        {{ client.email || "—" }}
                                    </td>

                                    <!-- ENTREPRISE -->

                                    <td
                                        class="px-6 py-4 text-sm text-night-500"
                                    >
                                        {{ client.company || "—" }}
                                    </td>

                                    <!-- CONVERSATIONS -->

                                    <td
                                        class="whitespace-nowrap px-6 py-4"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700"
                                        >
                                            💬
                                            {{
                                                conversationCount(
                                                    client
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <!-- STATUT -->

                                    <td
                                        class="whitespace-nowrap px-6 py-4"
                                    >
                                        <span
                                            class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold"
                                            :class="
                                                statusClass(
                                                    client.status
                                                )
                                            "
                                        >
                                            {{
                                                statusLabel(
                                                    client.status
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <!-- ACTIONS -->

                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-right text-sm"
                                    >
                                        <div
                                            class="flex justify-end gap-3"
                                        >
                                            <Link
                                                :href="
                                                    route(
                                                        'clients.show',
                                                        client.id
                                                    )
                                                "
                                                class="font-medium text-brand-600 transition hover:text-brand-900"
                                            >
                                                Voir
                                            </Link>

                                            <Link
                                                :href="
                                                    route(
                                                        'clients.edit',
                                                        client.id
                                                    )
                                                "
                                                class="font-medium text-blue-600 transition hover:text-blue-900"
                                            >
                                                Modifier
                                            </Link>

                                            <button
                                                type="button"
                                                :disabled="deleting"
                                                @click="
                                                    deleteClient(
                                                        client
                                                    )
                                                "
                                                class="font-medium text-rose-600 transition hover:text-rose-900 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ==================================================
                         MOBILE
                    =================================================== -->

                    <div
                        class="divide-y divide-line lg:hidden"
                    >
                        <div
                            v-if="!clients.data?.length"
                            class="px-6 py-14 text-center"
                        >
                            <div class="text-5xl">
                                👥
                            </div>

                            <h3
                                class="mt-4 text-lg font-semibold text-night-900"
                            >
                                Aucun client trouvé
                            </h3>

                            <p
                                class="mt-2 text-sm text-night-400"
                            >
                                Modifiez votre recherche ou créez
                                un nouveau client.
                            </p>

                            <Link
                                :href="route('clients.create')"
                                class="mt-5 inline-flex rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white"
                            >
                                + Ajouter un client
                            </Link>
                        </div>

                        <div
                            v-for="client in clients.data"
                            :key="client.id"
                            class="p-5"
                        >
                            <!-- Client -->

                            <div
                                class="flex items-start justify-between gap-4"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-100 font-bold text-brand-700"
                                    >
                                        {{ getInitial(client) }}
                                    </div>

                                    <div class="min-w-0">
                                        <Link
                                            :href="
                                                route(
                                                    'clients.show',
                                                    client.id
                                                )
                                            "
                                            class="font-semibold text-night-900 hover:text-brand-600"
                                        >
                                            {{
                                                getFullName(
                                                    client
                                                )
                                            }}
                                        </Link>

                                        <p
                                            class="mt-1 truncate text-xs text-night-400"
                                        >
                                            {{
                                                client.email ??
                                                "Aucun email"
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <span
                                    class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="
                                        statusClass(
                                            client.status
                                        )
                                    "
                                >
                                    {{
                                        statusLabel(
                                            client.status
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- Informations -->

                            <div
                                class="mt-4 grid grid-cols-2 gap-3"
                            >
                                <div
                                    class="rounded-xl bg-canvas-sunken p-3"
                                >
                                    <p
                                        class="text-xs text-night-400"
                                    >
                                        Téléphone
                                    </p>

                                    <p
                                        class="mt-1 truncate text-sm font-medium text-night-800"
                                    >
                                        {{
                                            client.phone ||
                                            "—"
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl bg-canvas-sunken p-3"
                                >
                                    <p
                                        class="text-xs text-night-400"
                                    >
                                        Entreprise
                                    </p>

                                    <p
                                        class="mt-1 truncate text-sm font-medium text-night-800"
                                    >
                                        {{
                                            client.company ||
                                            "—"
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl bg-blue-50 p-3"
                                >
                                    <p
                                        class="text-xs text-blue-600"
                                    >
                                        Conversations
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-bold text-blue-800"
                                    >
                                        {{
                                            conversationCount(
                                                client
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl bg-canvas-sunken p-3"
                                >
                                    <p
                                        class="text-xs text-night-400"
                                    >
                                        Identifiant
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-bold text-night-800"
                                    >
                                        #{{ client.id }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->

                            <div
                                class="mt-4 flex flex-wrap gap-2"
                            >
                                <Link
                                    :href="
                                        route(
                                            'clients.show',
                                            client.id
                                        )
                                    "
                                    class="rounded-lg bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100"
                                >
                                    Voir
                                </Link>

                                <Link
                                    :href="
                                        route(
                                            'clients.edit',
                                            client.id
                                        )
                                    "
                                    class="rounded-lg bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100"
                                >
                                    Modifier
                                </Link>

                                <button
                                    type="button"
                                    :disabled="deleting"
                                    @click="
                                        deleteClient(
                                            client
                                        )
                                    "
                                    class="rounded-lg bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ==================================================
                         PAGINATION
                    =================================================== -->

                    <div
                        v-if="clients.links?.length > 3"
                        class="flex flex-wrap items-center justify-center gap-2 border-t border-line px-6 py-5"
                    >
                        <template
                            v-for="(
                                link, index
                            ) in clients.links"
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
                                        ? 'border-brand-500 bg-brand-500 text-white'
                                        : 'border-line-strong bg-white text-night-600 hover:bg-canvas-sunken'
                                "
                            >
                                <span
                                    v-html="link.label"
                                ></span>
                            </Link>

                            <span
                                v-else
                                class="rounded-lg border border-line px-3 py-2 text-sm text-night-300"
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
                    class="mt-6 rounded-2xl border border-brand-100 bg-brand-50 p-5"
                >
                    <div
                        class="flex items-start gap-4"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-100"
                        >
                            ℹ️
                        </div>

                        <div>
                            <h3
                                class="font-semibold text-brand-900"
                            >
                                Gestion des clients
                            </h3>

                            <p
                                class="mt-1 text-sm leading-6 text-brand-800"
                            >
                                Les informations clients sont limitées
                                à votre organisation. Consultez la fiche
                                d’un client pour accéder à son historique
                                de conversations.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
