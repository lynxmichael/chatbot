<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import StatTile from "@/Components/UI/StatTile.vue";
import StateBadge from "@/Components/UI/StateBadge.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";
import CountUp from "@/Components/UI/CountUp.vue";
import {
    formatDuration,
    formatNumber,
    formatRelative,
    initials,
} from "@/lib/format";

/*
|--------------------------------------------------------------------------
| Tableau de bord
|--------------------------------------------------------------------------
|
| Trois questions, dans cet ordre : qu'est-ce qui réclame une action tout
| de suite, où en est le volume, et que s'est-il passé ces dernières
| minutes. Le reste est secondaire.
|
*/

const page = usePage();

const stats = computed(() => page.props.statistics ?? {});
const series = computed(() => page.props.series ?? {});

const recentConversations = computed(
    () => page.props.recentConversations ?? []
);

const recentCalls = computed(
    () => page.props.recentCalls ?? []
);

const userName = computed(
    () => page.props.auth?.user?.name ?? "Utilisateur"
);

const num = (key) => Number(stats.value[key] ?? 0);

/*
|--------------------------------------------------------------------------
| Rafraîchissement
|--------------------------------------------------------------------------
|
| Rechargement partiel toutes les 20 secondes.
|
*/

const refreshing = ref(false);

let timer = null;

const refresh = () => {
    if (document.hidden || refreshing.value) {
        return;
    }

    refreshing.value = true;

    router.reload({
        only: [
            "statistics",
            "series",
            "recentConversations",
            "recentCalls",
        ],

        onFinish: () => {
            refreshing.value = false;
        },
    });
};

onMounted(() => {
    timer = setInterval(refresh, 20000);
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }
});

/*
|--------------------------------------------------------------------------
| Ce qui réclame une action
|--------------------------------------------------------------------------
*/

const alerts = computed(() =>
    [
        {
            key: "urgent",
            count: num("urgent_tickets"),
            label: "ticket urgent | tickets urgents",
            hint: "en attente de traitement",
            href: route("tickets.index"),
            tone: "rose",
        },

        {
            key: "overdue",
            count: num("overdue_tickets"),
            label: "SLA dépassé | SLA dépassés",
            hint: "échéance franchie",
            href: route("tickets.index"),
            tone: "amber",
        },

        {
            key: "missed",
            count: num("today_missed_calls"),
            label: "appel manqué | appels manqués",
            hint: "à rappeler aujourd'hui",
            href: route("calls.index"),
            tone: "amber",
        },

        {
            key: "pending",
            count: num("pending_conversations"),
            label: "conversation en attente | conversations en attente",
            hint: "sans réponse",
            href: route("conversations.index"),
            tone: "sky",
        },
    ].filter((alert) => alert.count > 0)
);

const plural = (count, label) => {
    const [one, many] = label.split(" | ");

    return count > 1 ? many : one;
};

const alertTones = {
    rose: "border-rose-200 bg-rose-50 text-rose-900 hover:border-rose-300",
    amber: "border-amber-200 bg-amber-50 text-amber-900 hover:border-amber-300",
    sky: "border-sky-200 bg-sky-50 text-sky-900 hover:border-sky-300",
};

/*
|--------------------------------------------------------------------------
| Flux d'activité
|--------------------------------------------------------------------------
|
| Les conversations et appels sont fusionnés dans une seule liste.
|
| IMPORTANT :
| `activityAt` contient le timestamp Unix envoyé par Laravel.
| Il sert uniquement au tri chronologique.
|
*/

const filter = ref("all");

const filters = [
    {
        value: "all",
        label: "Tout",
    },

    {
        value: "conversation",
        label: "Conversations",
    },

    {
        value: "call",
        label: "Appels",
    },
];

