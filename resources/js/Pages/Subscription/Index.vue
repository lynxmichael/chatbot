<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";

const props = defineProps({
    plans: { type: Array, default: () => [] },
    current: { type: Object, required: true },
    usage: { type: Object, default: () => ({}) },
    payments: { type: Array, default: () => [] },
    provider: { type: String, default: "manual" },
    currency: { type: String, default: "XOF" },
    payment_details: { type: Object, default: () => ({}) },
});

const form = useForm({ plan: null });

const choosing = ref(null);

const choose = (plan) => {
    if (plan.name === props.current.plan || plan.price === 0) {
        return;
    }

    choosing.value = plan.name;

    form.plan = plan.name;

    form.post(route("subscription.checkout"), {
        preserveScroll: true,
        onFinish: () => {
            choosing.value = null;
        },
    });
};

/*
 * Le franc CFA n'a pas de centimes : on formate en conséquence plutôt
 * que d'afficher « 25 000,00 ».
 */
const money = (amount, currency = props.currency) =>
    new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency,
        maximumFractionDigits: currency === "XOF" ? 0 : 2,
    }).format(amount ?? 0);

const quantity = (value) =>
    value < 0 ? "illimité" : value === 0 ? "—" : value.toLocaleString("fr-FR");

const levelLabels = {
    off: "désactivé",
    suggest: "suggestion",
    assist: "assisté",
    auto: "autonome",
};

const actionLabels = {
    search_knowledge: "Recherche dans vos fiches",
    get_client_profile: "Dossier client complet",
    get_order_status: "Suivi des commandes",
    get_ticket_status: "Consultation des tickets",
    record_insights: "Analyse des conversations",
    create_ticket: "Création de tickets",
    update_ticket: "Mise à jour des tickets",
    schedule_follow_up: "Relances automatiques",
    escalate_to_human: "Transfert vers un agent",
};

/*
 * Ce que la formule apporte de plus que la formule actuelle : c'est
 * l'information qui décide, pas la liste complète.
 */
const currentPlan = computed(() =>
    props.plans.find((plan) => plan.name === props.current.plan),
);

const extras = (plan) => {
    const owned = currentPlan.value?.actions ?? [];

    return plan.actions.filter((action) => !owned.includes(action));
};

const statusTone = {
    paid: "text-emerald-600",
    pending: "text-amber-600",
    failed: "text-rose-600",
    cancelled: "text-night-400",
};

const statusLabel = {
    paid: "réglé",
    pending: "en attente",
    failed: "échoué",
    cancelled: "annulé",
};

/*
 * Seuls les moyens réellement renseignés sont montrés : une ligne
 * « Wave : — » donne l'impression d'un service à moitié configuré.
 */
const channels = computed(() => {
    const labels = {
        wave: "Wave",
        orange_money: "Orange Money",
        mtn_money: "MTN Money",
        moov_money: "Moov Money",
        bank_name: "Banque",
        bank_holder: "Titulaire",
        bank_iban: "Compte",
    };

    return Object.entries(labels)
        .filter(([key]) => props.payment_details?.[key])
        .map(([key, label]) => ({
            label,
            value: props.payment_details[key],
        }));
});

const expiringSoon = computed(
    () =>
        props.current.days_remaining !== null &&
        props.current.days_remaining <= 7,
);
</script>

