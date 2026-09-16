<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },

    agents: {
        type: Array,
        default: () => [],
    },
});

const ticket = computed(() => props.ticket);
const page = usePage();

const canDelete = computed(() => {
    return page.props.auth?.user?.role === "owner";
});
const client = computed(() => ticket.value?.client ?? null);

const assignedAgent = computed(
    () =>
        ticket.value?.assigned_agent ??
        ticket.value?.assignedAgent ??
        null
);

const conversation = computed(() => ticket.value?.conversation ?? null);

/*
|--------------------------------------------------------------------------
| Client
|--------------------------------------------------------------------------
*/

const clientName = computed(() => {
    const name =
        client.value?.full_name ??
        `${client.value?.first_name ?? ""} ${
            client.value?.last_name ?? ""
        }`.trim();

    return name || "—";
});

/*
|--------------------------------------------------------------------------
| Labels
|--------------------------------------------------------------------------
*/

const statusLabels = {
    open: "Ouvert",
    pending: "En attente",
    in_progress: "En cours",
    resolved: "Résolu",
    closed: "Fermé",
};

const priorityLabels = {
    low: "Faible",
    normal: "Normale",
    high: "Haute",
    urgent: "Urgente",
};

const categoryLabels = {
    technical: "Technique",
    billing: "Facturation",
    account: "Compte",
    complaint: "Réclamation",
    request: "Demande",
    general: "Général",
};

const channelLabels = {
    web: "Web",
    widget: "Widget",
    whatsapp: "WhatsApp",
    email: "Email",
    phone: "Téléphone",
};

/*
|--------------------------------------------------------------------------
| Classes
|--------------------------------------------------------------------------
*/

const statusClasses = {
    open: "bg-blue-100 text-blue-700",
    pending: "bg-yellow-100 text-yellow-700",
    in_progress: "bg-purple-100 text-purple-700",
    resolved: "bg-green-100 text-green-700",
    closed: "bg-gray-100 text-gray-700",
};

const priorityClasses = {
    low: "bg-gray-100 text-gray-700",
    normal: "bg-blue-100 text-blue-700",
    high: "bg-orange-100 text-orange-700",
    urgent: "bg-red-100 text-red-700",
};

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const statusLabel = computed(() => {
    return statusLabels[ticket.value?.status] ?? ticket.value?.status ?? "—";
});

const priorityLabel = computed(() => {
    return (
        priorityLabels[ticket.value?.priority] ??
        ticket.value?.priority ??
        "—"
    );
});

const categoryLabel = computed(() => {
    return (
        categoryLabels[ticket.value?.category] ??
        ticket.value?.category ??
        "—"
    );
});

const channelLabel = computed(() => {
    return (
        channelLabels[ticket.value?.channel] ??
        ticket.value?.channel ??
        "—"
    );
});

const statusClass = computed(() => {
    return (
        statusClasses[ticket.value?.status] ??
        "bg-gray-100 text-gray-700"
    );
});

const priorityClass = computed(() => {
    return (
        priorityClasses[ticket.value?.priority] ??
        "bg-gray-100 text-gray-700"
    );
});

const formatDate = (date) => {
    if (!date) {
        return "—";
    }

    try {
        return new Date(date).toLocaleString("fr-FR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    } catch {
        return "—";
    }
};

const formatShortDate = (date) => {
    if (!date) {
        return "—";
    }

    try {
        return new Date(date).toLocaleDateString("fr-FR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
        });
    } catch {
        return "—";
    }
};

const agentName = (agent) => {
    if (!agent) {
        return "Non attribué";
    }

    return (
        agent.name ??
        `${agent.first_name ?? ""} ${agent.last_name ?? ""}`.trim() ??
        agent.email ??
        "Agent"
    );
};

