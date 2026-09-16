<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Link, router } from "@inertiajs/vue3";

/*
|--------------------------------------------------------------------------
| Poste téléphonique
|--------------------------------------------------------------------------
|
| Monté une seule fois dans le layout : l'agent est donc joignable depuis
| n'importe quelle page de la console, pas seulement depuis la liste des
| appels.
|
| Le serveur est interrogé toutes les 3 secondes. Dès qu'un appel sonne
| pour cet agent, la fiche s'affiche et la sonnerie démarre.
|
*/

const POLL_INTERVAL = 3000;

const ringing = ref(null);
const active = ref(null);
const busy = ref(false);
const soundBlocked = ref(false);

let pollTimer = null;
let tickTimer = null;

/*
|--------------------------------------------------------------------------
| Chronomètre
|--------------------------------------------------------------------------
|
| La valeur de référence vient du serveur à chaque sondage ; entre deux
| sondages, on incrémente localement. L'affichage reste ainsi fluide sans
| dériver, même si l'onglet a été mis en veille.
|
*/

const elapsed = ref(0);

const formattedElapsed = computed(() => {
    const total = Math.max(0, elapsed.value);
    const minutes = String(Math.floor(total / 60)).padStart(2, "0");
    const seconds = String(total % 60).padStart(2, "0");

    return `${minutes}:${seconds}`;
});

/*
|--------------------------------------------------------------------------
| Sonnerie
|--------------------------------------------------------------------------
|
| Générée avec l'API Web Audio plutôt qu'avec un fichier son : aucun asset
| à héberger, aucune requête réseau, et le rythme reste réglable.
|
| Cadence française : deux tonalités superposées, 1,5 s de sonnerie puis
| 3,5 s de silence.
|
*/

let audioContext = null;
let ringTimer = null;

const ensureAudioContext = () => {
    if (!audioContext) {
        const Context = window.AudioContext || window.webkitAudioContext;

        if (!Context) {
            return null;
        }

        audioContext = new Context();
    }

    if (audioContext.state === "suspended") {
        audioContext.resume().catch(() => {});
    }

    return audioContext;
};

const playRingBurst = () => {
    const context = ensureAudioContext();

    if (!context) {
        return;
    }

    /*
     * Le navigateur refuse de jouer un son tant que l'utilisateur n'a
     * pas interagi avec la page. On le signale plutôt que de laisser
     * l'agent croire que son poste est muet.
     */
    if (context.state !== "running") {
        soundBlocked.value = true;
        return;
    }

    soundBlocked.value = false;

    const gain = context.createGain();

    gain.connect(context.destination);
    gain.gain.setValueAtTime(0.0001, context.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.18, context.currentTime + 0.05);
    gain.gain.setValueAtTime(0.18, context.currentTime + 1.4);
    gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 1.5);

    [440, 480].forEach((frequency) => {
        const oscillator = context.createOscillator();

        oscillator.type = "sine";
        oscillator.frequency.value = frequency;
        oscillator.connect(gain);
        oscillator.start(context.currentTime);
        oscillator.stop(context.currentTime + 1.5);
    });
};

const startRinging = () => {
    if (ringTimer) {
        return;
    }

    playRingBurst();

    ringTimer = setInterval(playRingBurst, 5000);
};

const stopRinging = () => {
    if (ringTimer) {
        clearInterval(ringTimer);
        ringTimer = null;
    }
};

/*
 * Deux notes courtes pour signaler la fin de l'appel.
 */
const playHangUpTone = () => {
    const context = ensureAudioContext();

    if (!context || context.state !== "running") {
        return;
    }

    const gain = context.createGain();

    gain.connect(context.destination);
    gain.gain.setValueAtTime(0.12, context.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.35);

    const oscillator = context.createOscillator();

    oscillator.type = "sine";
    oscillator.frequency.setValueAtTime(420, context.currentTime);
    oscillator.frequency.setValueAtTime(320, context.currentTime + 0.18);
    oscillator.connect(gain);
    oscillator.start(context.currentTime);
    oscillator.stop(context.currentTime + 0.35);
};

/*
|--------------------------------------------------------------------------
| Sondage
|--------------------------------------------------------------------------
*/

const poll = async () => {
    try {
        const response = await fetch(route("call-desk.incoming"), {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        const wasActive = active.value;

        ringing.value = data.ringing;
        active.value = data.active;

        if (data.active) {
            elapsed.value = data.active.elapsed ?? 0;
        }

        /*
         * Le client a raccroché de son côté : on referme la barre
         * d'appel sans attendre une action de l'agent.
         */
        if (wasActive && !data.active) {
            playHangUpTone();
            elapsed.value = 0;
        }

        if (data.ringing && !data.active) {
            startRinging();
        } else {
            stopRinging();
        }
    } catch {
        /*
         * Coupure réseau passagère : on retentera au prochain cycle.
         */
    }
};

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

const post = async (url) => {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    return fetch(url, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": token ?? "",
        },
        credentials: "same-origin",
    });
};

