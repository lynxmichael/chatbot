<script setup>
import { computed } from "vue";

import {
    Head,
    Link,
    useForm,
} from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    client: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    first_name: props.client.first_name || "",
    last_name: props.client.last_name || "",
    email: props.client.email || "",
    phone: props.client.phone || "",
    company: props.client.company || "",
    address: props.client.address || "",
    city: props.client.city || "",
    country: props.client.country || "",
    status: props.client.status || "active",
    notes: props.client.notes || "",
});

const hasErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});

const fullName = computed(() => {
    return [
        props.client.first_name,
        props.client.last_name,
    ]
        .filter(Boolean)
        .join(" ") || "Client";
});

const submit = () => {
    form.put(
        route(
            "clients.update",
            props.client.id
        ),
        {
            preserveScroll: true,
        }
    );
};
</script>

<template>
    <Head :title="`Modifier ${fullName}`" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-xl font-semibold text-gray-800"
                    >
                        Modifier le client
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Mise à jour des informations de {{ fullName }}.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="
                            route(
                                'clients.show',
                                client.id
                            )
                        "
                        class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Voir la fiche
                    </Link>

                    <Link
                        :href="route('clients.index')"
                        class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        ← Retour
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div
                class="max-w-4xl px-4 mx-auto sm:px-6 lg:px-8"
            >
                <!-- Erreur générale -->

                <div
                    v-if="hasErrors"
                    class="p-4 mb-6 border border-red-200 rounded-xl bg-red-50"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-lg">
                            ⚠️
                        </span>

                        <div>
                            <p
                                class="font-semibold text-red-800"
                            >
                                Impossible d’enregistrer les modifications.
                            </p>

                            <p
                                class="mt-1 text-sm text-red-700"
                            >
                                Vérifiez les champs signalés ci-dessous.
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
                        <div
                            class="flex items-center gap-4"
                        >
                            <div
                                class="flex items-center justify-center w-12 h-12 text-xl bg-indigo-100 rounded-xl"
                            >
                                ✏️
                            </div>

                            <div>
                                <h1
                                    class="text-lg font-semibold text-gray-900"
                                >
                                    Modifier les informations
                                </h1>

                                <p
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    Client #{{ client.id }}
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
                                class="mb-4 text-base font-semibold text-gray-900"
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
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Prénom
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        id="first_name"
                                        v-model="form.first_name"
                                        type="text"
                                        autocomplete="given-name"
                                        required
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.first_name,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.first_name"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.first_name }}
                                    </p>
                                </div>

                                <!-- Nom -->

                                <div>
                                    <label
                                        for="last_name"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Nom
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        id="last_name"
                                        v-model="form.last_name"
                                        type="text"
                                        autocomplete="family-name"
                                        required
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.last_name,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.last_name"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.last_name }}
                                    </p>
                                </div>

                                <!-- Téléphone -->

                                <div>
                                    <label
                                        for="phone"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Téléphone
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        autocomplete="tel"
                                        required
                                        placeholder="+225 ..."
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.phone,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.phone"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.phone }}
                                    </p>
                                </div>

                                <!-- Email -->

                                <div>
                                    <label
                                        for="email"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Email
                                    </label>

                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
                                        placeholder="client@example.com"
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.email,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.email"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.email }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- ==================================================
                             ENTREPRISE
                        =================================================== -->

                        <section class="pt-8 border-t border-gray-100">
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Informations professionnelles
                            </h3>

                            <div>
                                <label
                                    for="company"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Entreprise
                                </label>

                                <input
                                    id="company"
                                    v-model="form.company"
                                    type="text"
                                    autocomplete="organization"
                                    placeholder="Nom de l’entreprise"
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="{
                                        'border-red-400 focus:border-red-500 focus:ring-red-500':
                                            form.errors.company,
                                    }"
                                />

                                <p
                                    v-if="form.errors.company"
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.company }}
                                </p>
                            </div>
                        </section>

                        <!-- ==================================================
                             LOCALISATION
                        =================================================== -->

                        <section class="pt-8 border-t border-gray-100">
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Localisation
                            </h3>

                            <div
                                class="grid grid-cols-1 gap-5 md:grid-cols-2"
                            >
                                <div class="md:col-span-2">
                                    <label
                                        for="address"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Adresse
                                    </label>

                                    <input
                                        id="address"
                                        v-model="form.address"
                                        type="text"
                                        autocomplete="street-address"
                                        placeholder="Adresse complète"
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.address,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.address"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.address }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="city"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Ville
                                    </label>

                                    <input
                                        id="city"
                                        v-model="form.city"
                                        type="text"
                                        autocomplete="address-level2"
                                        placeholder="Abidjan"
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.city,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.city"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.city }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="country"
                                        class="block text-sm font-medium text-gray-700"
                                    >
                                        Pays
                                    </label>

                                    <input
                                        id="country"
                                        v-model="form.country"
                                        type="text"
                                        autocomplete="country-name"
                                        placeholder="Côte d’Ivoire"
                                        class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :class="{
                                            'border-red-400 focus:border-red-500 focus:ring-red-500':
                                                form.errors.country,
                                        }"
                                    />

                                    <p
                                        v-if="form.errors.country"
                                        class="mt-1.5 text-sm text-red-600"
                                    >
                                        {{ form.errors.country }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- ==================================================
                             STATUT
                        =================================================== -->

                        <section class="pt-8 border-t border-gray-100">
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Gestion du client
                            </h3>

                            <div class="max-w-md">
                                <label
                                    for="status"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Statut
                                </label>

                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="{
                                        'border-red-400 focus:border-red-500 focus:ring-red-500':
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
                                    class="mt-1.5 text-sm text-red-600"
                                >
                                    {{ form.errors.status }}
                                </p>
                            </div>
                        </section>

                        <!-- ==================================================
                             NOTES
                        =================================================== -->

                        <section class="pt-8 border-t border-gray-100">
                            <h3
                                class="mb-4 text-base font-semibold text-gray-900"
                            >
                                Notes internes
                            </h3>

                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="5"
                                placeholder="Informations supplémentaires concernant ce client..."
                                class="block w-full border-gray-300 shadow-sm rounded-xl focus:border-indigo-500 focus:ring-indigo-500"
                                :class="{
                                    'border-red-400 focus:border-red-500 focus:ring-red-500':
                                        form.errors.notes,
                                }"
                            ></textarea>

                            <p
                                v-if="form.errors.notes"
                                class="mt-1.5 text-sm text-red-600"
                            >
                                {{ form.errors.notes }}
                            </p>
                        </section>

                        <!-- ==================================================
                             ACTIONS
                        =================================================== -->

                        <div
                            class="flex flex-col-reverse gap-3 pt-6 border-t border-gray-100 sm:flex-row sm:justify-end"
                        >
                            <Link
                                :href="
                                    route(
                                        'clients.show',
                                        client.id
                                    )
                                "
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
</template>
