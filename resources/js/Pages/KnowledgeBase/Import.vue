<script setup>
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";

defineProps({
    categories: { type: Array, default: () => [] },
});

const form = useForm({
    content: "",
    category: "",
    replace_existing: false,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        category: data.category || null,
        replace_existing: Boolean(data.replace_existing),
    })).post(route("knowledge.import.store"));
};

/*
 * Aperçu du découpage, calculé côté navigateur avec la même règle que
 * le serveur : le responsable voit ce qu'il va obtenir avant d'importer.
 */
const preview = computed(() => {
    const blocks = form.content
        .trim()
        .split(/\r?\n\s*\r?\n/)
        .map((block) =>
            block
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter(Boolean),
        )
        .filter((lines) => lines.length >= 2);

    return blocks.map((lines) => ({
        title: lines[0],
        lines: lines.length - 1,
    }));
});

const example = `Horaires d'ouverture
Nous sommes ouverts du lundi au samedi, de 8h à 18h.
Fermé les dimanches et jours fériés.

Frais de livraison
La livraison est gratuite à Abidjan pour toute commande
supérieure à 25 000 FCFA. En dehors d'Abidjan, comptez
3 000 FCFA.

Délai de remboursement
Un remboursement est traité sous 5 à 7 jours ouvrés
après réception du produit retourné.`;

const useExample = () => {
    form.content = example;
};
</script>

<template>
    <Head title="Importer des fiches" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Importer votre FAQ"
                description="Collez votre document existant plutôt que de tout ressaisir."
            >
                <template #actions>
                    <Link
                        :href="route('knowledge.index')"
                        class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Retour
                    </Link>
                </template>
            </PageHeader>

            <FlashMessages />

            <form class="space-y-6" @submit.prevent="submit">
                <SurfaceCard
                    title="Format attendu"
                    description="Une fiche par bloc. Titre sur la première ligne, contenu en dessous, ligne vide entre deux fiches."
                >
                    <pre
                        class="overflow-x-auto rounded-xl bg-canvas-sunken p-4 text-xs leading-relaxed text-night-600"
                    >{{ example }}</pre>

                    <button
                        type="button"
                        class="mt-3 text-sm font-medium text-brand-600 transition hover:text-brand-700"
                        @click="useExample"
                    >
                        Remplir avec cet exemple
                    </button>
                </SurfaceCard>

                <SurfaceCard>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Vos fiches
                            </label>

                            <textarea
                                v-model="form.content"
                                rows="16"
                                placeholder="Collez ici votre FAQ, vos conditions, vos tarifs…"
                                class="mt-1 w-full rounded-xl border-line font-mono text-sm leading-relaxed focus:border-brand-400 focus:ring-brand-400"
                            ></textarea>

                            <p
                                v-if="form.errors.content"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.content }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Catégorie appliquée à toutes les fiches
                            </label>

                            <select
                                v-model="form.category"
                                class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            >
                                <option value="">Aucune</option>
                                <option
                                    v-for="item in categories"
                                    :key="item"
                                    :value="item"
                                >
                                    {{ item }}
                                </option>
                            </select>
                        </div>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3"
                        >
                            <input
                                v-model="form.replace_existing"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-400"
                            />
                            <span>
                                <span
                                    class="block text-sm font-medium text-rose-900"
                                >
                                    Remplacer toute la base existante
                                </span>
                                <span class="mt-0.5 block text-xs text-rose-700">
                                    Les fiches actuelles seront supprimées
                                    définitivement. Sans cette case, l'import
                                    s'ajoute à ce qui existe déjà.
                                </span>
                            </span>
                        </label>
                    </div>
                </SurfaceCard>

                <!-- Aperçu -->

                <SurfaceCard
                    v-if="preview.length"
                    :title="`${preview.length} fiche(s) détectée(s)`"
                    description="Vérifiez le découpage avant d'importer."
                >
                    <ul class="divide-y divide-line">
                        <li
                            v-for="(item, index) in preview.slice(0, 20)"
                            :key="index"
                            class="flex items-center justify-between gap-3 py-2 text-sm first:pt-0"
                        >
                            <span class="truncate font-medium text-night-800">
                                {{ item.title }}
                            </span>
                            <span class="shrink-0 text-xs text-night-400">
                                {{ item.lines }} ligne(s)
                            </span>
                        </li>
                    </ul>

                    <p
                        v-if="preview.length > 20"
                        class="mt-3 text-xs text-night-400"
                    >
                        … et {{ preview.length - 20 }} autres.
                    </p>
                </SurfaceCard>

                <div class="flex items-center justify-end gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing || !preview.length"
                        class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50"
                    >
                        Importer {{ preview.length || "" }} fiche(s)
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
