<script setup>
import { computed, ref, watch } from "vue";

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
    entries: {
        type: Object,
        required: true,
    },

    categories: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: "",
            category: "",
        }),
    },

    statistics: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            inactive: 0,
            categories: 0,
        }),
    },
});

/*
|--------------------------------------------------------------------------
| Recherche et filtre
|--------------------------------------------------------------------------
*/

const search = ref(props.filters.search || "");
const category = ref(props.filters.category || "");

let searchTimeout = null;

const applyFilters = () => {
    router.get(
        route("knowledge-base.index"),
        {
            search: search.value || undefined,
            category: category.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);

    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 400);
});

watch(category, () => {
    applyFilters();
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
| Activer / désactiver
|--------------------------------------------------------------------------
*/

const toggling = ref(false);

const toggleEntry = (entry) => {
    if (toggling.value) {
        return;
    }

    toggling.value = true;

    router.patch(
        route("knowledge-base.toggle", entry.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,

            onFinish: () => {
                toggling.value = false;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Suppression
|--------------------------------------------------------------------------
*/

const deleting = ref(false);

const deleteEntry = (entry) => {
    if (deleting.value) {
        return;
    }

    if (
        !confirm(
            `Supprimer définitivement « ${entry.title} » de la base de connaissances ?`
        )
    ) {
        return;
    }

    deleting.value = true;

    router.delete(
        route("knowledge-base.destroy", entry.id),
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
| Aperçu du contenu
|--------------------------------------------------------------------------
*/

const excerpt = (content, length = 120) => {
    const text = (content || "").trim();

    if (text.length <= length) {
        return text;
    }

    return text.slice(0, length).trim() + "…";
};
</script>

<template>
    <Head title="Base de connaissances" />

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
                        class="text-xl font-semibold leading-tight text-gray-800"
                    >
                        Base de connaissances
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Les informations que l’IA utilise pour répondre
                        automatiquement à vos clients.
                    </p>
                </div>

                <Link
                    :href="route('knowledge-base.create')"
                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                >
                    + Nouvelle entrée
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
                    class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700"
                >
                    ⚠️ {{ errorMessage }}
                </div>

                <!-- ======================================================
                     STATISTIQUES
                ======================================================= -->

                <div
                    class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <div
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">
                                    Total entrées
                                </p>

                                <p
                                    class="mt-2 text-3xl font-bold text-gray-900"
                                >
                                    {{ statistics.total }}
                                </p>
                            </div>

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-xl"
                            >
                                📚
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">
                                    Actives (utilisées par l’IA)
                                </p>

                                <p
                                    class="mt-2 text-3xl font-bold text-emerald-600"
                                >
                                    {{ statistics.active }}
                                </p>
                            </div>

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl"
                            >
                                ✅
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">
                                    Désactivées
                                </p>

                                <p
                                    class="mt-2 text-3xl font-bold text-gray-500"
                                >
                                    {{ statistics.inactive }}
                                </p>
                            </div>

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-xl"
                            >
                                ⏸️
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">
                                    Catégories
                                </p>

                                <p
                                    class="mt-2 text-3xl font-bold text-blue-600"
                                >
                                    {{ statistics.categories }}
                                </p>
                            </div>

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-xl"
                            >
                                🏷️
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======================================================
                     RECHERCHE / FILTRE
                ======================================================= -->

                <div
                    class="mb-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100"
                >
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-end"
                    >
                        <div class="w-full md:flex-1">
                            <label
                                for="search"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                Rechercher
                            </label>

                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"
                                >
                                    🔎
                                </span>

                                <input
                                    id="search"
                                    v-model="search"
                                    type="text"
                                    placeholder="Titre, catégorie ou contenu..."
                                    class="w-full rounded-xl border-gray-300 py-3 pl-11 pr-4 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>

                        <div class="w-full md:w-64">
                            <label
                                for="category"
                                class="mb-2 block text-sm font-medium text-gray-700"
                            >
                                Catégorie
                            </label>

                            <select
                                id="category"
                                v-model="category"
                                class="w-full rounded-xl border-gray-300 py-3 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">
                                    Toutes les catégories
                                </option>

                                <option
                                    v-for="item in categories"
                                    :key="item"
                                    :value="item"
                                >
                                    {{ item }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ======================================================
                     TABLEAU
                ======================================================= -->

                <div
                    class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-gray-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">
                                Entrées de la base de connaissances
                            </h2>

                            <p class="mt-1 text-sm text-gray-500">
                                Seules les entrées actives sont transmises
                                à l’IA lorsqu’elle répond à un client.
                            </p>
                        </div>

                        <span
                            class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700"
                        >
                            {{
                                entries.total ??
                                entries.data?.length ??
                                0
                            }}
                        </span>
                    </div>

                    <!-- ==================================================
                         DESKTOP
                    =================================================== -->

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Titre
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Catégorie
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Aperçu
                                    </th>

                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Statut
                                    </th>

                                    <th
                                        class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-if="!entries.data?.length">
                                    <td
                                        colspan="5"
                                        class="px-6 py-14 text-center"
                                    >
                                        <div class="text-5xl">📚</div>

                                        <h3
                                            class="mt-4 text-lg font-semibold text-gray-900"
                                        >
                                            Aucune entrée trouvée
                                        </h3>

                                        <p class="mt-2 text-sm text-gray-500">
                                            Ajoutez vos premières questions
                                            fréquentes pour que l’IA puisse
                                            y répondre automatiquement.
                                        </p>

                                        <Link
                                            :href="route('knowledge-base.create')"
                                            class="mt-5 inline-flex rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                                        >
                                            + Ajouter une entrée
                                        </Link>
                                    </td>
                                </tr>

                                <tr
                                    v-for="entry in entries.data"
                                    :key="entry.id"
                                    class="transition hover:bg-gray-50"
                                >
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900">
                                            {{ entry.title }}
                                        </div>

                                        <div class="mt-1 text-xs text-gray-400">
                                            ID #{{ entry.id }}
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            v-if="entry.category"
                                            class="inline-flex rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700"
                                        >
                                            {{ entry.category }}
                                        </span>

                                        <span
                                            v-else
                                            class="text-sm text-gray-400"
                                        >
                                            —
                                        </span>
                                    </td>

                                    <td class="max-w-md px-6 py-4 text-sm text-gray-600">
                                        {{ excerpt(entry.content) }}
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4">
                                        <button
                                            type="button"
                                            :disabled="toggling"
                                            class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                                            :class="
                                                entry.is_active
                                                    ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                            "
                                            @click="toggleEntry(entry)"
                                        >
                                            {{
                                                entry.is_active
                                                    ? "Active"
                                                    : "Désactivée"
                                            }}
                                        </button>
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <div class="flex justify-end gap-3">
                                            <Link
                                                :href="
                                                    route(
                                                        'knowledge-base.edit',
                                                        entry.id
                                                    )
                                                "
                                                class="font-medium text-blue-600 transition hover:text-blue-900"
                                            >
                                                Modifier
                                            </Link>

                                            <button
                                                type="button"
                                                :disabled="deleting"
                                                @click="deleteEntry(entry)"
                                                class="font-medium text-red-600 transition hover:text-red-900 disabled:cursor-not-allowed disabled:opacity-50"
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

                    <div class="divide-y divide-gray-100 lg:hidden">
                        <div
                            v-if="!entries.data?.length"
                            class="px-6 py-14 text-center"
                        >
                            <div class="text-5xl">📚</div>

                            <h3 class="mt-4 text-lg font-semibold text-gray-900">
                                Aucune entrée trouvée
                            </h3>

                            <p class="mt-2 text-sm text-gray-500">
                                Ajoutez vos premières questions fréquentes.
                            </p>

                            <Link
                                :href="route('knowledge-base.create')"
                                class="mt-5 inline-flex rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white"
                            >
                                + Ajouter une entrée
                            </Link>
                        </div>

                        <div
                            v-for="entry in entries.data"
                            :key="entry.id"
                            class="p-5"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <Link
                                        :href="
                                            route(
                                                'knowledge-base.edit',
                                                entry.id
                                            )
                                        "
                                        class="font-semibold text-gray-900 hover:text-indigo-600"
                                    >
                                        {{ entry.title }}
                                    </Link>

                                    <span
                                        v-if="entry.category"
                                        class="mt-1 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                    >
                                        {{ entry.category }}
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    :disabled="toggling"
                                    class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold disabled:opacity-50"
                                    :class="
                                        entry.is_active
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-gray-100 text-gray-700'
                                    "
                                    @click="toggleEntry(entry)"
                                >
                                    {{ entry.is_active ? "Active" : "Désactivée" }}
                                </button>
                            </div>

                            <p class="mt-3 text-sm text-gray-600">
                                {{ excerpt(entry.content, 140) }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link
                                    :href="
                                        route(
                                            'knowledge-base.edit',
                                            entry.id
                                        )
                                    "
                                    class="rounded-lg bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100"
                                >
                                    Modifier
                                </Link>

                                <button
                                    type="button"
                                    :disabled="deleting"
                                    @click="deleteEntry(entry)"
                                    class="rounded-lg bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
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
                        v-if="entries.links?.length > 3"
                        class="flex flex-wrap items-center justify-center gap-2 border-t border-gray-200 px-6 py-5"
                    >
                        <template
                            v-for="(link, index) in entries.links"
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
                                        ? 'border-indigo-600 bg-indigo-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                "
                            >
                                <span v-html="link.label"></span>
                            </Link>

                            <span
                                v-else
                                class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400"
                            >
                                <span v-html="link.label"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- ======================================================
                     INFORMATION
                ======================================================= -->

                <div
                    class="mt-6 rounded-2xl border border-indigo-100 bg-indigo-50 p-5"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100"
                        >
                            ℹ️
                        </div>

                        <div>
                            <h3 class="font-semibold text-indigo-900">
                                Comment l’IA utilise ces entrées
                            </h3>

                            <p class="mt-1 text-sm leading-6 text-indigo-800">
                                À chaque message client, l’IA consulte
                                toutes les entrées actives pour construire
                                sa réponse. Elle ne répond qu’à partir de
                                ces informations : désactivez une entrée
                                obsolète plutôt que de la supprimer si vous
                                pensez la réutiliser plus tard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
