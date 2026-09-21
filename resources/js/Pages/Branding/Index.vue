<script setup>
import { computed, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";

const props = defineProps({
    branding: { type: Object, required: true },
    widget_token: { type: String, required: true },
    widget_url: { type: String, required: true },
    api_url: { type: String, required: true },
    demo_url: { type: String, default: null },
});

const demoCopied = ref(false);

const copyDemo = async () => {
    try {
        await navigator.clipboard.writeText(props.demo_url);
        demoCopied.value = true;
        setTimeout(() => (demoCopied.value = false), 2000);
    } catch {
        /* Le lien reste sélectionnable à la main. */
    }
};

const form = useForm({
    brand_color: props.branding.brand_color ?? "#4f46e5",
    support_email: props.branding.support_email ?? "",
    welcome_message: props.branding.welcome_message ?? "",
    logo: null,
    remove_logo: false,
});

/*
 * Aperçu local du logo choisi, avant envoi : voir son logo dans le
 * widget vaut mieux que de deviner comment il sera recadré.
 */
const preview = ref(props.branding.logo);

const pickLogo = (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    form.logo = file;
    form.remove_logo = false;

    preview.value = URL.createObjectURL(file);
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    preview.value = null;
};

const submit = () =>
    form.post(route("branding.update"), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.logo = null;
            form.remove_logo = false;
        },
    });

const initials = computed(
    () =>
        props.branding.name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word[0])
            .join("")
            .toUpperCase() || "?",
);

const snippet = computed(
    () =>
        `<script
  src="${props.widget_url}"
  data-api="${props.api_url}"
  data-token="${props.widget_token}"
><\/script>`,
);

const copied = ref(false);

