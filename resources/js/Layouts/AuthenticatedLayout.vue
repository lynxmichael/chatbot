<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import CallDesk from "@/Components/CallDesk.vue";

import LivePulse from "@/Components/UI/LivePulse.vue";
import ToastHost from "@/Components/UI/ToastHost.vue";
import { formatRelative, initials } from "@/lib/format";

/*
|--------------------------------------------------------------------------
| Console
|--------------------------------------------------------------------------
|
| Rail de navigation permanent à gauche, barre de contexte en haut, contenu
| au centre. C'est la disposition d'un outil qu'on garde ouvert toute la
| journée : la navigation ne bouge jamais, seule la zone de travail change.
|
*/

const page = usePage();

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const baseNavigation = [
    {
        name: "Tableau de bord",
        route: "dashboard",
        pattern: "dashboard",
        icon: "grid",
    },
    {
        name: "Conversations",
        route: "conversations.index",
        pattern: "conversations.*",
        icon: "chat",
    },
    {
        name: "Appels",
        route: "calls.index",
        pattern: "calls.*",
        icon: "phone",
    },
    {
        name: "Tickets",
        route: "tickets.index",
        pattern: "tickets.*",
        icon: "ticket",
    },
    {
        name: "Clients",
        route: "clients.index",
        pattern: "clients.*",
        icon: "users",
    },
    {
        name: "Agents",
        route: "agents.index",
        pattern: "agents.*",
        icon: "shield",
    },
    {
        name: "Autopilot",
        route: "autopilot.index",
        pattern: "autopilot.*",
        icon: "spark",
        ownerOnly: true,
    },
];

/*
 * Les réglages de l'IA et la validation de ses actions
 * ne concernent que le propriétaire de l'organisation.
 */
const navigation = computed(() =>
    baseNavigation.filter(
        (item) => !item.ownerOnly || page.props.auth?.user?.role === "owner",
    ),
);

const isCurrent = (pattern) => route().current(pattern);

/*
|--------------------------------------------------------------------------
| État de l'interface
|--------------------------------------------------------------------------
*/

const mobileNavOpen = ref(false);
const showingNotifications = ref(false);
const showingAccount = ref(false);

const user = computed(() => page.props.auth?.user ?? null);

const organizationName = computed(
    () => page.props.auth?.user?.organization?.name ?? "Service client"
);

/*
 * Initiales affichées à la place d'un logo.
 *
 * Tant qu'aucun logo n'est téléversé par l'organisation, deux lettres
 * sur une pastille tiennent mieux la mise en page qu'une icône
 * générique — et surtout, elles appartiennent au client.
 */
const organizationInitials = computed(() => {
    const words = organizationName.value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

    const initials = words.map((word) => word[0]).join("").toUpperCase();

    return initials || "SC";
});

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

const notifications = computed(() => page.props.notifications ?? []);

const unreadCount = computed(
    () => page.props.unread_notifications_count ?? 0
);

const notificationTitle = (notification) => {
    const data = notification.data ?? {};

    return data.title ?? data.message ?? data.subject ?? "Notification";
};

const notificationHref = (notification) => {
    const data = notification.data ?? {};

    if (data.conversation_id) {
        return route("conversations.show", data.conversation_id);
    }

    if (data.call_id) {
        return route("calls.show", data.call_id);
    }

    if (data.ticket_id) {
        return route("tickets.show", data.ticket_id);
    }

    return null;
};

const markAsRead = (notification) => {
    if (notification.read_at) {
        return;
    }

    router.post(
        route("notifications.read", notification.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ["notifications", "unread_notifications_count"],
        }
    );
};

const markAllAsRead = () => {
    router.post(
        route("notifications.read-all"),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ["notifications", "unread_notifications_count"],
        }
    );
};

/*
|--------------------------------------------------------------------------
| Rafraîchissement des notifications
|--------------------------------------------------------------------------
|
| Rechargement partiel toutes les 30 secondes : seules les notifications
| repassent par le serveur, la page ouverte n'est pas remontée. La boucle
| s'arrête quand l'onglet passe en arrière-plan.
|
*/

const live = ref(true);

let poller = null;

const syncNotifications = () => {
    if (document.hidden) {
        return;
    }

    router.reload({
        only: ["notifications", "unread_notifications_count"],
    });
};

const stopPolling = () => {
    if (poller) {
        clearInterval(poller);
        poller = null;
    }
};

const startPolling = () => {
    stopPolling();
    poller = setInterval(syncNotifications, 30000);
    live.value = true;
};

const handleVisibility = () => {
    if (document.hidden) {
        stopPolling();
        live.value = false;
    } else {
        syncNotifications();
        startPolling();
    }
};

