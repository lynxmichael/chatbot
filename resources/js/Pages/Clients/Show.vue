<script setup>
import { computed } from "vue";

import {
    Head,
    Link,
} from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    client: {
        type: Object,
        required: true,
    },
});

/*
|--------------------------------------------------------------------------
| Nom complet
|--------------------------------------------------------------------------
*/

const fullName = computed(() => {
    return [
        props.client.first_name,
        props.client.last_name,
    ]
        .filter(Boolean)
        .join(" ") || "Client sans nom";
});

/*
|--------------------------------------------------------------------------
| Initiales
|--------------------------------------------------------------------------
*/

const initials = computed(() => {
    const first =
        props.client.first_name?.charAt(0) ?? "";

    const last =
        props.client.last_name?.charAt(0) ?? "";

    const result = `${first}${last}`.trim();

    return result
        ? result.toUpperCase()
        : "?";
});

/*
|--------------------------------------------------------------------------
| Conversations
|--------------------------------------------------------------------------
*/

const conversations = computed(() => {
    return props.client.conversations ?? [];
});

const conversationsCount = computed(() => {
    return (
        props.client.conversations_count ??
        conversations.value.length ??
        0
    );
});

const openConversationsCount = computed(() => {
    return conversations.value.filter(
        (conversation) =>
            conversation.status === "open"
    ).length;
});

const resolvedConversationsCount = computed(() => {
    return conversations.value.filter(
        (conversation) =>
            conversation.status === "resolved"
    ).length;
});

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
| Conversation
|--------------------------------------------------------------------------
*/

const conversationStatusLabel = (status) => {
    const labels = {
        open: "Ouverte",
        pending: "En attente",
        resolved: "Résolue",
        closed: "Fermée",
    };

    return labels[status] ?? status ?? "—";
};

const conversationStatusClass = (status) => {
    const classes = {
        open:
            "bg-emerald-100 text-emerald-700",

        pending:
            "bg-amber-100 text-amber-700",

        resolved:
            "bg-blue-100 text-blue-700",

        closed:
            "bg-canvas-sunken text-night-600",
    };

    return (
        classes[status] ??
        "bg-canvas-sunken text-night-600"
    );
};

const priorityLabel = (priority) => {
    const labels = {
        low: "Faible",
        normal: "Normale",
        high: "Élevée",
        urgent: "Urgente",
    };

    return labels[priority] ?? priority ?? "—";
};

const priorityClass = (priority) => {
    const classes = {
        low:
            "bg-canvas-sunken text-night-600",

        normal:
            "bg-blue-100 text-blue-700",

        high:
            "bg-orange-100 text-orange-700",

        urgent:
            "bg-rose-100 text-rose-700",
    };

    return (
        classes[priority] ??
        "bg-canvas-sunken text-night-600"
    );
};

const channelLabel = (channel) => {
    const labels = {
        web: "Web",
        widget: "Widget",
        whatsapp: "WhatsApp",
        email: "Email",
        phone: "Téléphone",
        chat: "Chat",
        social: "Réseaux sociaux",
    };

    return labels[channel] ?? channel ?? "—";
};

/*
|--------------------------------------------------------------------------
| Date
|--------------------------------------------------------------------------
*/

const formatDate = (date) => {
    if (!date) {
        return "—";
    }

    return date;
};
</script>

