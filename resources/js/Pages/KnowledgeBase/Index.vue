<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";

const props = defineProps({
    entries: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statistics: { type: Object, default: () => ({}) },
    gaps: { type: Array, default: () => [] },
});

const search = ref(props.filters.search ?? "");
const category = ref(props.filters.category ?? "");
const status = ref(props.filters.status ?? "");

const applyFilters = () => {
    router.get(
        route("knowledge.index"),
        {
            search: search.value || undefined,
            category: category.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer = null;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 350);
});

watch([category, status], applyFilters);

const categoryLabels = {
    facturation: "Facturation",
    livraison: "Livraison",
    technique: "Technique",
    reclamation: "Réclamation",
    remboursement: "Remboursement",
    commande: "Commande",
    compte: "Compte",
    general: "Général",
};

const label = (value) => categoryLabels[value] ?? value;

const toggle = (entry) =>
    router.patch(
        route("knowledge.toggle", entry.id),
        {},
        { preserveScroll: true },
    );

const remove = (entry) => {
    if (
        window.confirm(
            `Supprimer « ${entry.title} » ? L'assistant ne pourra plus s'en servir.`,
        )
    ) {
        router.delete(route("knowledge.destroy", entry.id), {
            preserveScroll: true,
        });
    }
};

/*
 * Une question restée sans réponse devient le titre d'une nouvelle fiche.
 */
const writeAnswer = (gap) =>
    router.visit(route("knowledge.create", { question: gap.question }));

const isEmpty = computed(
    () => props.statistics.total === 0,
);
</script>

<template>
    <Head title="Base de connaissances" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Base de connaissances"
                description="Ce que votre assistant sait. C'est d'ici que viennent ses réponses."
            >
                <template #actions>
                    <Link
                        :href="route('knowledge.import')"
                        class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Importer
                    </Link>

                    <Link
                        :href="route('knowledge.create')"
                        class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"
                    >
                        Nouvelle fiche
                    </Link>
                </template>
            </PageHeader>

            <FlashMessages />

            <!-- Base vide : c'est le cas le plus important à traiter -->

            <div
                v-if="isEmpty"
                class="rounded-2xl border border-amber-200 bg-amber-50 p-6"
            >
                <h2 class="font-semibold text-amber-900">
                    Votre assistant ne sait encore rien
                </h2>

                <p class="mt-1 text-sm leading-relaxed text-amber-800">
                    Sans fiches, il ne peut répondre à aucune question sur vos
                    horaires, vos prix ou vos procédures : il transférera tout à
                    un conseiller. Le plus rapide est de coller votre FAQ
                    existante.
                </p>

                <Link
                    :href="route('knowledge.import')"
                    class="mt-4 inline-block rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700"
                >
                    Importer ma FAQ
                </Link>
            </div>

            <!-- Questions sans réponse -->

            <SurfaceCard
                v-if="gaps.length"
                title="Questions restées sans réponse"
                description="Posées par vos clients ces 30 derniers jours, sans fiche correspondante."
            >
                <ul class="divide-y divide-line">
                    <li
                        v-for="gap in gaps"
                        :key="gap.question"
                        class="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate text-sm text-night-800"
                            >
                                {{ gap.question }}
                            </span>
                            <span class="text-xs text-night-400">
                                {{ gap.occurrences }} fois
                            </span>
                        </span>

                        <button
                            type="button"
                            class="shrink-0 rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                            @click="writeAnswer(gap)"
                        >
                            Écrire la réponse
                        </button>
                    </li>
                </ul>
            </SurfaceCard>

            <!-- Filtres -->

            <SurfaceCard v-if="!isEmpty">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <label class="block text-xs font-medium text-night-500">
                            Rechercher
                        </label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Titre ou contenu…"
                            class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Catégorie
                        </label>
                        <select
                            v-model="category"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Toutes</option>
                            <option
                                v-for="item in categories"
                                :key="item"
                                :value="item"
                            >
                                {{ label(item) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            État
                        </label>
                        <select
                            v-model="status"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Toutes</option>
                            <option value="active">Actives</option>
                            <option value="inactive">Désactivées</option>
                        </select>
                    </div>

                    <p class="ml-auto self-center text-sm text-night-400">
                        {{ statistics.active }} fiche(s) active(s) sur
                        {{ statistics.total }}
                    </p>
                </div>
            </SurfaceCard>

            <!-- Liste -->

            <SurfaceCard v-if="!isEmpty" flush>
                <div v-if="entries.data.length" class="divide-y divide-line">
                    <article
                        v-for="entry in entries.data"
                        :key="entry.id"
                        class="flex flex-wrap items-start gap-4 px-6 py-4 transition hover:bg-canvas-sunken"
                        :class="!entry.is_active && 'opacity-60'"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-night-900">
                                    {{ entry.title }}
                                </p>

                                <span
                                    v-if="entry.category"
                                    class="rounded-md bg-canvas-sunken px-2 py-0.5 text-[11px] font-medium text-night-600 ring-1 ring-line"
                                >
                                    {{ label(entry.category) }}
                                </span>

                                <span
                                    v-if="!entry.is_active"
                                    class="rounded-md bg-night-100 px-2 py-0.5 text-[11px] font-semibold text-night-600"
                                >
                                    désactivée
                                </span>
                            </div>

                            <p
                                class="mt-1 line-clamp-2 text-sm leading-relaxed text-night-500"
                            >
                                {{ entry.excerpt }}
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            <Link
                                :href="route('knowledge.edit', entry.id)"
                                class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-night-600 transition hover:bg-white"
                            >
                                Modifier
                            </Link>

                            <button
                                type="button"
                                class="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-night-500 transition hover:bg-white"
                                @click="toggle(entry)"
                            >
                                {{ entry.is_active ? "Désactiver" : "Activer" }}
                            </button>

                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50"
                                @click="remove(entry)"
                            >
                                Supprimer
                            </button>
                        </div>
                    </article>
                </div>

                <EmptyState
                    v-else
                    icon="🔍"
                    title="Aucune fiche"
                    description="Aucune fiche ne correspond à cette recherche."
                />
            </SurfaceCard>

            <!-- Pagination -->

            <div
                v-if="entries.links && entries.links.length > 3"
                class="flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in entries.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-lg px-3 py-1.5 text-sm transition"
                    :class="[
                        link.active
                            ? 'bg-night-800 text-white'
                            : 'bg-white text-night-600 ring-1 ring-line hover:bg-canvas-sunken',
                        !link.url && 'pointer-events-none opacity-40',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