onMounted(() => {
    startPolling();
    document.addEventListener("visibilitychange", handleVisibility);
});

onBeforeUnmount(() => {
    stopPolling();
    document.removeEventListener("visibilitychange", handleVisibility);
});

/*
|--------------------------------------------------------------------------
| Fermeture des panneaux à la navigation
|--------------------------------------------------------------------------
*/

watch(
    () => page.url,
    () => {
        mobileNavOpen.value = false;
        showingNotifications.value = false;
        showingAccount.value = false;
    }
);

/*
|--------------------------------------------------------------------------
| Icônes
|--------------------------------------------------------------------------
*/

const icons = {
    grid: "M3 3h7v7H3V3Zm11 0h7v4h-7V3ZM3 14h7v7H3v-7Zm11-3h7v10h-7V11Z",
    chat: "M4 4h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V5a1 1 0 0 1 1-1Z",
    phone: "M6.6 3h3l1.5 4-2 1.4a12 12 0 0 0 5.5 5.5l1.4-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4.6 5.2 2 2 0 0 1 6.6 3Z",
    ticket: "M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4a2 2 0 0 0 0-4V6Z",
    users: "M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20a7 7 0 0 1 14 0H2Zm15 0a8.9 8.9 0 0 0-1.7-5A5.5 5.5 0 0 1 22 20h-5Z",
    shield: "M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Z",
    spark: "M12 2l2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2L12 2Zm6.5 11 1.1 2.9L22.5 17l-2.9 1.1L18.5 21l-1.1-2.9L14.5 17l2.9-1.1L18.5 13Z",
};
</script>