const activity = computed(() => {
    /*
    |--------------------------------------------------------------------------
    | Conversations
    |--------------------------------------------------------------------------
    */

    const conversations = recentConversations.value.map(
        (conversation) => ({
            kind: "conversation",

            id: `conv-${conversation.id}`,

            href: route(
                "conversations.show",
                conversation.id
            ),

            title:
                conversation.subject ||
                "Conversation sans objet",

            clientName:
                conversation.client?.name ??
                "Client inconnu",

            channel: conversation.channel,

            status: conversation.status,

            priority: conversation.priority,

            aiEnabled: Boolean(
                conversation.ai_enabled
            ),

            /*
            |--------------------------------------------------------------------------
            | Date affichée
            |--------------------------------------------------------------------------
            */

            at: conversation.last_message_at,

            /*
            |--------------------------------------------------------------------------
            | Timestamp réel utilisé pour le tri
            |--------------------------------------------------------------------------
            */

            activityAt: Number(
                conversation.activity_at ?? 0
            ),
        })
    );

    /*
    |--------------------------------------------------------------------------
    | Appels
    |--------------------------------------------------------------------------
    */

    const calls = recentCalls.value.map((call) => ({
        kind: "call",

        id: `call-${call.id}`,

        href: route(
            "calls.show",
            call.id
        ),

        title:
            call.reason ||
            (
                call.type === "incoming"
                    ? "Appel entrant"
                    : "Appel sortant"
            ),

        clientName:
            call.client?.name ??
            call.phone ??
            "Client inconnu",

        callType: call.type,

        status: call.status,

        duration: call.duration,

        /*
        |--------------------------------------------------------------------------
        | Date affichée
        |--------------------------------------------------------------------------
        */

        at: call.started_at,

        /*
        |--------------------------------------------------------------------------
        | Timestamp réel utilisé pour le tri
        |--------------------------------------------------------------------------
        */

        activityAt: Number(
            call.activity_at ?? 0
        ),
    }));

    /*
    |--------------------------------------------------------------------------
    | Fusion + tri chronologique
    |--------------------------------------------------------------------------
    |
    | Du plus récent au plus ancien.
    |
    */

    const merged = [
        ...conversations,
        ...calls,
    ].sort((a, b) => {
        return b.activityAt - a.activityAt;
    });

    /*
    |--------------------------------------------------------------------------
    | Filtrage
    |--------------------------------------------------------------------------
    */

    if (filter.value !== "all") {
        return merged.filter(
            (item) =>
                item.kind === filter.value
        );
    }

    return merged;
});

/*
|--------------------------------------------------------------------------
| Compteurs des filtres
|--------------------------------------------------------------------------
*/

const counts = computed(() => ({
    all:
        recentConversations.value.length +
        recentCalls.value.length,

    conversation:
        recentConversations.value.length,

    call:
        recentCalls.value.length,
}));

/*
|--------------------------------------------------------------------------
| Répartition des conversations
|--------------------------------------------------------------------------
*/

const breakdown = computed(() => {
    const rows = [
        {
            status: "open",
            value: num("open_conversations"),
        },

        {
            status: "pending",
            value: num("pending_conversations"),
        },

        {
            status: "resolved",
            value: num("resolved_conversations"),
        },

        {
            status: "closed",
            value: num("closed_conversations"),
        },
    ];

    const total =
        rows.reduce(
            (sum, row) =>
                sum + row.value,
            0
        ) || 1;

    return rows.map((row) => ({
        ...row,

        percent: Math.round(
            (row.value / total) * 100
        ),
    }));
});

const barColors = {
    open: "bg-sky-500",
    pending: "bg-amber-500",
    resolved: "bg-emerald-500",
    closed: "bg-night-300",
};

/*
|--------------------------------------------------------------------------
| Part traitée par l'IA
|--------------------------------------------------------------------------
*/

const aiShare = computed(() => {
    const conversations =
        num("conversations");

    if (conversations === 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round(
            (
                num(
                    "ai_enabled_conversations"
                ) /
                conversations
            ) * 100
        )
    );
});
</script>

