<script setup>
import { computed } from "vue";

import {
    Head,
    Link,
    useForm,
} from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const form = useForm({
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    company: "",
    address: "",
    city: "",
    country: "",
    status: "active",
    notes: "",
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
| Soumission
|--------------------------------------------------------------------------
*/

const submit = () => {
    form.post(route("clients.store"), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nouveau client" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-night-800"
                    >
                        Nouveau client
                    </h2>

                    <p class="mt-1 text-sm text-night-400">
                        Ajoutez un nouveau client à votre organisation.
                    </p>
                </div>

                <Link
                    :href="route('clients.index')"
                    class="inline-flex items-center justify-center rounded-xl border border-line-strong bg-white px-4 py-2.5 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                >
                    ← Retour aux clients
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div
                class="max-w-4xl px-4 mx-auto sm:px-6 lg:px-8"
            >
                <!-- Erreur générale -->

                <div
                    v-if="hasErrors"
                    class="p-4 mb-6 border border-rose-200 rounded-xl bg-rose-50"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-lg">⚠️</span>

                        <div>
                            <p class="font-semibold text-rose-800">
                                Vérifiez les informations saisies.
                            </p>

                            <p class="mt-1 text-sm text-rose-700">
                                Certains champs nécessitent une correction.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Formulaire -->

                <div
                    class="overflow-hidden bg-white shadow-sm rounded-2xl ring-1 ring-line"
                >
                    <div
                        class="px-6 py-6 border-b border-line bg-gradient-to-r from-brand-50 to-white sm:px-8"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex items-center justify-center w-12 h-12 text-xl bg-brand-100 rounded-xl"
                            >
                                👤
                            </div>

                            <div>
                                <h1
                                    class="text-lg font-semibold text-night-900"
                                >
                                    Informations du client
                                </h1>

                                <p
                                    class="mt-1 text-sm text-night-400"
                                >
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
                             IDENTITÉ
                        =================================================== -->

                        <section>
                            <h3
                                class="mb-4 text-base font-semibold text-night-900"
                            >
                                Identité
                            </h3>

                            <div
                                class="grid grid-cols-1 gap-5 md:grid-cols-2"
                            >
                                <!-- Prénom -->

                                <div>
                                    <label
                                        for="first_name"
                                        class="block text-sm font-medium text-night-600"
                                    >
                                        Prénom
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <input
                                        id="first_name"
                                        v-model="form.first_name"
                                        type="text"
                                        autocomplete="given-name"
                                        required
                                        class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                        :class="{
                                            'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                                form.errors.first_name,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.first_name"
                                        class="mt-1.5 text-sm text-rose-600"
                                    >
                                        {{ form.errors.first_name }}
                                    </p>
                                </div>

                                <!-- Nom -->

                                <div>
                                    <label
                                        for="last_name"
                                        class="block text-sm font-medium text-night-600"
                                    >
                                        Nom
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <input
                                        id="last_name"
                                        v-model="form.last_name"
                                        type="text"
                                        autocomplete="family-name"
                                        required
                                        class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                        :class="{
                                            'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                                form.errors.last_name,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.last_name"
                                        class="mt-1.5 text-sm text-rose-600"
                                    >
                                        {{ form.errors.last_name }}
                                    </p>
                                </div>

                                <!-- Téléphone -->

                                <div>
                                    <label
                                        for="phone"
                                        class="block text-sm font-medium text-night-600"
                                    >
                                        Téléphone
                                        <span class="text-rose-500">*</span>
                                    </label>

                                    <input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        autocomplete="tel"
                                        required
                                        placeholder="+225 ..."
                                        class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                        :class="{
                                            'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                                form.errors.phone,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.phone"
                                        class="mt-1.5 text-sm text-rose-600"
                                    >
                                        {{ form.errors.phone }}
                                    </p>
                                </div>

                                <!-- Email -->

                                <div>
                                    <label
                                        for="email"
                                        class="block text-sm font-medium text-night-600"
                                    >
                                        Email
                                    </label>

                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
                                        placeholder="client@example.com"
                                        class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                        :class="{
                                            'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                                form.errors.email,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.email"
                                        class="mt-1.5 text-sm text-rose-600"
                                    >
                                        {{ form.errors.email }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- ==================================================
                             ENTREPRISE
                        =================================================== -->

                        <section class="pt-8 border-t border-line">
                            <h3
                                class="mb-4 text-base font-semibold text-night-900"
                            >
                                Informations professionnelles
                            </h3>

                            <div>
                                <label
                                    for="company"
                                    class="block text-sm font-medium text-night-600"
                                >
                                    Entreprise
                                </label>

                                <input
                                    id="company"
                                    v-model="form.company"
                                    type="text"
                                    autocomplete="organization"
                                    placeholder="Nom de l’entreprise"
                                    class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                    :class="{
                                        'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                            form.errors.company,
                                    }"
                                />

                                <p
                                    v-if="form.errors.company"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ form.errors.company }}
                                </p>
                            </div>
                        </section>

                        <!-- ==================================================
                             LOCALISATION
                        =================================================== -->

                        <section class="pt-8 border-t border-line">
                            <h3
                                class="mb-4 text-base font-semibold text-night-900"
                            >
                                Localisation
                            </h3>

                            <div
                                class="grid grid-cols-1 gap-5 md:grid-cols-2"
                            >
                                <!-- Adresse -->

                                <div class="md:col-span-2">
                                    <label
                                        for="address"
                                        class="block text-sm font-medium text-night-600"
                                    >
                                        Adresse
                                    </label>

                                    <input
                                        id="address"
                                        v-model="form.address"
                                        type="text"
                                        autocomplete="street-address"
                                        placeholder="Adresse complète"
                                        class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                        :class="{
                                            'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                                form.errors.address,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.address"
                                        class="mt-1.5 text-sm text-rose-600"
                                    >
                                        {{ form.errors.address }}
                                    </p>
                                </div>

                                <!-- Ville -->

                                <div>
                                    <label
                                        for="city"
                                        class="block text-sm font-medium text-night-600"
                                    >
                                        Ville
                                    </label>

                                    <input
                                        id="city"
                                        v-model="form.city"
                                        type="text"
                                        autocomplete="address-level2"
                                        placeholder="Abidjan"
                                        class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                        :class="{
                                            'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                                form.errors.city,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.city"
                                        class="mt-1.5 text-sm text-rose-600"
                                    >
                                        {{ form.errors.city }}
                                    </p>
                                </div>

                                <!-- Pays -->

                                <div>
                                    <label
                                        for="country"
                                        class="block text-sm font-medium text-night-600"
                                    >
                                        Pays
                                    </label>

                                    <input
                                        id="country"
                                        v-model="form.country"
                                        type="text"
                                        autocomplete="country-name"
                                        placeholder="Côte d’Ivoire"
                                        class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                        :class="{
                                            'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                                form.errors.country,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.country"
                                        class="mt-1.5 text-sm text-rose-600"
                                    >
                                        {{ form.errors.country }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- ==================================================
                             STATUT
                        =================================================== -->

                        <section class="pt-8 border-t border-line">
                            <h3
                                class="mb-4 text-base font-semibold text-night-900"
                            >
                                Gestion du client
                            </h3>

                            <div class="max-w-md">
                                <label
                                    for="status"
                                    class="block text-sm font-medium text-night-600"
                                >
                                    Statut
                                </label>

                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1.5 block w-full rounded-xl border-line-strong shadow-sm focus:border-brand-400 focus:ring-brand-400"
                                    :class="{
                                        'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                            form.errors.status,
                                    }"
                                >
                                    <option value="active">
                                        Actif
                                    </option>

                                    <option value="inactive">
                                        Inactif
                                    </option>

                                    <option value="blocked">
                                        Bloqué
                                    </option>
                                </select>

                                <p
                                    v-if="form.errors.status"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ form.errors.status }}
                                </p>
                            </div>
                        </section>

                        <!-- ==================================================
                             NOTES
                        =================================================== -->

                        <section class="pt-8 border-t border-line">
                            <h3
                                class="mb-4 text-base font-semibold text-night-900"
                            >
                                Notes internes
                            </h3>

                            <label
                                for="notes"
                                class="sr-only"
                            >
                                Notes
                            </label>

                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="5"
                                placeholder="Informations supplémentaires concernant ce client..."
                                class="block w-full border-line-strong shadow-sm rounded-xl focus:border-brand-400 focus:ring-brand-400"
                                :class="{
                                    'border-rose-400 focus:border-rose-500 focus:ring-rose-500':
                                        form.errors.notes,
                                }"
                            ></textarea>

                            <p
                                v-if="form.errors.notes"
                                class="mt-1.5 text-sm text-rose-600"
                            >
                                {{ form.errors.notes }}
                            </p>
                        </section>

                        <!-- ==================================================
                             ACTIONS
                        =================================================== -->

                        <div
                            class="flex flex-col-reverse gap-3 pt-6 border-t border-line sm:flex-row sm:justify-end"
                        >
                            <Link
                                :href="route('clients.index')"
                                class="inline-flex items-center justify-center rounded-xl border border-line-strong bg-white px-5 py-2.5 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                            >
                                Annuler
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span
                                    v-if="form.processing"
                                    class="w-4 h-4 mr-2 border-2 border-white rounded-full animate-spin border-t-transparent"
                                ></span>

                                {{
                                    form.processing
                                        ? "Création..."
                                        : "Créer le client"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
