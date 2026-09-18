<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";

const props = defineProps({
    entry: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    suggestedTitle: { type: String, default: "" },
});

const isEdit = computed(() => Boolean(props.entry));

const form = useForm({
    title: props.entry?.title ?? props.suggestedTitle ?? "",
    category: props.entry?.category ?? "",
    content: props.entry?.content ?? "",
    is_active: props.entry?.is_active ?? true,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        category: data.category || null,
        is_active: Boolean(data.is_active),
    }));

    if (isEdit.value) {
        form.put(route("knowledge.update", props.entry.id));
    } else {
        form.post(route("knowledge.store"));
    }
};

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

/*
|--------------------------------------------------------------------------
| Photos
|--------------------------------------------------------------------------
|
| L'assistant ne peut envoyer que des photos rattachées à une fiche.
| Une chambre, un plat, un produit : la fiche décrit, les photos
| montrent.
|
*/

const uploads = ref([]);

const uploading = ref(false);

const pickImages = (event) => {
    uploads.value = Array.from(event.target.files ?? []).map((file) => ({
        file,
        caption: "",
        preview: URL.createObjectURL(file),
    }));
};

const sendImages = () => {
    if (!uploads.value.length || !props.entry) {
        return;
    }

    uploading.value = true;

    const payload = new FormData();

    uploads.value.forEach((item, index) => {
        payload.append(`images[${index}]`, item.file);
        payload.append(`captions[${index}]`, item.caption ?? "");
    });

    router.post(route("knowledge.images.store", props.entry.id), payload, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            uploads.value = [];
        },
        onFinish: () => {
            uploading.value = false;
        },
    });
};

const saveCaption = (image) =>
    router.patch(
        route("knowledge.images.update", image.id),
        { caption: image.caption },
        { preserveScroll: true },
    );

const removeImage = (image) => {
    if (window.confirm("Supprimer cette photo ?")) {
        router.delete(route("knowledge.images.destroy", image.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Modifier une fiche' : 'Nouvelle fiche'" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                :title="isEdit ? 'Modifier la fiche' : 'Nouvelle fiche'"
                description="Écrivez comme si vous répondiez à un client au téléphone."
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

            <form class="space-y-6" @submit.prevent="submit">
                <SurfaceCard>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Titre
                            </label>

                            <input
                                v-model="form.title"
                                type="text"
                                placeholder="Horaires d'ouverture"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />

                            <p class="mt-1 text-xs text-night-400">
                                C'est ce que l'assistant lit en premier quand il
                                cherche. Employez les mots de vos clients.
                            </p>

                            <p
                                v-if="form.errors.title"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Catégorie
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
                                    {{ label(item) }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Contenu
                            </label>

                            <textarea
                                v-model="form.content"
                                rows="12"
                                placeholder="Nous sommes ouverts du lundi au samedi, de 8h à 18h. Fermé les dimanches et jours fériés."
                                class="mt-1 w-full rounded-xl border-line text-sm leading-relaxed focus:border-brand-400 focus:ring-brand-400"
                            ></textarea>

                            <p class="mt-1 text-xs text-night-400">
                                Soyez précis et factuel. L'assistant reformulera
                                selon le ton réglé dans l'Autopilot, mais il
                                n'inventera jamais ce qui n'est pas écrit ici.
                            </p>

                            <p
                                v-if="form.errors.content"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.content }}
                            </p>
                        </div>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-line px-4 py-3 transition hover:bg-canvas-sunken"
                        >
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-line-strong text-brand-500 focus:ring-brand-400"
                            />
                            <span>
                                <span
                                    class="block text-sm font-medium text-night-800"
                                >
                                    Active
                                </span>
                                <span class="mt-0.5 block text-xs text-night-400">
                                    Une fiche désactivée reste enregistrée mais
                                    l'assistant ne la consulte plus. Utile
                                    pendant une promotion terminée.
                                </span>
                            </span>
                        </label>
                    </div>
                </SurfaceCard>

                <div class="flex items-center justify-end gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50"
                    >
                        {{ isEdit ? "Enregistrer" : "Ajouter la fiche" }}
                    </button>
                </div>
            </form>

            <!-- Photos : seulement sur une fiche déjà créée -->

            <SurfaceCard
                v-if="isEdit"
                title="Photos"
                description="L'assistant les enverra au client qui demande à voir ce que décrit cette fiche."
            >
                <!-- Photos existantes -->

                <div
                    v-if="entry.images?.length"
                    class="grid gap-3 sm:grid-cols-3"
                >
                    <figure
                        v-for="image in entry.images"
                        :key="image.id"
                        class="overflow-hidden rounded-xl border border-line"
                    >
                        <img
                            :src="image.url"
                            :alt="image.caption ?? ''"
                            class="h-28 w-full object-cover"
                        />

                        <figcaption class="space-y-2 p-2">
                            <input
                                v-model="image.caption"
                                type="text"
                                maxlength="150"
                                placeholder="Légende"
                                class="w-full rounded-lg border-line text-xs focus:border-brand-400 focus:ring-brand-400"
                                @blur="saveCaption(image)"
                            />

                            <button
                                type="button"
                                class="text-xs text-rose-600 transition hover:text-rose-700"
                                @click="removeImage(image)"
                            >
                                Supprimer
                            </button>
                        </figcaption>
                    </figure>
                </div>

                <p v-else class="text-sm text-night-400">
                    Aucune photo pour cette fiche.
                </p>

                <!-- Ajout -->

                <div class="mt-5 border-t border-line pt-5">
                    <label
                        class="inline-block cursor-pointer rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Choisir des photos
                        <input
                            type="file"
                            multiple
                            class="hidden"
                            accept="image/png,image/jpeg,image/webp"
                            @change="pickImages"
                        />
                    </label>

                    <p class="mt-2 text-xs text-night-400">
                        JPG, PNG ou WebP. 3 Mo par photo, huit au maximum.
                        La légende aide l'assistant à choisir la bonne image.
                    </p>

                    <div v-if="uploads.length" class="mt-4 space-y-3">
                        <div
                            v-for="(item, index) in uploads"
                            :key="index"
                            class="flex items-center gap-3"
                        >
                            <img
                                :src="item.preview"
                                alt=""
                                class="h-14 w-14 shrink-0 rounded-lg object-cover ring-1 ring-line"
                            />

                            <input
                                v-model="item.caption"
                                type="text"
                                maxlength="150"
                                placeholder="Légende (Chambre Deluxe, vue mer…)"
                                class="w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                        </div>

                        <button
                            type="button"
                            :disabled="uploading"
                            class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50"
                            @click="sendImages"
                        >
                            {{
                                uploading
                                    ? "Envoi…"
                                    : `Ajouter ${uploads.length} photo(s)`
                            }}
                        </button>
                    </div>
                </div>
            </SurfaceCard>

            <p
                v-else
                class="rounded-xl bg-canvas-sunken px-4 py-3 text-sm text-night-500"
            >
                Enregistrez d'abord la fiche : vous pourrez ensuite y
                ajouter des photos.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
