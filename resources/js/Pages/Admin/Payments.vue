<script setup>
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";
import EmptyState from "@/Components/UI/EmptyState.vue";

defineProps({
    payments: { type: Object, required: true },
    filters: { type: Object, default: () => ({ status: "pending" }) },
    pending_count: { type: Number, default: 0 },
});

const busy = ref(null);

const money = (amount, currency) =>
    new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: currency ?? "XOF",
        maximumFractionDigits: currency === "XOF" ? 0 : 2,
    }).format(amount ?? 0);

const methods = ["wave", "orange-money", "mtn-money", "moov-money", "virement", "espèces"];

const confirm = (payment) => {
    const method = window.prompt(
        `Moyen de règlement employé ?\n\nSuggestions : ${methods.join(", ")}`,
        payment.method ?? "wave",
    );

    if (method === null) {
        return;
    }

    busy.value = payment.id;

    router.post(
        route("admin.payments.confirm", payment.id),
        { method },
        { preserveScroll: true, onFinish: () => (busy.value = null) },
    );
};

const reject = (payment) => {
    if (
        !window.confirm(
            `Marquer le règlement ${payment.reference} comme non abouti ?`,
        )
    ) {
        return;
    }

    busy.value = payment.id;

    router.post(
        route("admin.payments.reject", payment.id),
        {},
        { preserveScroll: true, onFinish: () => (busy.value = null) },
    );
};

const filterBy = (status) =>
    router.get(
        route("admin.payments"),
        { status },
        { preserveState: true, preserveScroll: true },
    );

const statuses = [
    { value: "pending", label: "À confirmer" },
    { value: "paid", label: "Réglés" },
    { value: "failed", label: "Non aboutis" },
    { value: "all", label: "Tout" },
];

const tone = {
    paid: "bg-emerald-100 text-emerald-800",
    pending: "bg-amber-100 text-amber-800",
    failed: "bg-rose-100 text-rose-800",
    cancelled: "bg-night-100 text-night-600",
};

const label = {
    paid: "réglé",
    pending: "en attente",
    failed: "non abouti",
    cancelled: "annulé",
};
</script>

<template>
    <Head title="Règlements" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Règlements"
                description="Confirmez les paiements reçus : la formule s'active aussitôt."
            >
                <template #actions>
                    <Link
                        :href="route('admin.index')"
                        class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Retour
                    </Link>
                </template>
            </PageHeader>

            <FlashMessages />

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="status in statuses"
                    :key="status.value"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        filters.status === status.value
                            ? 'bg-night-800 text-white'
                            : 'bg-white text-night-600 ring-1 ring-line hover:bg-canvas-sunken'
                    "
                    @click="filterBy(status.value)"
                >
                    {{ status.label }}
                    <span
                        v-if="status.value === 'pending' && pending_count"
                        class="ml-1 text-xs opacity-80"
                    >
                        ({{ pending_count }})
                    </span>
                </button>
            </div>

            <SurfaceCard flush>
                <div v-if="payments.data.length" class="divide-y divide-line">
                    <article
                        v-for="payment in payments.data"
                        :key="payment.id"
                        class="flex flex-wrap items-center gap-4 px-6 py-4"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-night-900">
                                    {{ payment.organization }}
                                </span>

                                <span
                                    class="rounded-md px-2 py-0.5 text-[11px] font-semibold"
                                    :class="tone[payment.status]"
                                >
                                    {{ label[payment.status] ?? payment.status }}
                                </span>

                                <span
                                    class="rounded-md bg-canvas-sunken px-2 py-0.5 text-[11px] font-medium text-night-600 ring-1 ring-line"
                                >
                                    {{ payment.plan }}
                                </span>
                            </div>

                            <p class="mt-1 font-mono text-xs text-night-400">
                                {{ payment.reference }}
                            </p>

                            <p class="mt-0.5 text-xs text-night-400">
                                Demandé le {{ payment.created_at }}
                                <template v-if="payment.requested_by">
                                    par {{ payment.requested_by }}
                                </template>
                                <template v-if="payment.method">
                                    · {{ payment.method }}
                                </template>
                            </p>
                        </div>

                        <p class="shrink-0 text-lg font-semibold text-night-900">
                            {{ money(payment.amount, payment.currency) }}
                        </p>

                        <div
                            v-if="payment.status === 'pending'"
                            class="flex shrink-0 gap-2"
                        >
                            <button
                                type="button"
                                :disabled="busy === payment.id"
                                class="rounded-lg bg-emerald-500 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600 disabled:opacity-50"
                                @click="confirm(payment)"
                            >
                                J'ai reçu
                            </button>

                            <button
                                type="button"
                                :disabled="busy === payment.id"
                                class="rounded-lg border border-line px-3.5 py-2 text-sm font-medium text-night-600 transition hover:bg-canvas-sunken disabled:opacity-50"
                                @click="reject(payment)"
                            >
                                Rejeter
                            </button>
                        </div>
                    </article>
                </div>

                <EmptyState
                    v-else
                    icon="💰"
                    title="Aucun règlement"
                    description="Les demandes de vos clients apparaîtront ici."
                />
            </SurfaceCard>

            <div
                v-if="payments.links && payments.links.length > 3"
                class="flex flex-wrap gap-1"
            >
                <Link
                    v-for="link in payments.links"
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
