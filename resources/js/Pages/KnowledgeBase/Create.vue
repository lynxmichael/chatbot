<script setup>
import { computed } from "vue";

import {
    Head,
    Link,
    useForm,
} from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const form = useForm({
    title: "",
    category: "",
    content: "",
    is_active: true,
});

/*
|--------------------------------------------------------------------------
| Erreurs
|--------------------------------------------------------------------------
*/

const hasErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});

/*
|--------------------------------------------------------------------------
| Suggestions de catégories
|--------------------------------------------------------------------------
*/

const suggestedCategories = [
    "horaires",
    "produits",
    "prix",
    "disponibilite",
    "procedures",
    "commandes",
    "livraison",
    "paiement",
    "general",
];

/*
|--------------------------------------------------------------------------
| Soumission
|--------------------------------------------------------------------------
*/

const submit = () => {
    form.post(route("knowledge-base.store"), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nouvelle entrée" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800"
                    >
                        Nouvelle entrée
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Ajoutez une information que l’IA pourra utiliser
                        pour répondre automatiquement à vos clients.
                    </p>
                </div>

                <Link
                    :href="route('knowledge-base.index')"
                    class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    ← Retour à la base de connaissances
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl px-4 mx-auto sm:px-6 lg:px-8">
                <!-- Erreur générale -->

                <div
                    v-if="hasErrors"
                    class="p-4 mb-6 border border-red-200 rounded-xl bg-red-50"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-lg">⚠️</span>

                        <div>
                            <p class="font-semibold text-red-800">
                                Vérifiez les informations saisies.
                            </p>

                            <p class="mt-1 text-sm text-red-700">
                                Certains champs nécessitent une correction.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Formulaire -->

                <div
                    class="overflow-hidden bg-white shadow-sm rounded-2xl ring-1 ring-gray-100"
                >
                    <div
                        class="px-6 py-6 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white sm:px-8"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex items-center justify-center w-12 h-12 text-xl bg-indigo-100 rounded-xl"
                            >
                                📚
                            </div>

                            <div>
                                <h1
                                    class="text-lg font-semibold text-gray-900"
                                >
                                    Contenu de l’entrée
                                </h1>

                                <p class="mt-1 text-sm text-gray-500">
                                    Les champs marqués d’un astérisque sont
                                    obligatoires.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form
                        @submit.prevent="submit"
                        class="p-6 space-y-8 sm:p-8"
                    >
                        <!-- ==================================================
                             IDENTIFICATION
                        =================================================== -->

                        <section>
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Identification
                            </h3>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <!-- Titre -->

                                <div>
                                    <label
                                        for="title"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Titre
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        id="title"
                                        v-model="form.title"
                                        type="text"
                                        required
                                        placeholder="Ex : Horaires d’ouverture"
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.title,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.title"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.title }}
                                    </p>
                                </div>

                                <!-- Catégorie -->

                                <div>
                                    <label
                                        for="category"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Catégorie
                                    </label>

                                    <input
                                        id="category"
                                        v-model="form.category"
                                        type="text"
                                        list="category-suggestions"
                                        placeholder="Ex : horaires, prix, livraison..."
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.category,
                                        }"
                                    />

                                    <datalist id="category-suggestions">
                                        <option
                                            v-for="item in suggestedCategories"
                                            :key="item"
                                            :value="item"
                                        />
                                    </datalist>

                                    <p
                                        v-if="form.errors.category"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.category }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- ==================================================
                             CONTENU
                        =================================================== -->

                        <section class="pt-8 border-t border-gray-100">
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Contenu
                            </h3>

                            <label
                                for="content"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Information transmise à l’IA
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea
                                id="content"
                                v-model="form.content"
                                rows="8"
                                required
                                placeholder="Rédigez l’information exactement comme vous voulez qu’elle soit comprise. Soyez clair et précis : l’IA répond uniquement à partir de ce texte."
                                class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                :class="{
                                    'border-red-400 focus:border-red-500 focus:ring-red-500':
                                        form.errors.content,
                                }"
                            ></textarea>

                            <p
                                v-if="form.errors.content"
                                class="mt-1.5 text-sm text-red-600"
                            >
                                {{ form.errors.content }}
                            </p>
                        </section>

                        <!-- ==================================================
                             STATUT
                        =================================================== -->

                        <section class="pt-8 border-t border-gray-100">
                            <label
                                class="flex items-start gap-3 rounded-xl bg-gray-50 p-4"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />

                                <span>
                                    <span class="block text-sm font-medium text-gray-800">
                                        Activer cette entrée immédiatement
                                    </span>

                                    <span class="mt-0.5 block text-sm text-gray-500">
                                        Une entrée active est transmise à
                                        l’IA dès le prochain message client.
                                    </span>
                                </span>
                            </label>
                        </section>

                        <!-- ==================================================
                             ACTIONS
                        =================================================== -->

                        <div
                            class="flex flex-col-reverse gap-3 pt-6 border-t border-gray-100 sm:flex-row sm:justify-end"
                        >
                            <Link
                                :href="route('knowledge-base.index')"
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
                                        : "Ajouter l’entrée"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