<template>
    <Head title="Tableau de bord" />

    <AuthenticatedLayout>

        <!-- ==========================================================
             EN-TÊTE
        =========================================================== -->

        <template #header>
            <div
                class="flex flex-wrap items-baseline gap-x-3 gap-y-1"
            >
                <h1
                    class="text-lg font-semibold text-night-800"
                >
                    Bonjour {{ userName }}
                </h1>

                <p class="text-sm text-night-400">
                    Voici l'état du service client.
                </p>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">

            <!-- ======================================================
                 CE QUI RÉCLAME UNE ACTION
            ======================================================= -->

            <TransitionGroup
                v-if="alerts.length"
                tag="div"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                leave-active-class="transition duration-200 ease-in absolute"
                leave-to-class="opacity-0"
                move-class="transition duration-300"
            >
                <Link
                    v-for="alert in alerts"
                    :key="alert.key"
                    :href="alert.href"
                    class="flex items-center gap-3 rounded-xl border px-4 py-3 transition duration-200 hover:-translate-y-0.5"
                    :class="alertTones[alert.tone]"
                >
                    <span
                        class="font-mono text-2xl font-semibold leading-none"
                    >
                        <CountUp :value="alert.count" />
                    </span>

                    <span class="min-w-0">
                        <span
                            class="block text-sm font-medium leading-tight"
                        >
                            {{ plural(alert.count, alert.label) }}
                        </span>

                        <span
                            class="block text-xs opacity-70"
                        >
                            {{ alert.hint }}
                        </span>
                    </span>
                </Link>
            </TransitionGroup>

            <!-- ======================================================
                 VOLUMES
            ======================================================= -->

            <div
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <StatTile
                    label="Conversations"
                    :value="num('conversations')"
                    :today="num('today_conversations')"
                    :series="series.conversations ?? []"
                    tone="sky"
                    :href="route('conversations.index')"
                    :format="formatNumber"
                />

                <StatTile
                    label="Appels"
                    :value="num('calls')"
                    :today="num('today_calls')"
                    :series="series.calls ?? []"
                    tone="amber"
                    :href="route('calls.index')"
                    :format="formatNumber"
                />

                <StatTile
                    label="Tickets"
                    :value="num('tickets')"
                    :today="num('today_tickets')"
                    :series="series.tickets ?? []"
                    tone="brand"
                    :href="route('tickets.index')"
                    :format="formatNumber"
                />

                <StatTile
                    label="Réponses de l'IA"
                    :value="num('ai_responses')"
                    :today="num('today_ai_responses')"
                    :series="series.ai_responses ?? []"
                    tone="emerald"
                    :format="formatNumber"
                />
            </div>

            <!-- ======================================================
                 FLUX + SYNTHÈSE
            ======================================================= -->

            <div
                class="grid gap-6 lg:grid-cols-3"
            >

                <!-- ==================================================
                     FLUX D'ACTIVITÉ
                =================================================== -->

                <section
                    class="overflow-hidden rounded-2xl border border-line bg-canvas-raised lg:col-span-2"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4"
                    >
                        <h2
                            class="text-sm font-semibold text-night-800"
                        >
                            Activité récente
                        </h2>

                        <div
                            class="flex items-center gap-1 rounded-lg bg-canvas-sunken p-1"
                        >
                            <button
                                v-for="option in filters"
                                :key="option.value"
                                type="button"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition duration-150"
                                :class="
                                    filter === option.value
                                        ? 'bg-canvas-raised text-night-800 shadow-sm'
                                        : 'text-night-400 hover:text-night-600'
                                "
                                @click="
                                    filter =
                                        option.value
                                "
                            >
                                {{ option.label }}

                                <span
                                    class="ml-1 font-mono opacity-60"
                                >
                                    {{
                                        counts[
                                            option.value
                                        ]
                                    }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- ==================================================
                         LISTE CHRONOLOGIQUE
                    =================================================== -->

                    <TransitionGroup
                        tag="div"
                        class="divide-y divide-line"
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="-translate-x-2 opacity-0"
                        leave-active-class="transition duration-150 ease-in absolute"
                        leave-to-class="opacity-0"
                        move-class="transition duration-300"
                    >
                        <Link
                            v-for="item in activity"
                            :key="item.id"
                            :href="item.href"
                            class="flex items-start gap-3 px-5 py-3.5 transition hover:bg-canvas-sunken"
                        >

                            <!-- ==================================================
                                 AVATAR / PASTILLE
                            =================================================== -->

                            <span
                                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-semibold"
                                :class="
                                    item.kind === 'call'
                                        ? 'bg-amber-50 text-amber-700'
                                        : 'bg-brand-50 text-brand-700'
                                "
                            >
                                {{ initials(item.clientName) }}
                            </span>

                            <!-- ==================================================
                                 CONTENU
                            =================================================== -->

                            <span
                                class="min-w-0 flex-1"
                            >
                                <span
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="truncate text-sm font-medium text-night-800"
                                    >
                                        {{ item.title }}
                                    </span>

                                    <!-- Canal conversation -->

                                    <StateBadge
                                        v-if="
                                            item.kind ===
                                            'conversation'
                                        "
                                        kind="channel"
                                        :value="item.channel"
                                        dense
                                    />

                                    <!-- Type appel -->

                                    <StateBadge
                                        v-else
                                        kind="callType"
                                        :value="item.callType"
                                        dense
                                    />

                                    <!-- Statut -->

                                    <StateBadge
                                        kind="status"
                                        :value="item.status"
                                        dense
                                    />
                                </span>

                                <span
                                    class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-night-400"
                                >
                                    <span class="truncate">
                                        {{ item.clientName }}
                                    </span>

                                    <span
                                        aria-hidden="true"
                                    >
                                        ·
                                    </span>

                                    <span class="font-mono">
                                        {{
                                            formatRelative(
                                                item.at
                                            )
                                        }}
                                    </span>

                                    <!-- Durée appel -->

                                    <template
                                        v-if="
                                            item.kind ===
                                                'call' &&
                                            item.duration
                                        "
                                    >
                                        <span
                                            aria-hidden="true"
                                        >
                                            ·
                                        </span>

                                        <span
                                            class="font-mono"
                                        >
                                            {{
                                                formatDuration(
                                                    item.duration
                                                )
                                            }}
                                        </span>
                                    </template>

                                    <!-- IA active -->

                                    <template
                                        v-if="
                                            item.kind ===
                                                'conversation' &&
                                            item.aiEnabled
                                        "
                                    >
                                        <span
                                            aria-hidden="true"
                                        >
                                            ·
                                        </span>

                                        <span
                                            class="text-emerald-600"
                                        >
                                            IA active
                                        </span>
                                    </template>
                                </span>
                            </span>
                        </Link>
                    </TransitionGroup>

                    <!-- ==================================================
                         AUCUNE ACTIVITÉ
                    =================================================== -->

                    <EmptyState
                        v-if="!activity.length"
                        icon="○"
                        title="Aucune activité à afficher"
                        description="Les conversations et les appels apparaîtront ici dès qu'un client se manifestera."
                    />
                </section>

                <!-- ==================================================
                     SYNTHÈSE
                =================================================== -->

                <div class="space-y-6">

                    <!-- ==================================================
                         RÉPARTITION DES CONVERSATIONS
                    =================================================== -->

                    <section
                        class="rounded-2xl border border-line bg-canvas-raised p-5"
                    >
                        <h2
                            class="text-sm font-semibold text-night-800"
                        >
                            Répartition des conversations
                        </h2>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="row in breakdown"
                                :key="row.status"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <StateBadge
                                        kind="status"
                                        :value="row.status"
                                        dense
                                    />

                                    <span
                                        class="font-mono text-night-600"
                                    >
                                        <CountUp
                                            :value="row.value"
                                        />
                                    </span>
                                </div>

                                <div
                                    class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-canvas-sunken"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-700 ease-out"
                                        :class="
                                            barColors[
                                                row.status
                                            ]
                                        "
                                        :style="{
                                            width: `${row.percent}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ==================================================
                         APPELS
                    =================================================== -->

                    <section
                        class="rounded-2xl border border-line bg-canvas-raised p-5"
                    >
                        <h2
                            class="text-sm font-semibold text-night-800"
                        >
                            Appels
                        </h2>

                        <dl
                            class="mt-4 grid grid-cols-2 gap-4"
                        >
                            <div>
                                <dt
                                    class="text-xs text-night-400"
                                >
                                    Entrants
                                </dt>

                                <dd
                                    class="font-mono text-xl font-semibold text-night-800"
                                >
                                    <CountUp
                                        :value="
                                            num(
                                                'incoming_calls'
                                            )
                                        "
                                    />
                                </dd>
                            </div>

                            <div>
                                <dt
                                    class="text-xs text-night-400"
                                >
                                    Sortants
                                </dt>

                                <dd
                                    class="font-mono text-xl font-semibold text-night-800"
                                >
                                    <CountUp
                                        :value="
                                            num(
                                                'outgoing_calls'
                                            )
                                        "
                                    />
                                </dd>
                            </div>

                            <div>
                                <dt
                                    class="text-xs text-night-400"
                                >
                                    Manqués
                                </dt>

                                <dd
                                    class="font-mono text-xl font-semibold text-rose-600"
                                >
                                    <CountUp
                                        :value="
                                            num(
                                                'missed_calls'
                                            )
                                        "
                                    />
                                </dd>
                            </div>

                            <div>
                                <dt
                                    class="text-xs text-night-400"
                                >
                                    Temps en ligne
                                </dt>

                                <dd
                                    class="font-mono text-xl font-semibold text-night-800"
                                >
                                    {{
                                        formatDuration(
                                            num(
                                                "total_call_duration"
                                            )
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <!-- ==================================================
                         IA ET ÉQUIPE
                    =================================================== -->

                    <section
                        class="rounded-2xl border border-line bg-canvas-raised p-5"
                    >
                        <div
                            class="flex items-baseline justify-between"
                        >
                            <h2
                                class="text-sm font-semibold text-night-800"
                            >
                                Prise en charge par l'IA
                            </h2>

                            <span
                                class="font-mono text-sm font-semibold text-emerald-600"
                            >
                                <CountUp
                                    :value="aiShare"
                                />%
                            </span>
                        </div>

                        <div
                            class="mt-3 h-2 overflow-hidden rounded-full bg-canvas-sunken"
                        >
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all duration-700 ease-out"
                                :style="{
                                    width: `${aiShare}%`,
                                }"
                            ></div>
                        </div>

                        <p
                            class="mt-3 text-xs leading-5 text-night-400"
                        >
                            {{
                                formatNumber(
                                    num(
                                        "ai_enabled_conversations"
                                    )
                                )
                            }}
                            conversations sur
                            {{
                                formatNumber(
                                    num(
                                        "conversations"
                                    )
                                )
                            }}
                            sont encore gérées par l'IA.

                    <p class="mt-3 text-xs leading-5 text-night-400">
    {{ formatNumber(num("ai_enabled_conversations")) }}
    conversations sur
    {{ formatNumber(num("conversations")) }}
    sont encore gérées par l'IA.
    {{ formatNumber(num("assigned_conversations")) }}
    ont été reprises par un agent.
</p>

                            ont été reprises par un agent.
                        </p>

                        <div
                            class="mt-4 flex items-center justify-between border-t border-line pt-4"
                        >
                            <span
                                class="text-xs text-night-400"
                            >
                                Agents actifs
                            </span>

                            <Link
                                :href="
                                    route(
                                        'agents.index'
                                    )
                                "
                                class="font-mono text-sm font-semibold text-night-800 transition hover:text-brand-600"
                            >
                                <CountUp
                                    :value="
                                        num('agents')
                                    "
                                />
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
