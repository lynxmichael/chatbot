<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    clients: {
        type: Array,
        default: () => [],
    },
    conversations: {
        type: Array,
        default: () => [],
    },
    agents: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    client_id: props.ticket.client_id ?? "",
    conversation_id: props.ticket.conversation_id ?? "",
    assigned_to: props.ticket.assigned_to ?? "",
    subject: props.ticket.subject ?? "",
    description: props.ticket.description ?? "",
    category: props.ticket.category ?? "general",
    status: props.ticket.status ?? "open",
    priority: props.ticket.priority ?? "normal",
    channel: props.ticket.channel ?? "web",
    sla_due_at: props.ticket.sla_due_at
        ? new Date(props.ticket.sla_due_at).toISOString().slice(0, 16)
        : "",
    resolution: props.ticket.resolution ?? "",
});

const showAdvanced = ref(false);

const selectedClient = computed(() => {
    return (
        props.clients.find(
            (client) =>
                Number(client.id) === Number(form.client_id)
        ) ?? null
    );
});

const availableConversations = computed(() => {
    if (!form.client_id) {
        return [];
    }

    return props.conversations.filter(
        (conversation) =>
            Number(conversation.client_id) === Number(form.client_id)
    );
});

const selectedConversation = computed(() => {
    if (!form.conversation_id) {
        return null;
    }

    return (
        props.conversations.find(
            (conversation) =>
                Number(conversation.id) === Number(form.conversation_id)
        ) ?? null
    );
});

const getClientName = (client) => {
    if (!client) {
        return "Client";
    }

    const name = [
        client.first_name,
        client.last_name,
    ]
        .filter(Boolean)
        .join(" ");

    return name || `Client #${client.id}`;
};

const statusLabels = {
    open: "Ouvert",
    pending: "En attente",
    in_progress: "En cours",
    resolved: "Résolu",
    closed: "Fermé",
};

const channelLabels = {
    web: "Web",
    widget: "Widget",
    whatsapp: "WhatsApp",
    email: "Email",
    phone: "Téléphone",
};

const formatDate = (date) => {
    if (!date) {
        return "Date inconnue";
    }

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) {
        return "Date inconnue";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        dateStyle: "short",
        timeStyle: "short",
    }).format(parsedDate);
};

watch(
    () => form.client_id,
    (newClientId) => {
        if (
            form.conversation_id &&
            !props.conversations.some(
                (conversation) =>
                    Number(conversation.id) ===
                        Number(form.conversation_id) &&
                    Number(conversation.client_id) ===
                        Number(newClientId)
            )
        ) {
            form.conversation_id = "";
        }
    }
);

const submit = () => {
    form.put(route("tickets.update", props.ticket.id), {
        preserveScroll: true,
    });
};

const hasErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});
</script>

<template>
    <Head :title="`Modifier ${ticket.ticket_number}`" />

