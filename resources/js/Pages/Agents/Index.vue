<script setup>
import { computed } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";

const props = defineProps({
    agents: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

/*
|--------------------------------------------------------------------------
| Charge de travail
|--------------------------------------------------------------------------
|
| Le ratio affiché est celui qu'utilise réellement le routage automatique.
| Un agent au-delà de sa capacité est fortement pénalisé dans le choix,
| d'où la barre qui vire au rose.
|
*/

const loadRatio = (agent) =>
    Math.min(1.4, agent.open_tickets / Math.max(1, agent.max_open_tickets));

const loadTone = (agent) => {
    const ratio = loadRatio(agent);

    if (ratio >= 1) {
        return "bg-rose-500";
    }

    if (ratio >= 0.75) {
        return "bg-amber-500";
    }

    return "bg-emerald-500";
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

const toggle = (agent) => {
    router.patch(
        route("agents.toggle", agent.id),
        {},
        { preserveScroll: true },
    );
};

/*
 * Un agent indisponible ou sans compétence déclarée ne reçoit rien
 * du routage automatique : autant le signaler explicitement.
 */
const routingWarning = (agent) => {
    if (!agent.is_active) {
        return "Compte désactivé";
    }

    if (!agent.is_available) {
        return "Indisponible : aucun nouvel appel ni ticket";
    }

    if (!agent.skills.length) {
        return "Aucune compétence déclarée";
    }

    return null;
};

const hasAgents = computed(() => props.agents.data.length > 0);
</script>

<template>
    <Head title="Agents" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Agents"
                description="Compétences, disponibilité et charge : ce qui décide de la répartition automatique."
            >
                <template #actions>
                    <Link
                        :href="route('agents.create')"
                        class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"
                    >
                        Nouvel agent
                    </Link>
                </template>
            </PageHeader>

            <FlashMessages />

            <SurfaceCard flush>
                <div v-if="hasAgents" class="divide-y divide-line">
                    <article
                        v-for="agent in agents.data"
                        :key="agent.id"
                        class="flex flex-wrap items-start gap-4 px-6 py-5 transition hover:bg-canvas-sunken"
                    >
                        <!-- Identité -->

                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-sm font-bold text-brand-700"
                            >
                                {{ agent.name.slice(0, 2).toUpperCase() }}
                            </span>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p
                                        class="truncate font-semibold text-night-900"
                                    >
                                        {{ agent.name }}
                                    </p>

                                    <span
                                        v-if="agent.role === 'owner'"
                                        class="rounded-md bg-night-100 px-1.5 py-0.5 text-[11px] font-semibold text-night-600"
                                    >
                                        responsable
                                    </span>

                                    <span
                                        v-if="agent.is_active && agent.is_available"
                                        class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                        ></span>
                                        disponible
                                    </span>
                                </div>

                                <p class="truncate text-sm text-night-400">
                                    {{ agent.email }}
                                </p>

                                <!-- Compétences -->

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="skill in agent.skills"
                                        :key="skill"
                                        class="rounded-md bg-canvas-sunken px-2 py-0.5 text-[11px] font-medium text-night-600 ring-1 ring-line"
                                    >
                                        {{ label(skill) }}
                                    </span>

                                    <span
                                        v-if="routingWarning(agent)"
                                        class="rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-amber-200"
                                    >
                                        {{ routingWarning(agent) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Charge -->

                        <div class="w-44 shrink-0">
                            <div
                                class="flex items-baseline justify-between text-xs"
                            >
                                <span class="text-night-400">Charge</span>
                                <span class="font-semibold text-night-700">
                                    {{ agent.open_tickets }} /
                                    {{ agent.max_open_tickets }}
                                </span>
                            </div>

                            <div
                                class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-canvas-sunken"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="loadTone(agent)"
                                    :style="{
                                        width:
                                            Math.min(100, loadRatio(agent) * 100) +
                                            '%',
                                    }"
                                ></div>
                            </div>

                            <p class="mt-1.5 text-[11px] text-night-400">
                                {{ agent.conversations_count }} conversation(s)
                            </p>
                        </div>

                        <!-- Actions -->

                        <div class="flex shrink-0 gap-2">
                            <Link
                                :href="route('agents.edit', agent.id)"
                                class="rounded-lg border border-line px-3.5 py-2 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                            >
                                Régler
                            </Link>

                            <button
                                v-if="agent.role !== 'owner'"
                                type="button"
                                class="rounded-lg px-3.5 py-2 text-sm font-medium transition"
                                :class="
                                    agent.is_active
                                        ? 'border border-line text-night-500 hover:bg-canvas-sunken'
                                        : 'bg-emerald-500 text-white hover:bg-emerald-600'
                                "
                                @click="toggle(agent)"
                            >
                                {{ agent.is_active ? "Désactiver" : "Activer" }}
                            </button>
                        </div>
                    </article>
                </div>

                <EmptyState
                    v-else
                    icon="🧑‍💼"
                    title="Aucun agent pour l'instant"
                    description="Ajoutez un agent pour que les appels et les tickets puissent lui être attribués."
                />
            </SurfaceCard>

            <!-- Pagination -->

            <div
                v-if="agents.links && agents.links.length > 3"
                class="flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in agents.links"
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
