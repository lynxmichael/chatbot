<script setup>
import { Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
    agent: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.agent.name ?? "",
    email: props.agent.email ?? "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.put(route("agents.update", props.agent.id));
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
            <div class="mb-6">
                <Link
                    :href="route('agents.index')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Retour aux agents
                </Link>
            </div>

            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    Modifier l'agent
                </h1>

                <p class="mt-2 text-gray-500">
                    Modifiez les informations de cet agent.
                </p>
            </div>

            <div
                class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 md:p-8"
            >
                <form
                    @submit.prevent="submit"
                    class="space-y-6"
                >
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
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="{
                                'border-red-500': form.errors.name,
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
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="{
                                'border-red-500': form.errors.email,
                            }"
                        />

                        <p
                            v-if="form.errors.email"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Nouveau mot de passe -->
                    <div>
                        <label
                            for="password"
                            class="mb-2 block text-sm font-semibold text-gray-700"
                        >
                            Nouveau mot de passe
                        </label>

                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Laisser vide pour conserver l'ancien"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :class="{
                                'border-red-500': form.errors.password,
                            }"
                        />

                        <p class="mt-1 text-xs text-gray-500">
                            Laissez ce champ vide si vous ne souhaitez pas
                            modifier le mot de passe.
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
                            Confirmer le nouveau mot de passe
                        </label>

                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Répétez le nouveau mot de passe"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Informations -->
                    <div
                        class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700"
                    >
                        <strong>Agent :</strong>
                        {{ props.agent.name }}
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
                                    ? "Enregistrement..."
                                    : "Enregistrer les modifications"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>