```
<AuthenticatedLayout>
    <template #header>
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    Modifier le ticket
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    {{ ticket.ticket_number }} — modifiez les informations
                    de la demande.
                </p>
            </div>

            <div class="flex gap-2">
                <Link
                    :href="route('tickets.show', ticket.id)"
                    class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    Voir le ticket
                </Link>

                <Link
                    :href="route('tickets.index')"
                    class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    ← Retour
                </Link>
            </div>
        </div>
    </template>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div
                v-if="hasErrors"
                class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5"
            >
                <div class="flex items-start gap-3">
                    <span class="text-xl">⚠️</span>

                    <div>
                        <h3 class="font-semibold text-red-800">
                            Vérifiez les informations saisies
                        </h3>

                        <p class="mt-1 text-sm text-red-700">
                            Certains champs du ticket contiennent une
                            erreur.
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100"
            >
                <div
                    class="border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white px-6 py-6 sm:px-8"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-xl"
                        >
                            🎫
                        </div>

                        <div>
                            <h1
                                class="text-lg font-semibold text-gray-900"
                            >
                                Modification du ticket
                            </h1>

                            <p class="mt-1 text-sm text-gray-500">
                                Ticket
                                <span class="font-semibold">
                                    {{ ticket.ticket_number }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <form
                    @submit.prevent="submit"
                    class="space-y-8 p-6 sm:p-8"
                >
                    <!-- CLIENT -->
                    <section>
                        <h3
                            class="mb-4 text-base font-semibold text-gray-900"
                        >
                            Client concerné
                        </h3>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label
                                    for="client_id"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Client
                                    <span class="text-red-500">*</span>
                                </label>

                                <select
                                    id="client_id"
                                    v-model="form.client_id"
                                    required
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="{
                                        'border-red-400':
                                            form.errors.client_id,
                                    }"
                                >
                                    <option value="">
                                        Sélectionnez un client
                                    </option>

                                    <option
                                        v-for="client in clients"
                                        :key="client.id"
                                        :value="client.id"
                                    >
                                        {{ getClientName(client) }}
                                        —
                                        {{
                                            client.email ||
                                            client.phone ||
                                            "Aucune coordonnée"
                                        }}
                                    </option>
                                </select>

                                <p
                                    v-if="form.errors.client_id"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.client_id }}
                                </p>
                            </div>

                            <!-- RESUME CLIENT -->
                            <div
                                v-if="selectedClient"
                                class="rounded-xl border border-indigo-100 bg-indigo-50 p-4 md:col-span-2"
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-indigo-900"
                                        >
                                            {{
                                                getClientName(
                                                    selectedClient
                                                )
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-indigo-700"
                                        >
                                            {{
                                                selectedClient.email ||
                                                "Email non renseigné"
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        class="text-sm text-indigo-800"
                                    >
                                        📞
                                        {{
                                            selectedClient.phone ||
                                            "Téléphone non renseigné"
                                        }}
                                    </div>
                                </div>
                            </div>

                            <!-- CONVERSATION -->
                            <div class="md:col-span-2">
                                <label
                                    for="conversation_id"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Conversation associée
                                </label>

                                <select
                                    id="conversation_id"
                                    v-model="form.conversation_id"
                                    :disabled="!form.client_id"
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-100"
                                    :class="{
                                        'border-red-400':
                                            form.errors.conversation_id,
                                    }"
                                >
                                    <option value="">
                                        {{
                                            form.client_id
                                                ? "Aucune conversation"
                                                : "Sélectionnez d'abord un client"
                                        }}
                                    </option>

                                    <option
                                        v-for="conversation in availableConversations"
                                        :key="conversation.id"
                                        :value="conversation.id"
                                    >
                                        {{
                                            conversation.subject ||
                                            `Conversation #${conversation.id}`
                                        }}
                                        —
                                        {{
                                            statusLabels[
                                                conversation.status
                                            ] ||
                                            conversation.status
                                        }}
                                        —
                                        {{
                                            channelLabels[
                                                conversation.channel
                                            ] ||
                                            conversation.channel
                                        }}
                                    </option>
                                </select>

                                <p
                                    v-if="
                                        form.client_id &&
                                        availableConversations.length ===
                                            0
                                    "
                                    class="mt-2 text-xs text-gray-500"
                                >
                                    Aucune conversation existante pour ce
                                    client.
                                </p>

                                <p
                                    v-if="form.errors.conversation_id"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.conversation_id }}
                                </p>

                                <!-- CONVERSATION SELECTIONNEE -->
                                <div
                                    v-if="selectedConversation"
                                    class="mt-3 rounded-xl border border-blue-100 bg-blue-50 p-4"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-blue-900"
                                            >
                                                {{
                                                    selectedConversation.subject ||
                                                    `Conversation #${selectedConversation.id}`
                                                }}
                                            </p>

                                            <div
                                                class="mt-2 flex flex-wrap gap-2 text-xs"
                                            >
                                                <span
                                                    class="rounded-full bg-white px-2.5 py-1 font-medium text-blue-700"
                                                >
                                                    {{
                                                        statusLabels[
                                                            selectedConversation
                                                                .status
                                                        ] ||
                                                        selectedConversation.status
                                                    }}
                                                </span>

                                                <span
                                                    class="rounded-full bg-white px-2.5 py-1 font-medium text-blue-700"
                                                >
                                                    {{
                                                        channelLabels[
                                                            selectedConversation
                                                                .channel
                                                        ] ||
                                                        selectedConversation.channel
                                                    }}
                                                </span>

                                                <span
                                                    class="rounded-full bg-white px-2.5 py-1 font-medium text-blue-700"
                                                >
                                                    Créée le
                                                    {{
                                                        formatDate(
                                                            selectedConversation.created_at
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <Link
                                            :href="
                                                route(
                                                    'conversations.show',
                                                    selectedConversation.id
                                                )
                                            "
                                            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700"
                                        >
                                            Ouvrir la conversation →
                                        </Link>
                                    </div>
                                </div>

                                <p
                                    v-else
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    Laissez vide si le ticket n'est pas
                                    lié à une conversation existante.
                                </p>
                            </div>

                            <!-- AGENT -->
                            <div>
                                <label
                                    for="assigned_to"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Agent responsable
                                </label>

                                <select
                                    id="assigned_to"
                                    v-model="form.assigned_to"
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="{
                                        'border-red-400':
                                            form.errors.assigned_to,
                                    }"
                                >
                                    <option value="">
                                        Non attribué
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

                                <p
                                    v-if="form.errors.assigned_to"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.assigned_to }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- DEMANDE -->
                    <section class="border-t border-gray-100 pt-8">
                        <h3
                            class="mb-4 text-base font-semibold text-gray-900"
                        >
                            Demande
                        </h3>

                        <div>
                            <label
                                for="subject"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Sujet
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                id="subject"
                                v-model="form.subject"
                                type="text"
                                required
                                maxlength="255"
                                class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :class="{
                                    'border-red-400':
                                        form.errors.subject,
                                }"
                            />

                            <div
                                class="mt-1.5 flex justify-between"
                            >
                                <p
                                    v-if="form.errors.subject"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.subject }}
                                </p>

                                <p
                                    v-else
                                    class="text-xs text-gray-400"
                                >
                                    {{ form.subject.length }}/255
                                </p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <label
                                for="description"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Description
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="7"
                                required
                                class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :class="{
                                    'border-red-400':
                                        form.errors.description,
                                }"
                            ></textarea>

                            <p
                                v-if="form.errors.description"
                                class="mt-1.5 text-sm text-red-600"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <div
                            v-if="
                                form.status === 'resolved' ||
                                form.status === 'closed'
                            "
                            class="mt-5"
                        >
                            <label
                                for="resolution"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Résolution
                            </label>

                            <textarea
                                id="resolution"
                                v-model="form.resolution"
                                rows="5"
                                placeholder="Décrivez la solution apportée..."
                                class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :class="{
                                    'border-red-400':
                                        form.errors.resolution,
                                }"
                            ></textarea>

                            <p
                                v-if="form.errors.resolution"
                                class="mt-1.5 text-sm text-red-600"
                            >
                                {{ form.errors.resolution }}
                            </p>
                        </div>
                    </section>

                    <!-- CLASSIFICATION -->
                    <section class="border-t border-gray-100 pt-8">
                        <h3
                            class="mb-4 text-base font-semibold text-gray-900"
                        >
                            Classification
                        </h3>

                        <div
                            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div>
                                <label
                                    for="category"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Catégorie
                                    <span class="text-red-500">*</span>
                                </label>

                                <select
                                    id="category"
                                    v-model="form.category"
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
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

                                <p
                                    v-if="form.errors.category"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.category }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="priority"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Priorité
                                    <span class="text-red-500">*</span>
                                </label>

                                <select
                                    id="priority"
                                    v-model="form.priority"
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
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

                                <p
                                    v-if="form.errors.priority"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.priority }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="channel"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Canal
                                    <span class="text-red-500">*</span>
                                </label>

                                <select
                                    id="channel"
                                    v-model="form.channel"
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
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

                                <p
                                    v-if="form.errors.channel"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.channel }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="status"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Statut
                                    <span class="text-red-500">*</span>
                                </label>

                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
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

                                <p
                                    v-if="form.errors.status"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.status }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- SLA -->
                    <section class="border-t border-gray-100 pt-8">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <h3
                                    class="text-base font-semibold text-gray-900"
                                >
                                    Délai SLA
                                </h3>

                                <p class="mt-1 text-sm text-gray-500">
                                    Définissez une échéance de traitement.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="text-sm font-semibold text-indigo-600 hover:text-indigo-700"
                                @click="
                                    showAdvanced = !showAdvanced
                                "
                            >
                                {{
                                    showAdvanced
                                        ? "Masquer"
                                        : "Afficher"
                                }}
                                les options avancées
                            </button>
                        </div>

                        <div
                            v-show="showAdvanced"
                            class="mt-5"
                        >
                            <label
                                for="sla_due_at"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Échéance SLA
                            </label>

                            <input
                                id="sla_due_at"
                                v-model="form.sla_due_at"
                                type="datetime-local"
                                class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />

                            <p
                                v-if="form.errors.sla_due_at"
                                class="mt-1.5 text-sm text-red-600"
                            >
                                {{ form.errors.sla_due_at }}
                            </p>
                        </div>
                    </section>

                    <!-- ACTIONS -->
                    <div
                        class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end"
                    >
                        <Link
                            :href="route('tickets.show', ticket.id)"
                            class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                        >
                            Annuler
                        </Link>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span
                                v-if="form.processing"
                                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>

                            {{
                                form.processing
                                    ? "Enregistrement..."
                                    : "Enregistrer les modifications"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</AuthenticatedLayout>
```

</template>