const getAgentName = (agent) => {
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

/*
|--------------------------------------------------------------------------
| Formulaire de modification
|--------------------------------------------------------------------------
*/

const form = useForm({
    subject: ticket.value?.subject ?? "",
    description: ticket.value?.description ?? "",
    category: ticket.value?.category ?? "general",
    status: ticket.value?.status ?? "open",
    priority: ticket.value?.priority ?? "normal",
    channel: ticket.value?.channel ?? "widget",
    assigned_to: ticket.value?.assigned_to ?? "",
    sla_due_at: ticket.value?.sla_due_at
        ? ticket.value.sla_due_at.substring(0, 16)
        : "",
    resolution: ticket.value?.resolution ?? "",
});

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/
const deleteTicket = () => {
    if (!canDelete.value) {
        return;
    }

    if (
        !window.confirm(
            `Voulez-vous vraiment supprimer le ticket ${ticket.value.ticket_number} ?`
        )
    ) {
        return;
    }

    useForm({}).delete(route("tickets.destroy", ticket.value.id), {
        preserveScroll: false,
        onSuccess: () => {
            window.location.href = route("tickets.index");
        },
    });
};

const updateTicket = () => {
    form.put(route("tickets.update", ticket.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const setStatus = (status) => {
    form.status = status;
    updateTicket();
};

const setPriority = (priority) => {
    form.priority = priority;
    updateTicket();
};

const assignAgent = (agentId) => {
    form.assigned_to = agentId || "";
    updateTicket();
};

const saveResolution = () => {
    updateTicket();
};
</script>

<template>
    <Head :title="`Ticket ${ticket.ticket_number}`" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- HEADER -->
                <div
                    class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="mb-2 flex items-center gap-2">
                            <Link
                                :href="route('tickets.index')"
                                class="text-sm font-medium text-gray-500 hover:text-gray-700"
                            >
                                ← Retour aux tickets
                            </Link>
                        </div>

                        <h1 class="text-2xl font-bold text-gray-900">
                            Ticket {{ ticket.ticket_number }}
                        </h1>

                        <p class="mt-1 text-sm text-gray-500">
                            Créé le {{ formatDate(ticket.created_at) }}
                        </p>
                    </div>

                <div class="flex flex-wrap gap-2">
    <Link
        :href="route('tickets.edit', ticket.id)"
        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
    >
        Modifier
    </Link>

    <button
        v-if="canDelete"
        type="button"
        @click="deleteTicket"
        class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700"
    >
        Supprimer
    </button>
</div>
                </div>

                <!-- TICKET HEADER -->
                <div
                    class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"
                >
                    <div class="border-b border-gray-200 p-6">
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="mb-3 flex flex-wrap gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="statusClass"
                                    >
                                        {{ statusLabel }}
                                    </span>

                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="priorityClass"
                                    >
                                        {{ priorityLabel }}
                                    </span>

                                    <span
                                        class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700"
                                    >
                                        {{ categoryLabel }}
                                    </span>

                                    <span
                                        class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700"
                                    >
                                        {{ channelLabel }}
                                    </span>
                                </div>

                                <h2
                                    class="break-words text-xl font-semibold text-gray-900"
                                >
                                    {{ ticket.subject || "Sans sujet" }}
                                </h2>
                            </div>

                            <div class="text-sm text-gray-500 lg:text-right">
                                <div>
                                    Dernière modification :
                                </div>

                                <div class="font-medium text-gray-700">
                                    {{ formatDate(ticket.updated_at) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACTIONS RAPIDES -->
                    <div class="bg-gray-50 p-4">
                        <div class="grid gap-4 lg:grid-cols-3">
                            <!-- STATUS -->
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Statut
                                </label>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="setStatus('open')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-blue-100 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-200 disabled:opacity-50"
                                    >
                                        Ouvert
                                    </button>

                                    <button
                                        type="button"
                                        @click="setStatus('pending')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-yellow-100 px-3 py-2 text-xs font-semibold text-yellow-700 hover:bg-yellow-200 disabled:opacity-50"
                                    >
                                        En attente
                                    </button>

                                    <button
                                        type="button"
                                        @click="setStatus('in_progress')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-purple-100 px-3 py-2 text-xs font-semibold text-purple-700 hover:bg-purple-200 disabled:opacity-50"
                                    >
                                        En cours
                                    </button>

                                    <button
                                        type="button"
                                        @click="setStatus('resolved')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-green-100 px-3 py-2 text-xs font-semibold text-green-700 hover:bg-green-200 disabled:opacity-50"
                                    >
                                        Résolu
                                    </button>

                                    <button
                                        type="button"
                                        @click="setStatus('closed')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-300 disabled:opacity-50"
                                    >
                                        Fermé
                                    </button>
                                </div>
                            </div>

                            <!-- PRIORITE -->
                            <div>
                                <label
                                    class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Priorité
                                </label>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="setPriority('low')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                                    >
                                        Faible
                                    </button>

                                    <button
                                        type="button"
                                        @click="setPriority('normal')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-blue-100 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-200 disabled:opacity-50"
                                    >
                                        Normale
                                    </button>

                                    <button
                                        type="button"
                                        @click="setPriority('high')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-orange-100 px-3 py-2 text-xs font-semibold text-orange-700 hover:bg-orange-200 disabled:opacity-50"
                                    >
                                        Haute
                                    </button>

                                    <button
                                        type="button"
                                        @click="setPriority('urgent')"
                                        :disabled="form.processing"
                                        class="rounded-lg bg-red-100 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-200 disabled:opacity-50"
                                    >
                                        Urgente
                                    </button>
                                </div>
                            </div>

                            <!-- AGENT -->
                            <div>
                                <label
                                    for="quick_assigned_to"
                                    class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Agent
                                </label>

                                <select
                                    id="quick_assigned_to"
                                    :value="ticket.assigned_to ?? ''"
                                    @change="
                                        assignAgent(
                                            $event.target.value
                                                ? Number(
                                                      $event.target.value
                                                  )
                                                : null
                                        )
                                    "
                                    :disabled="form.processing"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                                >
                                    <option value="">
                                        Non attribué
                                    </option>

                                    <option
                                        v-for="agent in agents"
                                        :key="agent.id"
                                        :value="agent.id"
                                    >
                                        {{ getAgentName(agent) }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONTENU PRINCIPAL -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- COLONNE PRINCIPALE -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- DESCRIPTION -->
                        <div
                            class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200"
                        >
                            <div
                                class="border-b border-gray-200 px-6 py-4"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-900"
                                >
                                    Description
                                </h3>
                            </div>

                            <div class="p-6">
                                <div
                                    class="whitespace-pre-wrap break-words text-sm leading-7 text-gray-700"
                                >
                                    {{
                                        ticket.description ||
                                        "Aucune description disponible."
                                    }}
                                </div>
                            </div>
                        </div>

                        <!-- RESOLUTION -->
                        <div
                            class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200"
                        >
                            <div
                                class="flex flex-col gap-3 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <h3
                                        class="text-base font-semibold text-gray-900"
                                    >
                                        Résolution
                                    </h3>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        Ajoutez les informations relatives
                                        à la résolution du ticket.
                                    </p>
                                </div>
                            </div>

                            <div class="p-6">
                                <textarea
                                    v-model="form.resolution"
                                    rows="6"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Décrivez la solution apportée au client..."
                                ></textarea>

                                <div
                                    v-if="form.errors.resolution"
                                    class="mt-2 text-sm text-red-600"
                                >
                                    {{ form.errors.resolution }}
                                </div>

                                <div class="mt-4 flex justify-end">
                                    <button
                                        type="button"
                                        @click="saveResolution"
                                        :disabled="form.processing"
                                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {{
                                            form.processing
                                                ? "Enregistrement..."
                                                : "Enregistrer la résolution"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- CONVERSATION -->
                        <div
                            v-if="conversation"
                            class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200"
                        >
                            <div
                                class="border-b border-gray-200 px-6 py-4"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-900"
                                >
                                    Conversation associée
                                </h3>
                            </div>

                            <div class="p-6">
                                <div
                                    class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-gray-900"
                                        >
                                            {{
                                                conversation.subject ||
                                                "Conversation sans sujet"
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-gray-500"
                                        >
                                            Conversation #{{
                                                conversation.id
                                            }}
                                        </p>
                                    </div>

                                    <Link
                                        v-if="
                                            conversation.id &&
                                            route().has(
                                                'conversations.show'
                                            )
                                        "
                                        :href="
                                            route(
                                                'conversations.show',
                                                conversation.id
                                            )
                                        "
                                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        Voir la conversation
                                    </Link>
                                </div>

                                <div
                                    class="grid gap-4 sm:grid-cols-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                        >
                                            Canal
                                        </p>

                                        <p
                                            class="mt-1 text-sm font-semibold text-gray-900"
                                        >
                                            {{
                                                channelLabels[
                                                    conversation.channel
                                                ] ??
                                                conversation.channel ??
                                                "—"
                                            }}
                                        </p>
                                    </div>

                                    <div>
                                        <p
                                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                        >
                                            Statut
                                        </p>

                                        <p
                                            class="mt-1 text-sm font-semibold text-gray-900"
                                        >
                                            {{
                                                statusLabels[
                                                    conversation.status
                                                ] ??
                                                conversation.status ??
                                                "—"
                                            }}
                                        </p>
                                    </div>

                                    <div>
                                        <p
                                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                        >
                                            Dernier message
                                        </p>

                                        <p
                                            class="mt-1 text-sm font-semibold text-gray-900"
                                        >
                                            {{
                                                formatDate(
                                                    conversation.last_message_at
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- COLONNE LATERALE -->
                    <div class="space-y-6">
                        <!-- CLIENT -->
                        <div
                            class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200"
                        >
                            <div
                                class="border-b border-gray-200 px-6 py-4"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-900"
                                >
                                    Client
                                </h3>
                            </div>

                            <div class="p-6">
                                <div class="mb-4">
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-lg font-bold text-indigo-700"
                                    >
                                        {{
                                            clientName
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </div>
                                </div>

                                <h4
                                    class="break-words text-base font-semibold text-gray-900"
                                >
                                    {{ clientName }}
                                </h4>

                                <div
                                    v-if="client?.company"
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    {{ client.company }}
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div v-if="client?.email">
                                        <p
                                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                        >
                                            Email
                                        </p>

                                        <a
                                            :href="`mailto:${client.email}`"
                                            class="mt-1 block break-all text-sm text-indigo-600 hover:text-indigo-800"
                                        >
                                            {{ client.email }}
                                        </a>
                                    </div>

                                    <div v-if="client?.phone">
                                        <p
                                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                        >
                                            Téléphone
                                        </p>

                                        <a
                                            :href="`tel:${client.phone}`"
                                            class="mt-1 block text-sm text-indigo-600 hover:text-indigo-800"
                                        >
                                            {{ client.phone }}
                                        </a>
                                    </div>

                                    <div
                                        v-if="
                                            client?.city ||
                                            client?.country
                                        "
                                    >
                                        <p
                                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                        >
                                            Localisation
                                        </p>

                                        <p
                                            class="mt-1 text-sm text-gray-700"
                                        >
                                            {{
                                                [
                                                    client?.city,
                                                    client?.country,
                                                ]
                                                    .filter(Boolean)
                                                    .join(", ") || "—"
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AGENT -->
                        <div
                            class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200"
                        >
                            <div
                                class="border-b border-gray-200 px-6 py-4"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-900"
                                >
                                    Agent assigné
                                </h3>
                            </div>

                            <div class="p-6">
                                <div
                                    v-if="assignedAgent"
                                    class="flex items-center gap-3"
                                >
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-full bg-green-100 font-bold text-green-700"
                                    >
                                        {{
                                            getAgentName(
                                                assignedAgent
                                            )
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </div>

                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-semibold text-gray-900"
                                        >
                                            {{
                                                getAgentName(
                                                    assignedAgent
                                                )
                                            }}
                                        </p>

                                        <p
                                            v-if="assignedAgent.email"
                                            class="truncate text-xs text-gray-500"
                                        >
                                            {{ assignedAgent.email }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-700"
                                >
                                    Aucun agent n'est actuellement
                                    assigné à ce ticket.
                                </div>
                            </div>
                        </div>

                        <!-- INFORMATIONS -->
                        <div
                            class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200"
                        >
                            <div
                                class="border-b border-gray-200 px-6 py-4"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-900"
                                >
                                    Informations
                                </h3>
                            </div>

                            <div class="divide-y divide-gray-100">
                                <div class="px-6 py-4">
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Numéro
                                    </p>

                                    <p
                                        class="mt-1 font-mono text-sm font-semibold text-gray-900"
                                    >
                                        {{ ticket.ticket_number }}
                                    </p>
                                </div>

                                <div class="px-6 py-4">
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Catégorie
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-semibold text-gray-900"
                                    >
                                        {{ categoryLabel }}
                                    </p>
                                </div>

                                <div class="px-6 py-4">
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Canal
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-semibold text-gray-900"
                                    >
                                        {{ channelLabel }}
                                    </p>
                                </div>

                                <div class="px-6 py-4">
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Création
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-700"
                                    >
                                        {{ formatDate(ticket.created_at) }}
                                    </p>
                                </div>

                                <div class="px-6 py-4">
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        SLA
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-semibold text-gray-900"
                                    >
                                        {{
                                            formatDate(
                                                ticket.sla_due_at
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- DATES -->
                        <div
                            class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200"
                        >
                            <div
                                class="border-b border-gray-200 px-6 py-4"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-900"
                                >
                                    Historique
                                </h3>
                            </div>

                            <div class="space-y-4 p-6">
                                <div>
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Créé
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-700"
                                    >
                                        {{ formatDate(ticket.created_at) }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Mis à jour
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-700"
                                    >
                                        {{ formatDate(ticket.updated_at) }}
                                    </p>
                                </div>

                                <div
                                    v-if="ticket.first_response_at"
                                >
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Première réponse
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-700"
                                    >
                                        {{
                                            formatDate(
                                                ticket.first_response_at
                                            )
                                        }}
                                    </p>
                                </div>

                                <div v-if="ticket.resolved_at">
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Résolu le
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-700"
                                    >
                                        {{
                                            formatDate(
                                                ticket.resolved_at
                                            )
                                        }}
                                    </p>
                                </div>

                                <div v-if="ticket.closed_at">
                                    <p
                                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                    >
                                        Fermé le
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-gray-700"
                                    >
                                        {{ formatDate(ticket.closed_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ERREURS FORMULAIRE -->
                <div
                    v-if="Object.keys(form.errors).length"
                    class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4"
                >
                    <h3
                        class="text-sm font-semibold text-red-800"
                    >
                        Impossible d'enregistrer les modifications
                    </h3>

                    <ul
                        class="mt-2 list-inside list-disc text-sm text-red-700"
                    >
                        <li
                            v-for="(error, field) in form.errors"
                            :key="field"
                        >
                            {{ error }}
                        </li>
                    </ul>
                </div>

                <!-- FOOTER -->
                <div
                    class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Link
                        :href="route('tickets.index')"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        ← Retour à la liste
                    </Link>

                    <div class="text-xs text-gray-500">
                        Dernière modification :
                        {{ formatShortDate(ticket.updated_at) }}
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
