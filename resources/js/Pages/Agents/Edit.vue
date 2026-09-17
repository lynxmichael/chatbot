<script setup>
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";

const props = defineProps({
    agent: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.agent.name,
    email: props.agent.email,
    password: "",
    password_confirmation: "",
    is_available: props.agent.is_available,
    max_open_tickets: props.agent.max_open_tickets,
    skills: [...props.agent.skills],
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        max_open_tickets: Number(data.max_open_tickets),
        is_available: Boolean(data.is_available),
    })).put(route("agents.update", props.agent.id));
};

const toggleSkill = (category) => {
    const index = form.skills.indexOf(category);

    if (index === -1) {
        form.skills.push(category);
    } else {
        form.skills.splice(index, 1);
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

const label = (category) => categoryLabels[category] ?? category;

/*
 * Avertissement affiché en clair plutôt qu'en petit : un agent sans
 * compétence ne sera jamais choisi en priorité par le routage, et c'est
 * le genre de réglage qu'on oublie.
 */
const routingNotice = computed(() => {
    if (!form.is_available) {
        return "Cet agent ne recevra ni appel ni nouveau ticket tant qu'il est marqué indisponible.";
    }

    if (!form.skills.length) {
        return "Sans compétence déclarée, cet agent ne sera choisi que faute de mieux, quand tous les autres sont chargés.";
    }

    return null;
});

const overCapacity = computed(
    () => props.agent.open_tickets > Number(form.max_open_tickets),
);
</script>

<template>
    <Head :title="`Régler ${agent.name}`" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                :title="agent.name"
                description="Compte, compétences et capacité de traitement."
            >
                <template #actions>
                    <Link
                        :href="route('agents.index')"
                        class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Retour
                    </Link>
                </template>
            </PageHeader>

            <FlashMessages />

            <form class="space-y-6" @submit.prevent="submit">
                <!-- Compte -->

                <SurfaceCard title="Compte">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Nom
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Email
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p
                                v-if="form.errors.email"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Nouveau mot de passe
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Laisser vide pour ne pas changer"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p
                                v-if="form.errors.password"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Confirmation
                            </label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                        </div>
                    </div>
                </SurfaceCard>

                <!-- Routage -->

                <SurfaceCard
                    title="Répartition automatique"
                    description="Ces réglages décident des appels et des tickets que reçoit cet agent."
                >
                    <!-- Disponibilité -->

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-line px-4 py-3 transition hover:bg-canvas-sunken"
                    >
                        <input
                            v-model="form.is_available"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-line-strong text-brand-500 focus:ring-brand-400"
                        />
                        <span>
                            <span class="block text-sm font-medium text-night-800">
                                Disponible
                            </span>
                            <span class="mt-0.5 block text-xs text-night-400">
                                Son poste sonne et il reçoit de nouveaux tickets.
                            </span>
                        </span>
                    </label>

                    <!-- Compétences -->

                    <div class="mt-6">
                        <p class="text-xs font-medium text-night-500">
                            Compétences
                        </p>
                        <p class="mt-0.5 text-xs text-night-400">
                            Une demande de cette catégorie lui sera confiée en
                            priorité.
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="category in categories"
                                :key="category"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                :class="
                                    form.skills.includes(category)
                                        ? 'bg-brand-500 text-white'
                                        : 'bg-white text-night-600 ring-1 ring-line hover:bg-canvas-sunken'
                                "
                                @click="toggleSkill(category)"
                            >
                                {{ label(category) }}
                            </button>
                        </div>
                    </div>

                    <!-- Capacité -->

                    <div class="mt-6 max-w-xs">
                        <label class="block text-xs font-medium text-night-500">
                            Capacité maximale
                        </label>

                        <input
                            v-model="form.max_open_tickets"
                            type="number"
                            min="1"
                            max="200"
                            class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        />

                        <p class="mt-1 text-xs text-night-400">
                            Tickets ouverts simultanément. Actuellement :
                            {{ agent.open_tickets }}.
                        </p>

                        <p
                            v-if="overCapacity"
                            class="mt-1 text-xs text-amber-700"
                        >
                            Sa charge actuelle dépasse déjà cette limite. Il
                            restera en bas de la liste tant qu'elle ne sera pas
                            redescendue.
                        </p>

                        <p
                            v-if="form.errors.max_open_tickets"
                            class="mt-1 text-xs text-rose-600"
                        >
                            {{ form.errors.max_open_tickets }}
                        </p>
                    </div>

                    <p
                        v-if="routingNotice"
                        class="mt-6 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200"
                    >
                        {{ routingNotice }}
                    </p>
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
        </div>
    </AuthenticatedLayout>
</template>
