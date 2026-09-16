<script setup>
import {
    computed,
    ref,
} from "vue";

import {
    Head,
    Link,
    useForm,
} from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps({
    clients: {
        type: Array,
        default: () => [],
    },

    agents: {
        type: Array,
        default: () => [],
    },
});

/*
|--------------------------------------------------------------------------
| Formulaire
|--------------------------------------------------------------------------
*/

const form = useForm({
    client_id: "",
    conversation_id: "",
    assigned_to: "",
    subject: "",
    description: "",
    category: "general",
    status: "open",
    priority: "normal",
    channel: "web",
    sla_due_at: "",
});

/*
|--------------------------------------------------------------------------
| État
|--------------------------------------------------------------------------
*/

const showAdvanced = ref(false);

/*
|--------------------------------------------------------------------------
| Client sélectionné
|--------------------------------------------------------------------------
*/

const selectedClient = computed(() => {
    return props.clients.find(
        (client) =>
            Number(client.id) === Number(form.client_id)
    ) ?? null;
});

/*
|--------------------------------------------------------------------------
| Nom client
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Soumission
|--------------------------------------------------------------------------
*/

const submit = () => {
    form.post(route("tickets.store"), {
        preserveScroll: true,
    });
};

/*
|--------------------------------------------------------------------------
| Erreurs
|--------------------------------------------------------------------------
*/

const hasErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});
</script>

<template>
    <Head title="Nouveau ticket" />

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
                        class="text-xl font-semibold text-gray-800"
                    >
                        Nouveau ticket
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500"
                    >
                        Créez une nouvelle réclamation ou demande client.
                    </p>
                </div>

                <Link
                    :href="route('tickets.index')"
                    class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    ← Retour aux tickets
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div
                class="max-w-5xl px-4 mx-auto sm:px-6 lg:px-8"
            >

                <!-- ======================================================
                     ERREURS
                ======================================================= -->

                <div
                    v-if="hasErrors"
                    class="p-5 mb-6 border border-red-200 rounded-2xl bg-red-50"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-xl">
                            ⚠️
                        </span>

                        <div>
                            <h3
                                class="font-semibold text-red-800"
                            >
                                Vérifiez les informations saisies
                            </h3>

                            <p
                                class="mt-1 text-sm text-red-700"
                            >
                                Certains champs du ticket contiennent une erreur.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ======================================================
                     FORMULAIRE
                ======================================================= -->

                <div
                    class="overflow-hidden bg-white shadow-sm rounded-2xl ring-1 ring-gray-100"
                >

                    <!-- En-tête -->

                    <div
                        class="px-6 py-6 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white sm:px-8"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex items-center justify-center w-12 h-12 text-xl bg-indigo-100 rounded-xl"
                            >
                                🎫
                            </div>

                            <div>
                                <h1
                                    class="text-lg font-semibold text-gray-900"
                                >
                                    Informations du ticket
                                </h1>

                                <p
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    Les informations marquées d’un
                                    <span class="font-semibold text-red-500">*</span>
                                    sont obligatoires.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form
                        @submit.prevent="submit"
                        class="p-6 space-y-8 sm:p-8"
                    >

                        <!-- ==================================================
                             CLIENT
                        =================================================== -->

                        <section>
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Client concerné
                            </h3>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                                <!-- Client -->

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

                                <!-- Résumé client -->

                                <div
                                    v-if="selectedClient"
                                    class="p-4 border border-indigo-100 md:col-span-2 rounded-xl bg-indigo-50"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-indigo-900"
                                            >
                                                {{ getClientName(selectedClient) }}
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

                                <!-- Conversation -->

                                <div>
                                    <label
                                        for="conversation_id"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Conversation associée
                                    </label>

                                    <input
                                        id="conversation_id"
                                        v-model="form.conversation_id"
                                        type="number"
                                        min="1"
                                        placeholder="ID de conversation, facultatif"
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400':
                                                form.errors.conversation_id,
                                        }"
                                    />

                                    <p
                                        class="mt-1 text-xs text-gray-400"
                                    >
                                        Laissez vide si le ticket ne provient
                                        pas d’une conversation existante.
                                    </p>

                                    <p
                                        v-if="form.errors.conversation_id"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.conversation_id }}
                                    </p>
                                </div>

                                <!-- Agent -->

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

                        <!-- ==================================================
                             DESCRIPTION
                        =================================================== -->

                        <section
                            class="pt-8 border-t border-gray-100"
                        >
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Demande
                            </h3>

                            <!-- Sujet -->

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
                                    placeholder="Ex. Problème de connexion à mon compte"
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

                            <!-- Description -->

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
                                    placeholder="Décrivez précisément le problème, la demande ou la réclamation..."
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
                        </section>

                        <!-- ==================================================
                             CLASSIFICATION
                        =================================================== -->

                        <section
                            class="pt-8 border-t border-gray-100"
                        >
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Classification
                            </h3>

                            <div
                                class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                            >

                                <!-- Catégorie -->

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
                                        :class="{
                                            'border-red-400':
                                                form.errors.category,
                                        }"
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

                                <!-- Priorité -->

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

                                <!-- Canal -->

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

                                <!-- Statut -->

                                <div>
                                    <label
                                        for="status"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Statut initial
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

                        <!-- ==================================================
                             SLA
                        =================================================== -->

                        <section
                            class="pt-8 border-t border-gray-100"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <h3
                                        class="text-base font-semibold text-gray-900"
                                    >
                                        Délai SLA
                                    </h3>

                                    <p
                                        class="mt-1 text-sm text-gray-500"
                                    >
                                        Définissez une échéance de traitement.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="text-sm font-semibold text-indigo-600 hover:text-indigo-700"
                                    @click="
                                        showAdvanced =
                                            !showAdvanced
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

                        <!-- ==================================================
                             ACTIONS
                        =================================================== -->

                        <div
                            class="flex flex-col-reverse gap-3 pt-6 border-t border-gray-100 sm:flex-row sm:justify-end"
                        >
                            <Link
                                :href="route('tickets.index')"
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
                                    class="w-4 h-4 mr-2 border-2 border-white rounded-full animate-spin border-t-transparent"
                                ></span>

                                {{
                                    form.processing
                                        ? "Création..."
                                        : "Créer le ticket"
                                }}
                            </button>
                        </div>
                    </form>
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
                            💡
                        </div>

                        <div>
                            <h3
                                class="font-semibold text-indigo-900"
                            >
                                Bon à savoir
                            </h3>

                            <p
                                class="mt-1 text-sm leading-6 text-indigo-800"
                            >
                                Un ticket peut être associé à une conversation
                                existante et attribué directement à un agent.
                                Le numéro du ticket sera généré automatiquement
                                par le système.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