<template>
    <div class="min-h-screen bg-canvas">

        <!-- Poste téléphonique : joignable depuis toutes les pages -->

        <CallDesk />

        <!-- ==========================================================
             VOILE MOBILE
        =========================================================== -->

        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileNavOpen"
                class="fixed inset-0 z-30 bg-night-900/50 lg:hidden"
                @click="mobileNavOpen = false"
            ></div>
        </Transition>

        <!-- ==========================================================
             RAIL DE NAVIGATION
        =========================================================== -->

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-night-800 transition-transform duration-300 ease-out lg:translate-x-0"
            :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Organisation -->

            <div class="flex h-16 items-center gap-3 px-5">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-xs font-bold tracking-tight text-white"
                    aria-hidden="true"
                >
                    {{ organizationInitials }}
                </span>

                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white">
                        {{ organizationName }}
                    </p>

                    <p class="text-[11px] text-night-300">
                        Console service client
                    </p>
                </div>

                <button
                    type="button"
                    class="ml-auto rounded-lg p-1.5 text-night-300 transition hover:bg-night-700 hover:text-white lg:hidden"
                    aria-label="Fermer la navigation"
                    @click="mobileNavOpen = false"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path
                            d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                        />
                    </svg>
                </button>
            </div>

            <!-- Liens -->

            <nav class="scroll-slim flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <Link
                    v-for="item in navigation"
                    :key="item.route"
                    :href="route(item.route)"
                    class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition duration-150"
                    :class="
                        isCurrent(item.pattern)
                            ? 'bg-night-700 text-white'
                            : 'text-night-200 hover:bg-night-700/60 hover:text-white'
                    "
                >
                    <!-- Marqueur de position -->
                    <span
                        class="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-brand-400 transition-opacity duration-200"
                        :class="
                            isCurrent(item.pattern) ? 'opacity-100' : 'opacity-0'
                        "
                    ></span>

                    <svg
                        class="h-5 w-5 shrink-0 transition-transform duration-150 group-hover:scale-110"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path :d="icons[item.icon]" />
                    </svg>

                    {{ item.name }}
                </Link>
            </nav>

            <!-- Compte -->

            <div class="border-t border-night-700 p-3">
                <div class="relative">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-night-700"
                        @click="showingAccount = !showingAccount"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-500 font-mono text-xs font-semibold text-white"
                        >
                            {{ initials(user?.name) }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate text-sm font-medium text-white"
                            >
                                {{ user?.name ?? "Utilisateur" }}
                            </span>

                            <span
                                class="block truncate text-[11px] text-night-300"
                            >
                                {{ user?.email ?? "" }}
                            </span>
                        </span>

                        <svg
                            class="h-4 w-4 shrink-0 text-night-300 transition-transform duration-200"
                            :class="showingAccount ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-2 opacity-0"
                        leave-active-class="transition duration-150 ease-in"
                        leave-to-class="translate-y-2 opacity-0"
                    >
                        <div
                            v-if="showingAccount"
                            class="absolute bottom-full left-0 right-0 mb-2 overflow-hidden rounded-xl bg-canvas-raised shadow-pop ring-1 ring-line"
                        >
                            <Link
                                :href="route('profile.edit')"
                                class="block px-4 py-2.5 text-sm text-night-700 transition hover:bg-canvas-sunken"
                            >
                                Profil
                            </Link>

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="block w-full px-4 py-2.5 text-left text-sm text-rose-600 transition hover:bg-rose-50"
                            >
                                Déconnexion
                            </Link>
                        </div>
                    </Transition>
                </div>
            </div>
        </aside>

        <!-- ==========================================================
             ZONE DE TRAVAIL
        =========================================================== -->

        <div class="lg:pl-64">

            <!-- BARRE DE CONTEXTE -->

            <header
                class="sticky top-0 z-20 border-b border-line bg-canvas/85 backdrop-blur"
            >
                <div
                    class="flex min-h-16 flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-6 lg:px-8"
                >
                    <button
                        type="button"
                        class="rounded-lg p-2 text-night-500 transition hover:bg-canvas-sunken lg:hidden"
                        aria-label="Ouvrir la navigation"
                        @click="mobileNavOpen = true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 6h18v2H3V6Zm0 5h18v2H3v-2Zm0 5h18v2H3v-2Z" />
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <slot name="header" />
                    </div>

                    <LivePulse
                        :active="live"
                        :label="live ? 'En direct' : 'En pause'"
                        class="hidden sm:inline-flex"
                    />

                    <!-- Notifications -->

                    <div class="relative">
                        <button
                            type="button"
                            class="relative rounded-lg p-2 text-night-500 transition hover:bg-canvas-sunken"
                            aria-label="Notifications"
                            @click="
                                showingNotifications = !showingNotifications;
                                showingAccount = false;
                            "
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path
                                    d="M12 2a6 6 0 0 0-6 6v3.6l-1.7 3.1A1 1 0 0 0 5.2 16h13.6a1 1 0 0 0 .9-1.3L18 11.6V8a6 6 0 0 0-6-6Zm0 20a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3Z"
                                />
                            </svg>

                            <span
                                v-if="unreadCount > 0"
                                class="absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 font-mono text-[10px] font-bold text-white"
                            >
                                {{ unreadCount > 9 ? "9+" : unreadCount }}
                            </span>
                        </button>

                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="-translate-y-2 opacity-0"
                            leave-active-class="transition duration-150 ease-in"
                            leave-to-class="-translate-y-2 opacity-0"
                        >
                            <div
                                v-if="showingNotifications"
                                class="absolute right-0 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl bg-canvas-raised shadow-pop ring-1 ring-line"
                            >
                                <div
                                    class="flex items-center justify-between border-b border-line px-4 py-3"
                                >
                                    <p class="text-sm font-semibold text-night-800">
                                        Notifications
                                    </p>

                                    <button
                                        v-if="unreadCount > 0"
                                        type="button"
                                        class="text-xs font-medium text-brand-600 transition hover:text-brand-700"
                                        @click="markAllAsRead"
                                    >
                                        Tout marquer comme lu
                                    </button>
                                </div>

                                <div class="scroll-slim max-h-80 overflow-y-auto">
                                    <component
                                        :is="
                                            notificationHref(notification)
                                                ? Link
                                                : 'div'
                                        "
                                        v-for="notification in notifications"
                                        :key="notification.id"
                                        :href="
                                            notificationHref(notification) ||
                                            undefined
                                        "
                                        class="flex gap-3 border-b border-line px-4 py-3 transition last:border-0 hover:bg-canvas-sunken"
                                        @click="markAsRead(notification)"
                                    >
                                        <span
                                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                                            :class="
                                                notification.read_at
                                                    ? 'bg-line-strong'
                                                    : 'bg-brand-500'
                                            "
                                        ></span>

                                        <span class="min-w-0 flex-1">
                                            <span
                                                class="block text-sm leading-5 text-night-700"
                                            >
                                                {{ notificationTitle(notification) }}
                                            </span>

                                            <span
                                                class="mt-0.5 block font-mono text-[11px] text-night-400"
                                            >
                                                {{
                                                    formatRelative(
                                                        notification.created_at,
                                                    )
                                                }}
                                            </span>
                                        </span>
                                    </component>

                                    <p
                                        v-if="!notifications.length"
                                        class="px-4 py-8 text-center text-sm text-night-400"
                                    >
                                        Rien de neuf pour le moment.
                                    </p>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <!-- CONTENU -->

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <Transition
                    mode="out-in"
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-1 opacity-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-to-class="opacity-0"
                >
                    <div :key="page.url">
                        <slot />
                    </div>
                </Transition>
            </main>
        </div>

        <ToastHost />
    </div>
</template>
