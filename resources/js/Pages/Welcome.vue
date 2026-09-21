<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";

/*
|--------------------------------------------------------------------------
| Page d'accueil
|--------------------------------------------------------------------------
|
| La vitrine du produit, pour une entreprise qui ne le connaît pas
| encore. Trois questions auxquelles répondre, dans cet ordre : qu'est-ce
| que ça fait pour moi, combien ça coûte, comment je commence.
|
*/

const props = defineProps({
    canRegister: { type: Boolean, default: true },
    plans: { type: Array, default: () => [] },
});

const page = usePage();

const appName = computed(() => page.props.app?.name ?? "AI Service Client");

const money = (amount, currency = "XOF") =>
    new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency,
        maximumFractionDigits: currency === "XOF" ? 0 : 2,
    }).format(amount ?? 0);

/*
 * Ce que le produit fait, formulé du point de vue du client : pas
 * « agent à outils », mais ce qui change dans sa journée.
 */
const benefits = [
    {
        icon: "💬",
        title: "Répond à votre place, jour et nuit",
        text: "Horaires, tarifs, disponibilités, suivi de commande : l'assistant répond aux questions courantes à partir de vos propres informations. Il ne prend jamais de pause.",
    },
    {
        icon: "📞",
        title: "Décroche le téléphone",
        text: "Il accueille vos appels, comprend la demande et répond de vive voix. Si la demande le dépasse, il passe la main à un conseiller.",
    },
    {
        icon: "🖼️",
        title: "Montre au lieu de décrire",
        text: "Une chambre, un plat, un produit : il envoie les photos que vous avez choisies, au moment où le client demande à voir.",
    },
    {
        icon: "🎫",
        title: "N'oublie aucune demande",
        text: "Chaque réclamation devient un ticket, attribué au bon agent, avec un délai de réponse. Les relances partent toutes seules.",
    },
    {
        icon: "👁️",
        title: "Vous alerte avant que ça dérape",
        text: "Client sans réponse, délai bientôt dépassé, agent débordé : vous le savez avant que le client ne s'en plaigne.",
    },
    {
        icon: "🛡️",
        title: "Fait ce que vous autorisez, rien de plus",
        text: "Vous décidez de ce qu'il peut faire seul et de ce qui doit passer par vous. Il ne promet jamais ce qu'il n'a pas vérifié.",
    },
];

const steps = [
    {
        title: "Créez votre compte",
        text: "Le nom de votre entreprise et une adresse email suffisent.",
    },
    {
        title: "Collez votre FAQ",
        text: "Vos horaires, vos prix, vos conditions. L'assistant apprend en quelques secondes.",
    },
    {
        title: "Installez le widget",
        text: "Une ligne à copier sur votre site, à vos couleurs et avec votre logo.",
    },
];
</script>