<template>
    <Head title="Abonnement" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Abonnement"
                description="Votre formule, votre consommation, et ce que débloquerait la suivante."
            />

            <FlashMessages />

            <!-- Formule en cours -->

            <SurfaceCard>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs text-night-400">Formule actuelle</p>

                        <p class="mt-1 text-2xl font-semibold text-night-900">
                            {{ currentPlan?.label ?? current.plan }}
                        </p>

                        <p
                            v-if="current.ends_at"
                            class="mt-1 text-sm"
                            :class="
                                expiringSoon ? 'text-amber-700' : 'text-night-400'
                            "
                        >
                            Valable jusqu'au {{ current.ends_at }}
                            <template v-if="current.days_remaining !== null">
                                ·
                                {{
                                    current.days_remaining > 0
                                        ? current.days_remaining + " jour(s) restant(s)"
                                        : "échéance dépassée"
                                }}
                            </template>
                        </p>

                        <p v-else class="mt-1 text-sm text-night-400">
                            Sans engagement ni date de fin.
                        </p>
                    </div>

                    <!-- Consommation -->

                    <div class="min-w-[220px] space-y-3">
                        <div>
                            <div class="flex justify-between text-xs">
                                <span class="text-night-400">Réponses</span>
                                <span class="font-semibold text-night-700">
                                    {{ usage.ai_messages?.used ?? 0 }}
                                    <template v-if="!usage.ai_messages?.unlimited">
                                        / {{ usage.ai_messages?.limit }}
                                    </template>
                                    <template v-else>/ ∞</template>
                                </span>
                            </div>

                            <div
                                v-if="!usage.ai_messages?.unlimited"
                                class="mt-1 h-1.5 overflow-hidden rounded-full bg-canvas-sunken"
                            >
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="
                                        (usage.ai_messages?.ratio ?? 0) >= 1
                                            ? 'bg-rose-500'
                                            : (usage.ai_messages?.ratio ?? 0) >= 0.8
                                              ? 'bg-amber-500'
                                              : 'bg-emerald-500'
                                    "
                                    :style="{
                                        width:
                                            Math.min(
                                                100,
                                                (usage.ai_messages?.ratio ?? 0) * 100,
                                            ) + '%',
                                    }"
                                ></div>
                            </div>
                        </div>

                        <div class="flex justify-between text-xs">
                            <span class="text-night-400">Appels vocaux</span>
                            <span class="font-semibold text-night-700">
                                <template v-if="usage.voice_calls?.blocked">
                                    fermés
                                </template>
                                <template v-else>
                                    {{ usage.voice_calls?.used ?? 0 }}
                                    <template v-if="!usage.voice_calls?.unlimited">
                                        / {{ usage.voice_calls?.limit }}
                                    </template>
                                </template>
                            </span>
                        </div>
                    </div>
                </div>

                <p
                    v-if="usage.ai_messages?.ratio >= 1"
                    class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200"
                >
                    Votre plafond mensuel est atteint : les demandes partent
                    désormais vers vos agents. Une formule supérieure lève
                    cette limite immédiatement.
                </p>
            </SurfaceCard>

            <!-- Formules -->

            <div class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="plan in plans"
                    :key="plan.name"
                    class="flex flex-col rounded-2xl border bg-white p-6 shadow-lift transition"
                    :class="
                        plan.name === current.plan
                            ? 'border-brand-400 ring-1 ring-brand-300'
                            : 'border-line hover:border-line-strong'
                    "
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 class="text-lg font-semibold text-night-900">
                            {{ plan.label }}
                        </h2>

                        <span
                            v-if="plan.name === current.plan"
                            class="rounded-md bg-brand-100 px-2 py-0.5 text-[11px] font-semibold text-brand-700"
                        >
                            en cours
                        </span>
                    </div>

                    <p class="mt-3 text-2xl font-semibold text-night-900">
                        <template v-if="plan.price === 0">Gratuit</template>
                        <template v-else>
                            {{ money(plan.price, plan.currency) }}
                            <span class="text-sm font-normal text-night-400">
                                / mois
                            </span>
                        </template>
                    </p>

                    <p v-if="plan.pitch" class="mt-2 text-sm text-night-500">
                        {{ plan.pitch }}
                    </p>

                    <dl class="mt-5 space-y-2 border-t border-line pt-4 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-night-500">Réponses / mois</dt>
                            <dd class="font-medium text-night-800">
                                {{ quantity(plan.ai_messages) }}
                            </dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-night-500">Appels vocaux</dt>
                            <dd class="font-medium text-night-800">
                                {{ quantity(plan.voice_calls) }}
                            </dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-night-500">Autonomie</dt>
                            <dd class="font-medium text-night-800">
                                {{ levelLabels[plan.level] ?? plan.level }}
                            </dd>
                        </div>
                    </dl>

                    <!-- Ce que cette formule ajoute -->

                    <ul
                        v-if="
                            plan.name !== current.plan && extras(plan).length
                        "
                        class="mt-4 space-y-1.5 text-sm"
                    >
                        <li
                            v-for="action in extras(plan)"
                            :key="action"
                            class="flex items-start gap-2 text-night-600"
                        >
                            <span class="mt-0.5 text-emerald-500">+</span>
                            {{ actionLabels[action] ?? action }}
                        </li>
                    </ul>

                    <div class="mt-6 flex-1"></div>

                    <button
                        v-if="plan.name !== current.plan && plan.price > 0"
                        type="button"
                        :disabled="form.processing"
                        class="w-full rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50"
                        @click="choose(plan)"
                    >
                        {{
                            choosing === plan.name
                                ? "Ouverture…"
                                : "Choisir cette formule"
                        }}
                    </button>

                    <p
                        v-else-if="plan.name === current.plan"
                        class="text-center text-sm text-night-400"
                    >
                        Votre formule actuelle
                    </p>
                </article>
            </div>

            <!-- Règlement hors ligne -->

            <SurfaceCard
                v-if="provider === 'manual'"
                title="Comment régler"
                description="Indiquez votre référence lors du règlement : sans elle, votre paiement ne peut pas être rattaché."
            >
                <dl class="space-y-2 text-sm">
                    <div
                        v-for="item in channels"
                        :key="item.label"
                        class="flex flex-wrap justify-between gap-2 border-b border-line pb-2 last:border-0"
                    >
                        <dt class="text-night-500">{{ item.label }}</dt>
                        <dd class="font-medium text-night-900">
                            {{ item.value }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="payment_details.instructions"
                    class="mt-4 whitespace-pre-line text-sm leading-relaxed text-night-600"
                >
                    {{ payment_details.instructions }}
                </p>

                <p
                    v-if="!channels.length"
                    class="text-sm text-night-500"
                >
                    Les coordonnées de règlement seront communiquées après
                    votre demande.
                </p>
            </SurfaceCard>

            <!-- Historique -->

            <SurfaceCard v-if="payments.length" title="Vos règlements" flush>
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-line text-left text-xs text-night-400"
                        >
                            <th class="px-6 py-3">Référence</th>
                            <th class="px-3 py-3">Formule</th>
                            <th class="px-3 py-3">Montant</th>
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">État</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line">
                        <tr v-for="payment in payments" :key="payment.reference">
                            <td class="px-6 py-3 font-mono text-xs text-night-600">
                                {{ payment.reference }}
                            </td>
                            <td class="px-3 py-3 text-night-700">
                                {{ payment.plan }}
                            </td>
                            <td class="px-3 py-3 font-medium text-night-800">
                                {{ money(payment.amount, payment.currency) }}
                            </td>
                            <td class="px-3 py-3 text-night-500">
                                {{ payment.paid_at ?? payment.created_at }}
                            </td>
                            <td
                                class="px-3 py-3 font-medium"
                                :class="statusTone[payment.status]"
                            >
                                {{ statusLabel[payment.status] ?? payment.status }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </SurfaceCard>
        </div>
    </AuthenticatedLayout>
</template>