const copy = async () => {
    try {
        await navigator.clipboard.writeText(snippet.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        /* Le presse-papiers peut être refusé : le code reste sélectionnable. */
    }
};
</script>

<template>
    <Head title="Apparence" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Apparence"
                description="Ce que vos clients voient : le widget posé sur votre site et vos emails."
            />

            <FlashMessages />

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <!-- Réglages -->

                <form class="space-y-6" @submit.prevent="submit">
                    <SurfaceCard title="Logo">
                        <div class="flex items-center gap-5">
                            <span
                                class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl ring-1 ring-line"
                                :style="!preview ? { background: form.brand_color } : {}"
                            >
                                <img
                                    v-if="preview"
                                    :src="preview"
                                    alt="Logo"
                                    class="h-full w-full object-contain"
                                />
                                <span
                                    v-else
                                    class="text-lg font-bold text-white"
                                >
                                    {{ initials }}
                                </span>
                            </span>

                            <div class="space-y-2">
                                <label
                                    class="inline-block cursor-pointer rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                                >
                                    Choisir un fichier
                                    <input
                                        type="file"
                                        class="hidden"
                                        accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                        @change="pickLogo"
                                    />
                                </label>

                                <button
                                    v-if="preview"
                                    type="button"
                                    class="ml-2 text-sm text-rose-600 transition hover:text-rose-700"
                                    @click="removeLogo"
                                >
                                    Retirer
                                </button>

                                <p class="text-xs text-night-400">
                                    PNG, JPG, WebP ou SVG. 1 Mo maximum.
                                    Un carré sur fond transparent rend le mieux.
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="form.errors.logo"
                            class="mt-2 text-xs text-rose-600"
                        >
                            {{ form.errors.logo }}
                        </p>
                    </SurfaceCard>

                    <SurfaceCard title="Couleur">
                        <div class="flex items-center gap-4">
                            <input
                                v-model="form.brand_color"
                                type="color"
                                class="h-11 w-16 cursor-pointer rounded-lg border border-line bg-white p-1"
                            />

                            <input
                                v-model="form.brand_color"
                                type="text"
                                class="w-32 rounded-xl border-line font-mono text-sm uppercase focus:border-brand-400 focus:ring-brand-400"
                            />

                            <p class="text-xs text-night-400">
                                Appliquée à la bulle et à l'en-tête du widget.
                            </p>
                        </div>

                        <p
                            v-if="form.errors.brand_color"
                            class="mt-2 text-xs text-rose-600"
                        >
                            Format attendu : #RRGGBB.
                        </p>
                    </SurfaceCard>

                    <SurfaceCard title="Textes">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-night-500">
                                    Message d'accueil du widget
                                </label>
                                <input
                                    v-model="form.welcome_message"
                                    type="text"
                                    maxlength="300"
                                    placeholder="Bonjour, comment pouvons-nous vous aider ?"
                                    class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-night-500">
                                    Email de contact
                                </label>
                                <input
                                    v-model="form.support_email"
                                    type="email"
                                    placeholder="service.client@votre-entreprise.ci"
                                    class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                                />
                                <p class="mt-1 text-xs text-night-400">
                                    Apparaît au bas des emails envoyés à vos
                                    clients.
                                </p>
                            </div>
                        </div>
                    </SurfaceCard>

                    <div class="flex items-center justify-end gap-3">
                        <span
                            v-if="form.recentlySuccessful"
                            class="text-sm text-emerald-600"
                        >
                            Enregistré.
                        </span>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50"
                        >
                            Enregistrer
                        </button>
                    </div>
                </form>

                <!-- Aperçu -->

                <div class="space-y-4">
                    <SurfaceCard title="Aperçu">
                        <div
                            class="overflow-hidden rounded-2xl ring-1 ring-line"
                        >
                            <div
                                class="flex items-center gap-3 px-4 py-3"
                                :style="{ background: form.brand_color }"
                            >
                                <span
                                    class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-white/20"
                                >
                                    <img
                                        v-if="preview"
                                        :src="preview"
                                        alt=""
                                        class="h-full w-full object-contain"
                                    />
                                    <span
                                        v-else
                                        class="text-xs font-bold text-white"
                                    >
                                        {{ initials }}
                                    </span>
                                </span>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-white">
                                        {{ branding.name }}
                                    </p>
                                    <p class="text-[11px] text-white/80">
                                        En ligne
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2 bg-canvas-sunken p-4">
                                <p
                                    class="max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3 py-2 text-xs text-night-700 ring-1 ring-line"
                                >
                                    {{
                                        form.welcome_message ||
                                        "Bonjour, comment pouvons-nous vous aider ?"
                                    }}
                                </p>

                                <p
                                    class="ml-auto max-w-[85%] rounded-2xl rounded-br-md px-3 py-2 text-xs text-white"
                                    :style="{ background: form.brand_color }"
                                >
                                    Quels sont vos horaires ?
                                </p>
                            </div>
                        </div>
                    </SurfaceCard>

                    <SurfaceCard
                        v-if="demo_url"
                        title="Essayer le widget"
                        description="Sur un site fictif, avec vos réglages réels. Rien à installer."
                    >
                        <a
                            :href="demo_url"
                            target="_blank"
                            rel="noopener"
                            class="block w-full rounded-xl bg-brand-500 px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-brand-600"
                        >
                            Ouvrir la démonstration
                        </a>

                        <button
                            type="button"
                            class="mt-2 w-full rounded-xl border border-line px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                            @click="copyDemo"
                        >
                            {{ demoCopied ? "Lien copié" : "Copier le lien" }}
                        </button>

                        <p class="mt-2 text-xs leading-relaxed text-night-400">
                            À partager avec votre équipe pour tester. Chaque
                            message envoyé compte dans votre consommation
                            du mois.
                        </p>
                    </SurfaceCard>

                    <SurfaceCard title="Installer sur votre site">
                        <pre
                            class="overflow-x-auto rounded-xl bg-night-900 p-3 text-[11px] leading-relaxed text-night-100"
                        >{{ snippet }}</pre>

                        <button
                            type="button"
                            class="mt-3 w-full rounded-xl border border-line px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                            @click="copy"
                        >
                            {{ copied ? "Copié" : "Copier le code" }}
                        </button>

                        <p class="mt-2 text-xs text-night-400">
                            À coller avant la balise de fermeture du corps de
                            page, sur toutes les pages où le widget doit
                            apparaître.
                        </p>
                    </SurfaceCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
