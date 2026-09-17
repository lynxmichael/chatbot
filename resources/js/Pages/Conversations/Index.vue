<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";
import StateBadge from "@/Components/UI/StateBadge.vue";

const props = defineProps({
    conversations: { type: Object, required: true },
    agents: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

/*
|--------------------------------------------------------------------------
| Filtres
|--------------------------------------------------------------------------
*/

const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");
const priority = ref(props.filters.priority ?? "");
const aiEnabled = ref(props.filters.ai_enabled ?? "");
const assignedTo = ref(props.filters.assigned_to ?? "");

const applyFilters = () => {
    router.get(
        route("conversations.index"),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            priority: priority.value || undefined,
            ai_enabled: aiEnabled.value !== "" ? aiEnabled.value : undefined,
            assigned_to: assignedTo.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let searchTimer = null;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

watch([status, priority, aiEnabled, assignedTo], applyFilters);

const resetFilters = () => {
    search.value = "";
    status.value = "";
    priority.value = "";
    aiEnabled.value = "";
    assignedTo.value = "";
};

const activeFilters = computed(
    () =>
        [
            search.value,
            status.value,
            priority.value,
            aiEnabled.value,
            assignedTo.value,
        ].filter((value) => value !== "" && value !== null && value !== undefined)
            .length,
);

/*
|--------------------------------------------------------------------------
| Actualisation
|--------------------------------------------------------------------------
|
| Suspendue quand l'onglet est en arrière-plan.
|
*/

const refreshing = ref(false);

let refreshInterval = null;

const refresh = () => {
    if (refreshing.value || document.hidden) {
        return;
    }

    refreshing.value = true;

    router.reload({
        only: ["conversations"],
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            refreshing.value = false;
        },
    });
};

onMounted(() => {
    refreshInterval = setInterval(refresh, 10000);
});

onBeforeUnmount(() => {
    clearInterval(refreshInterval);
    clearTimeout(searchTimer);
});

/*
|--------------------------------------------------------------------------
| Présentation
|--------------------------------------------------------------------------
*/

const clientName = (conversation) => {
    const client = conversation.client;

    if (!client) {
        return "Client inconnu";
    }

    const name = `${client.first_name ?? ""} ${client.last_name ?? ""}`.trim();

    return name || client.email || client.phone || "Client inconnu";
};

const initials = (name) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join("")
        .toUpperCase() || "?";

const agentName = (conversation) =>
    conversation.assigned_agent?.name ??
    conversation.assignedAgent?.name ??
    null;

/*
 * Temps écoulé depuis le dernier message, en clair.
 * C'est l'information qui dit si un client attend.
 */
const sinceLastMessage = (conversation) => {
    const value = conversation.last_message_at;

    if (!value) {
        return "—";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "—";
    }

    const minutes = Math.floor((Date.now() - date.getTime()) / 60000);

    if (minutes < 1) {
        return "à l'instant";
    }

    if (minutes < 60) {
        return `il y a ${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `il y a ${hours} h`;
    }

    return date.toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
    });
};

/*
 * Une conversation ouverte, sans IA et sans agent, est en souffrance.
 */
const isStranded = (conversation) =>
    conversation.status === "open" &&
    !conversation.ai_enabled &&
    !agentName(conversation);

const hasConversations = computed(() => props.conversations.data.length > 0);
</script>

<template>
    <Head title="Conversations" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Conversations"
                description="Les échanges en cours, tous canaux confondus."
            >
                <template #actions>
                    <span
                        v-if="refreshing"
                        class="self-center text-xs text-night-400"
                    >
                        actualisation…
                    </span>
                </template>
            </PageHeader>

            <FlashMessages />

            <!-- Filtres -->

            <SurfaceCard>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <label class="block text-xs font-medium text-night-500">
                            Rechercher
                        </label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Sujet, client, email…"
                            class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Statut
                        </label>
                        <select
                            v-model="status"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Tous</option>
                            <option value="open">Ouverte</option>
                            <option value="pending">En attente</option>
                            <option value="resolved">Résolue</option>
                            <option value="closed">Fermée</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Priorité
                        </label>
                        <select
                            v-model="priority"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Toutes</option>
                            <option value="urgent">Urgente</option>
                            <option value="high">Élevée</option>
                            <option value="normal">Normale</option>
                            <option value="low">Faible</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Traitement
                        </label>
                        <select
                            v-model="aiEnabled"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Tous</option>
                            <option value="1">Assistant IA</option>
                            <option value="0">Agent humain</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-night-500">
                            Agent
                        </label>
                        <select
                            v-model="assignedTo"
                            class="mt-1 rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">Tous</option>
                            <option
                                v-for="agent in agents"
                                :key="agent.id"
                                :value="agent.id"
                            >
                                {{ agent.name }}
                            </option>
                        </select>
                    </div>

                    <button
                        v-if="activeFilters"
                        type="button"
                        class="rounded-xl border border-line px-4 py-2 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken"
                        @click="resetFilters"
                    >
                        Effacer ({{ activeFilters }})
                    </button>
                </div>
            </SurfaceCard>

            <!-- Liste -->

            <SurfaceCard flush>
                <div v-if="hasConversations" class="divide-y divide-line">
                    <Link
                        v-for="conversation in conversations.data"
                        :key="conversation.id"
                        :href="route('conversations.show', conversation.id)"
                        class="flex items-start gap-4 px-6 py-4 transition hover:bg-canvas-sunken"
                    >
                        <!-- Client -->

                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-canvas-sunken text-xs font-bold text-night-600 ring-1 ring-line"
                        >
                            {{ initials(clientName(conversation)) }}
                        </span>

                        <!-- Contenu -->

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-medium text-night-900">
                                    {{ clientName(conversation) }}
                                </p>

                                <StateBadge
                                    kind="channel"
                                    :value="conversation.channel"
                                    dense
                                />

                                <span
                                    v-if="conversation.ai_enabled"
                                    class="rounded-md bg-brand-50 px-1.5 py-0.5 text-[11px] font-semibold text-brand-700 ring-1 ring-brand-200"
                                >
                                    IA
                                </span>

                                <span
                                    v-if="isStranded(conversation)"
                                    class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200"
                                >
                                    sans agent
                                </span>
                            </div>

                            <p class="mt-0.5 truncate text-sm text-night-500">
                                {{ conversation.subject || "Sans sujet" }}
                            </p>

                            <p
                                v-if="agentName(conversation)"
                                class="mt-1 text-xs text-night-400"
                            >
                                {{ agentName(conversation) }}
                            </p>
                        </div>

                        <!-- Méta -->

                        <div class="flex shrink-0 flex-col items-end gap-1.5">
                            <StateBadge
                                kind="status"
                                :value="conversation.status"
                                dense
                            />

                            <StateBadge
                                v-if="
                                    conversation.priority === 'urgent' ||
                                    conversation.priority === 'high'
                                "
                                kind="priority"
                                :value="conversation.priority"
                                dense
                            />

                            <span class="text-xs text-night-400">
                                {{ sinceLastMessage(conversation) }}
                            </span>
                        </div>
                    </Link>
                </div>

                <EmptyState
                    v-else
                    icon="💬"
                    title="Aucune conversation"
                    :description="
                        activeFilters
                            ? 'Aucune conversation ne correspond à ces filtres.'
                            : 'Les échanges avec vos clients apparaîtront ici.'
                    "
                />
            </SurfaceCard>

            <!-- Pagination -->

            <div
                v-if="conversations.links && conversations.links.length > 3"
                class="flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in conversations.links"
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