const accept = async () => {
    if (!ringing.value || busy.value) {
        return;
    }

    busy.value = true;
    stopRinging();

    /*
     * Le premier clic débloque aussi l'audio du navigateur
     * pour les appels suivants.
     */
    ensureAudioContext();

    try {
        const response = await post(
            route("call-desk.accept", ringing.value.id),
        );

        const data = await response.json();

        if (data.success) {
            active.value = data.call;
            elapsed.value = 0;
            ringing.value = null;
        } else {
            ringing.value = null;
        }
    } finally {
        busy.value = false;
    }
};

const decline = async () => {
    if (!ringing.value || busy.value) {
        return;
    }

    busy.value = true;
    stopRinging();

    const id = ringing.value.id;
    ringing.value = null;

    try {
        await post(route("call-desk.decline", id));
    } finally {
        busy.value = false;
    }
};

const hangUp = async () => {
    if (!active.value || busy.value) {
        return;
    }

    busy.value = true;

    const id = active.value.id;

    try {
        await post(route("call-desk.hang-up", id));

        playHangUpTone();
        active.value = null;
        elapsed.value = 0;
    } finally {
        busy.value = false;
    }
};

const openConversation = () => {
    if (active.value?.conversation_id) {
        router.visit(
            route("conversations.show", active.value.conversation_id),
        );
    }
};

/*
|--------------------------------------------------------------------------
| Cycle de vie
|--------------------------------------------------------------------------
*/

onMounted(() => {
    poll();

    pollTimer = setInterval(poll, POLL_INTERVAL);

    tickTimer = setInterval(() => {
        if (active.value) {
            elapsed.value += 1;
        }
    }, 1000);

    /*
     * Première interaction de l'agent avec la page : on en profite
     * pour autoriser l'audio, sinon la sonnerie resterait muette.
     */
    const unlock = () => {
        ensureAudioContext();
        window.removeEventListener("pointerdown", unlock);
        window.removeEventListener("keydown", unlock);
    };

    window.addEventListener("pointerdown", unlock);
    window.addEventListener("keydown", unlock);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    clearInterval(tickTimer);
    stopRinging();
});
</script>

<template>
    <!-- ==============================================================
         APPEL ENTRANT
    =============================================================== -->

    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-3 scale-95"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0 scale-95"
    >
        <div
            v-if="ringing && !active"
            class="fixed bottom-6 right-6 z-[60] w-[330px] overflow-hidden rounded-2xl bg-night-800 shadow-pop ring-1 ring-night-700"
        >
            <!-- Halo pulsé -->

            <div class="relative px-5 pb-5 pt-6">
                <div class="flex items-center gap-4">
                    <span class="relative flex h-12 w-12 shrink-0">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"
                        ></span>
                        <span
                            class="relative inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500 text-xl"
                        >
                            📞
                        </span>
                    </span>

                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold uppercase tracking-wide text-emerald-300"
                        >
                            Appel entrant
                        </p>

                        <p class="truncate text-base font-semibold text-white">
                            {{ ringing.client?.name || "Client inconnu" }}
                        </p>

                        <p
                            v-if="ringing.client?.company || ringing.client?.phone"
                            class="truncate text-xs text-night-300"
                        >
                            {{ ringing.client?.company || ringing.client?.phone }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="soundBlocked"
                    class="mt-3 rounded-lg bg-night-700 px-3 py-2 text-[11px] leading-relaxed text-night-200"
                >
                    Le navigateur bloque la sonnerie tant que vous n'avez pas
                    cliqué sur la page. Un seul clic suffit à l'autoriser.
                </p>

                <div class="mt-5 flex gap-2">
                    <button
                        type="button"
                        :disabled="busy"
                        class="flex-1 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-400 disabled:opacity-50"
                        @click="accept"
                    >
                        Décrocher
                    </button>

                    <button
                        type="button"
                        :disabled="busy"
                        class="rounded-xl bg-night-700 px-4 py-2.5 text-sm font-medium text-night-200 transition hover:bg-night-600 hover:text-white disabled:opacity-50"
                        @click="decline"
                    >
                        Refuser
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <!-- ==============================================================
         APPEL EN COURS
    =============================================================== -->

    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-3"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0 translate-y-3"
    >
        <div
            v-if="active"
            class="fixed bottom-6 right-6 z-[60] w-[330px] overflow-hidden rounded-2xl bg-night-800 shadow-pop ring-1 ring-night-700"
        >
            <div class="px-5 pb-5 pt-5">
                <div class="flex items-center gap-4">
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-xl ring-1 ring-emerald-400/40"
                    >
                        🎧
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"
                            ></span>
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wide text-emerald-300"
                            >
                                En ligne
                            </p>
                        </div>

                        <p class="truncate text-base font-semibold text-white">
                            {{ active.client?.name || "Client inconnu" }}
                        </p>
                    </div>

                    <span
                        class="shrink-0 font-mono text-sm tabular-nums text-night-200"
                    >
                        {{ formattedElapsed }}
                    </span>
                </div>

                <div class="mt-5 flex gap-2">
                    <button
                        v-if="active.conversation_id"
                        type="button"
                        class="flex-1 rounded-xl bg-night-700 px-4 py-2.5 text-sm font-medium text-night-100 transition hover:bg-night-600 hover:text-white"
                        @click="openConversation"
                    >
                        Voir le dossier
                    </button>

                    <button
                        type="button"
                        :disabled="busy"
                        class="flex-1 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-50"
                        @click="hangUp"
                    >
                        Raccrocher
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
