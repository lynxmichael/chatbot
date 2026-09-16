<script setup>
import { computed, reactive, ref } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";

const page = usePage();

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("agents.store"));
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Navigation -->
        <nav class="border-b border-gray-200 bg-white">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4"
            >
                <div class="flex items-center gap-8">
                    <Link
                        :href="route('dashboard')"
                        class="text-xl font-bold text-gray-900"
                    >
                        AI Service Client
                    </Link>

                    <Link
                        :href="route('dashboard')"
                        class="text-sm font-medium text-gray-600 hover:text-indigo-600"
                    >
                        Tableau de bord
                    </Link>

                    <Link
                        :href="route('conversations.index')"
                        class="text-sm font-medium text-gray-600 hover:text-indigo-600"
                    >
                        Conversations
                    </Link>

                    <Link
                        :href="route('agents.index')"
                        class="text-sm font-semibold text-indigo-600"
                    >
                        Agents
                    </Link>

                    <Link
                        :href="route('clients.index')"
                        class="text-sm font-medium text-gray-600 hover:text-indigo-600"
                    >
                        Clients
                    </Link>
                </div>

                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">
                        {{ $page.props.auth?.user?.name ?? "Administrateur" }}
                    </span>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Déconnexion
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Contenu -->
        <main class="mx-auto max-w-3xl px-6 py-8">
            <!-- Retour -->
            <div class="mb-6">
                <Link
                    :href="route('agents.index')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Retour aux agents
                </Link>
            </div>

            <!-- En-tête -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    Ajouter un agent
                </h1>

                <p class="mt-2 text-gray-500">
                    Créez un nouveau membre pour votre équipe de service
                    client.
                </p>
            </div>

            <!-- Formulaire -->
            <div
                class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 md:p-8"
            >
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Nom -->
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-sm font-semibold text-gray-700"
                        >
                            Nom complet
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            autocomplete="name"
                            placeholder="Exemple : Jean Kouassi"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="{
                                'border-red-500':
                                    form.errors.name,
                            }"
                        />

                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label
                            for="email"
                            class="mb-2 block text-sm font-semibold text-gray-700"
                        >
                            Adresse email
                        </label>

                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="agent@entreprise.com"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="{
                                'border-red-500':
                                    form.errors.email,
                            }"
                        />

                        <p
                            v-if="form.errors.email"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Mot de passe -->
                    <div>
                        <label
                            for="password"
                            class="mb-2 block text-sm font-semibold text-gray-700"
                        >
                            Mot de passe
                        </label>

                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Minimum 8 caractères"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="{
                                'border-red-500':
                                    form.errors.password,
                            }"
                        />

                        <p class="mt-1 text-xs text-gray-500">
                            Le mot de passe doit contenir au moins 8 caractères.
                        </p>

                        <p
                            v-if="form.errors.password"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Confirmation -->
                    <div>
                        <label
                            for="password_confirmation"
                            class="mb-2 block text-sm font-semibold text-gray-700"
                        >
                            Confirmer le mot de passe
                        </label>

                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Répétez le mot de passe"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="{
                                'border-red-500':
                                    form.errors.password,
                            }"
                        />
                    </div>

                    <!-- Information -->
                    <div
                        class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700"
                    >
                        <strong>Information :</strong>
                        cet utilisateur sera automatiquement créé comme
                        <strong>agent</strong> dans votre organisation.
                    </div>

                    <!-- Actions -->
                    <div
                        class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:justify-end"
                    >
                        <Link
                            :href="route('agents.index')"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Annuler
                        </Link>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        >
                            {{
                                form.processing
                                    ? "Création en cours..."
                                    : "Créer l'agent"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>