<template>
    <Head :title="fullName" />

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
                        class="text-xl font-semibold text-night-800"
                    >
                        Fiche client
                    </h2>

                    <p class="mt-1 text-sm text-night-400">
                        Informations et historique du client.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">

                    <Link
                        :href="
                            route(
                                'clients.edit',
                                client.id
                            )
                        "
                        class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600"
                    >
                        Modifier
                    </Link>

                    <Link
                        :href="route('clients.index')"
                        class="rounded-xl border border-line-strong bg-white px-4 py-2.5 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                    >
                        ← Retour aux clients
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div
                class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8"
            >

                <!-- ==================================================
                     PROFIL
                =================================================== -->

                <div
                    class="overflow-hidden bg-white shadow-sm rounded-2xl ring-1 ring-line"
                >
                    <div
                        class="px-6 py-8 border-b border-line bg-gradient-to-r from-brand-50 to-white sm:px-8"
                    >
                        <div
                            class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between"
                        >

                            <!-- Identité -->

                            <div
                                class="flex items-center gap-5"
                            >
                                <div
                                    class="flex items-center justify-center w-20 h-20 text-2xl font-bold text-brand-700 bg-brand-100 rounded-full shrink-0 ring-4 ring-white"
                                >
                                    {{ initials }}
                                </div>

                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-3"
                                    >
                                        <h1
                                            class="text-2xl font-bold text-night-900"
                                        >
                                            {{ fullName }}
                                        </h1>

                                        <span
                                            class="inline-flex px-3 py-1 text-xs font-semibold rounded-full"
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

                                    <p
                                        class="mt-2 text-sm text-night-400"
                                    >
                                        Client #{{ client.id }}
                                    </p>

                                    <p
                                        v-if="client.company"
                                        class="mt-1 text-sm font-medium text-night-600"
                                    >
                                        {{ client.company }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->

                            <div class="flex flex-wrap gap-2">

                                <a
                                    v-if="client.phone"
                                    :href="`tel:${client.phone}`"
                                    class="rounded-xl border border-line-strong bg-white px-4 py-2.5 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                                >
                                    📞 Appeler
                                </a>

                                <a
                                    v-if="client.email"
                                    :href="`mailto:${client.email}`"
                                    class="rounded-xl border border-line-strong bg-white px-4 py-2.5 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                                >
                                    ✉️ Email
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- ==================================================
                         INFORMATIONS
                    =================================================== -->

                    <div class="p-6 sm:p-8">

                        <h2
                            class="mb-5 text-lg font-semibold text-night-900"
                        >
                            Informations personnelles
                        </h2>

                        <div
                            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                        >

                            <!-- Téléphone -->

                            <div
                                class="p-4 rounded-xl bg-canvas-sunken"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-night-400 uppercase"
                                >
                                    Téléphone
                                </p>

                                <p
                                    class="mt-2 font-semibold text-night-900"
                                >
                                    {{
                                        client.phone ||
                                        "Non renseigné"
                                    }}
                                </p>
                            </div>

                            <!-- Email -->

                            <div
                                class="p-4 rounded-xl bg-canvas-sunken"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-night-400 uppercase"
                                >
                                    Email
                                </p>

                                <p
                                    class="mt-2 font-semibold text-night-900 break-all"
                                >
                                    {{
                                        client.email ||
                                        "Non renseigné"
                                    }}
                                </p>
                            </div>

                            <!-- Entreprise -->

                            <div
                                class="p-4 rounded-xl bg-canvas-sunken"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-night-400 uppercase"
                                >
                                    Entreprise
                                </p>

                                <p
                                    class="mt-2 font-semibold text-night-900"
                                >
                                    {{
                                        client.company ||
                                        "Non renseignée"
                                    }}
                                </p>
                            </div>

                            <!-- Ville -->

                            <div
                                class="p-4 rounded-xl bg-canvas-sunken"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-night-400 uppercase"
                                >
                                    Ville
                                </p>

                                <p
                                    class="mt-2 font-semibold text-night-900"
                                >
                                    {{
                                        client.city ||
                                        "Non renseignée"
                                    }}
                                </p>
                            </div>

                            <!-- Pays -->

                            <div
                                class="p-4 rounded-xl bg-canvas-sunken"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-night-400 uppercase"
                                >
                                    Pays
                                </p>

                                <p
                                    class="mt-2 font-semibold text-night-900"
                                >
                                    {{
                                        client.country ||
                                        "Non renseigné"
                                    }}
                                </p>
                            </div>

                            <!-- Adresse -->

                            <div
                                class="p-4 rounded-xl bg-canvas-sunken"
                            >
                                <p
                                    class="text-xs font-medium tracking-wide text-night-400 uppercase"
                                >
                                    Adresse
                                </p>

                                <p
                                    class="mt-2 font-semibold text-night-900"
                                >
                                    {{
                                        client.address ||
                                        "Non renseignée"
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Notes -->

                        <div
                            class="p-5 mt-6 bg-white border border-line rounded-xl"
                        >
                            <p
                                class="text-xs font-medium tracking-wide text-night-400 uppercase"
                            >
                                Notes
                            </p>

                            <p
                                class="mt-3 text-sm leading-6 text-night-600 whitespace-pre-line"
                            >
                                {{
                                    client.notes ||
                                    "Aucune note enregistrée pour ce client."
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ==================================================
                     STATISTIQUES
                =================================================== -->

                <div
                    class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-3"
                >

                    <div
                        class="p-5 bg-white shadow-sm rounded-2xl ring-1 ring-line"
                    >
                        <p class="text-sm text-night-400">
                            Conversations
                        </p>

                        <p
                            class="mt-2 text-3xl font-bold text-brand-600"
                        >
                            {{ conversationsCount }}
                        </p>

                        <p class="mt-1 text-xs text-night-300">
                            Historique total
                        </p>
                    </div>

                    <div
                        class="p-5 bg-white shadow-sm rounded-2xl ring-1 ring-line"
                    >
                        <p class="text-sm text-night-400">
                            Conversations ouvertes
                        </p>

                        <p
                            class="mt-2 text-3xl font-bold text-emerald-600"
                        >
                            {{ openConversationsCount }}
                        </p>

                        <p class="mt-1 text-xs text-night-300">
                            Nécessitent potentiellement une action
                        </p>
                    </div>

                    <div
                        class="p-5 bg-white shadow-sm rounded-2xl ring-1 ring-line"
                    >
                        <p class="text-sm text-night-400">
                            Conversations résolues
                        </p>

                        <p
                            class="mt-2 text-3xl font-bold text-blue-600"
                        >
                            {{ resolvedConversationsCount }}
                        </p>

                        <p class="mt-1 text-xs text-night-300">
                            Dossiers traités
                        </p>
                    </div>
                </div>

                <!-- ==================================================
                     HISTORIQUE
                =================================================== -->

                <div
                    class="mt-6 overflow-hidden bg-white shadow-sm rounded-2xl ring-1 ring-line"
                >

                    <div
                        class="flex flex-col gap-3 px-6 py-5 border-b border-line sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                class="text-lg font-semibold text-night-900"
                            >
                                Historique des conversations
                            </h2>

                            <p
                                class="mt-1 text-sm text-night-400"
                            >
                                Les échanges récents avec ce client.
                            </p>
                        </div>

                        <Link
                            :href="route('conversations.index')"
                            class="text-sm font-semibold text-brand-600 hover:text-brand-700"
                        >
                            Toutes les conversations →
                        </Link>
                    </div>

                    <!-- Aucune conversation -->

                    <div
                        v-if="conversations.length === 0"
                        class="px-6 text-center py-14"
                    >
                        <div class="text-5xl">
                            💬
                        </div>

                        <h3
                            class="mt-4 text-lg font-semibold text-night-900"
                        >
                            Aucune conversation
                        </h3>

                        <p
                            class="mt-2 text-sm text-night-400"
                        >
                            Ce client n’a encore aucune conversation.
                        </p>
                    </div>

                    <!-- Conversations -->

                    <div
                        v-else
                        class="divide-y divide-line"
                    >
                        <Link
                            v-for="conversation in conversations"
                            :key="conversation.id"
                            :href="
                                route(
                                    'conversations.show',
                                    conversation.id
                                )
                            "
                            class="block px-6 py-5 transition hover:bg-canvas-sunken"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                            >

                                <!-- Informations -->

                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="font-semibold text-night-900"
                                        >
                                            {{
                                                conversation.subject ||
                                                "Sans sujet"
                                            }}
                                        </h3>

                                        <span
                                            class="px-3 py-1 text-xs font-medium rounded-full"
                                            :class="
                                                conversationStatusClass(
                                                    conversation.status
                                                )
                                            "
                                        >
                                            {{
                                                conversationStatusLabel(
                                                    conversation.status
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex flex-wrap gap-2 mt-2"
                                    >
                                        <span
                                            v-if="conversation.channel"
                                            class="px-3 py-1 text-xs font-medium text-night-500 bg-canvas-sunken rounded-full"
                                        >
                                            {{
                                                channelLabel(
                                                    conversation.channel
                                                )
                                            }}
                                        </span>

                                        <span
                                            v-if="conversation.priority"
                                            class="px-3 py-1 text-xs font-medium rounded-full"
                                            :class="
                                                priorityClass(
                                                    conversation.priority
                                                )
                                            "
                                        >
                                            Priorité :
                                            {{
                                                priorityLabel(
                                                    conversation.priority
                                                )
                                            }}
                                        </span>

                                        <span
                                            v-if="
                                                conversation.ai_enabled
                                            "
                                            class="px-3 py-1 text-xs font-medium rounded-full bg-violet-100 text-violet-700"
                                        >
                                            🤖 IA active
                                        </span>

                                        <span
                                            v-else
                                            class="px-3 py-1 text-xs font-medium text-night-500 bg-canvas-sunken rounded-full"
                                        >
                                            👤 Agent
                                        </span>
                                    </div>
                                </div>

                                <!-- Date -->

                                <div
                                    class="text-left shrink-0 lg:text-right"
                                >
                                    <p
                                        class="text-xs text-night-300"
                                    >
                                        Dernière activité
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-medium text-night-600"
                                    >
                                        {{
                                            formatDate(
                                                conversation.last_message_at
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            conversation.assigned_agent?.name ||
                                            conversation.assignedAgent?.name
                                        "
                                        class="mt-1 text-xs text-night-400"
                                    >
                                        👤
                                        {{
                                            conversation.assigned_agent?.name ??
                                            conversation.assignedAgent?.name
                                        }}
                                    </p>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- ==================================================
                     RACCOURCIS
                =================================================== -->

                <div
                    class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-2"
                >
                    <Link
                        :href="
                            route(
                                'clients.edit',
                                client.id
                            )
                        "
                        class="p-5 transition bg-white border border-line shadow-sm rounded-2xl hover:border-brand-200 hover:bg-brand-50"
                    >
                        <div
                            class="flex items-center gap-4"
                        >
                            <div
                                class="flex items-center justify-center text-xl bg-brand-100 h-11 w-11 rounded-xl"
                            >
                                ✏️
                            </div>

                            <div>
                                <h3
                                    class="font-semibold text-night-900"
                                >
                                    Modifier le client
                                </h3>

                                <p
                                    class="mt-1 text-sm text-night-400"
                                >
                                    Mettre à jour ses informations.
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="route('conversations.index')"
                        class="p-5 transition bg-white border border-line shadow-sm rounded-2xl hover:border-emerald-200 hover:bg-emerald-50"
                    >
                        <div
                            class="flex items-center gap-4"
                        >
                            <div
                                class="flex items-center justify-center text-xl h-11 w-11 rounded-xl bg-emerald-100"
                            >
                                💬
                            </div>

                            <div>
                                <h3
                                    class="font-semibold text-night-900"
                                >
                                    Voir les conversations
                                </h3>

                                <p
                                    class="mt-1 text-sm text-night-400"
                                >
                                    Accéder au centre de support.
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