<template>
    <Head :title="null" />

    <div class="min-h-screen bg-canvas text-night-900">
        <!-- ============================================================
             EN-TÊTE
        ============================================================= -->

        <header class="border-b border-line bg-white/80 backdrop-blur">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-sm font-bold text-white"
                    >
                        AI
                    </span>
                    <span class="font-semibold tracking-tight">{{ appName }}</span>
                </div>

                <nav class="flex items-center gap-2">
                    <Link
                        :href="route('login')"
                        class="rounded-xl px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Se connecter
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"
                    >
                        Essayer gratuitement
                    </Link>
                </nav>
            </div>
        </header>

        <!-- ============================================================
             ACCROCHE
        ============================================================= -->

        <section class="mx-auto max-w-6xl px-6 pb-16 pt-20 text-center">
            <p
                class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-1.5 text-xs font-medium text-night-500 ring-1 ring-line"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Service client disponible 24 heures sur 24
            </p>

            <h1
                class="mx-auto mt-6 max-w-3xl text-4xl font-semibold leading-tight tracking-tight sm:text-5xl"
            >
                Vos clients obtiennent une réponse,
                <span class="text-brand-600">même quand vous dormez.</span>
            </h1>

            <p
                class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-night-500"
            >
                Un assistant qui répond sur votre site et au téléphone, à
                partir de vos propres informations. Vos agents ne gèrent plus
                que ce qui demande vraiment un humain.
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="rounded-xl bg-brand-500 px-6 py-3 text-base font-semibold text-white shadow-lift transition hover:bg-brand-600"
                >
                    Créer mon compte gratuit
                </Link>

                <Link
                    :href="route('login')"
                    class="rounded-xl bg-white px-6 py-3 text-base font-medium text-night-700 ring-1 ring-line transition hover:bg-canvas-sunken"
                >
                    J'ai déjà un compte
                </Link>
            </div>

            <p class="mt-4 text-sm text-night-400">
                Sans carte bancaire. Opérationnel en dix minutes.
            </p>
        </section>

        <!-- ============================================================
             BÉNÉFICES
        ============================================================= -->

        <section class="mx-auto max-w-6xl px-6 pb-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="benefit in benefits"
                    :key="benefit.title"
                    class="rounded-2xl border border-line bg-white p-6 shadow-lift"
                >
                    <div class="text-2xl">{{ benefit.icon }}</div>

                    <h2 class="mt-3 font-semibold tracking-tight">
                        {{ benefit.title }}
                    </h2>

                    <p class="mt-2 text-sm leading-relaxed text-night-500">
                        {{ benefit.text }}
                    </p>
                </article>
            </div>
        </section>

        <!-- ============================================================
             DÉMARRAGE
        ============================================================= -->

        <section class="border-y border-line bg-white py-20">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="text-center text-3xl font-semibold tracking-tight">
                    En place avant votre prochain café
                </h2>

                <div class="mt-12 grid gap-8 md:grid-cols-3">
                    <div
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="text-center"
                    >
                        <span
                            class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-brand-100 text-lg font-bold text-brand-700"
                        >
                            {{ index + 1 }}
                        </span>

                        <h3 class="mt-4 font-semibold">{{ step.title }}</h3>

                        <p class="mt-2 text-sm leading-relaxed text-night-500">
                            {{ step.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             TARIFS
        ============================================================= -->

        <section v-if="plans.length" class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="text-center text-3xl font-semibold tracking-tight">
                Commencez gratuitement
            </h2>

            <p class="mx-auto mt-3 max-w-xl text-center text-night-500">
                Passez à une formule supérieure quand l'assistant vous fait
                gagner du temps, pas avant.
            </p>

            <div class="mt-12 grid gap-4 md:grid-cols-3">
                <article
                    v-for="plan in plans"
                    :key="plan.name"
                    class="flex flex-col rounded-2xl border bg-white p-7 shadow-lift"
                    :class="
                        plan.name === 'pro'
                            ? 'border-brand-400 ring-1 ring-brand-300'
                            : 'border-line'
                    "
                >
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ plan.label }}</h3>

                        <span
                            v-if="plan.name === 'pro'"
                            class="rounded-md bg-brand-100 px-2 py-0.5 text-[11px] font-semibold text-brand-700"
                        >
                            le plus choisi
                        </span>
                    </div>

                    <p class="mt-4 text-3xl font-semibold tracking-tight">
                        <template v-if="plan.price === 0">Gratuit</template>
                        <template v-else>
                            {{ money(plan.price, plan.currency) }}
                            <span class="text-base font-normal text-night-400">
                                / mois
                            </span>
                        </template>
                    </p>

                    <p class="mt-3 flex-1 text-sm leading-relaxed text-night-500">
                        {{ plan.pitch }}
                    </p>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="mt-6 rounded-xl px-4 py-2.5 text-center text-sm font-semibold transition"
                        :class="
                            plan.name === 'pro'
                                ? 'bg-brand-500 text-white hover:bg-brand-600'
                                : 'bg-canvas-sunken text-night-700 hover:bg-night-100'
                        "
                    >
                        {{ plan.price === 0 ? "Commencer" : "Essayer d'abord gratuitement" }}
                    </Link>
                </article>
            </div>
        </section>

        <!-- ============================================================
             PIED DE PAGE
        ============================================================= -->

        <footer class="border-t border-line bg-white">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-night-400"
            >
                <span>© {{ new Date().getFullYear() }} {{ appName }}</span>

                <div class="flex gap-5">
                    <Link :href="route('login')" class="transition hover:text-night-700">
                        Connexion
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="transition hover:text-night-700"
                    >
                        Inscription
                    </Link>
                </div>
            </div>
        </footer>
    </div>
</template>
