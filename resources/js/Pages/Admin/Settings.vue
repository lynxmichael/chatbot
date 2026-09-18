<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";

const props = defineProps({
    payment_details: { type: Object, default: () => ({}) },
    provider: { type: String, default: "manual" },
    currency: { type: String, default: "XOF" },
    plans: { type: Array, default: () => [] },
    admins: { type: Array, default: () => [] },
});

const form = useForm({
    wave: props.payment_details.wave ?? "",
    orange_money: props.payment_details.orange_money ?? "",
    mtn_money: props.payment_details.mtn_money ?? "",
    moov_money: props.payment_details.moov_money ?? "",
    bank_name: props.payment_details.bank_name ?? "",
    bank_iban: props.payment_details.bank_iban ?? "",
    bank_holder: props.payment_details.bank_holder ?? "",
    instructions: props.payment_details.instructions ?? "",
});

const submit = () =>
    form.patch(route("admin.settings.update"), { preserveScroll: true });

const money = (amount) =>
    new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: props.currency,
        maximumFractionDigits: props.currency === "XOF" ? 0 : 2,
    }).format(amount ?? 0);

const mobile = [
    { key: "wave", label: "Wave" },
    { key: "orange_money", label: "Orange Money" },
    { key: "mtn_money", label: "MTN Money" },
    { key: "moov_money", label: "Moov Money" },
];
</script>

<template>
    <Head title="Coordonnées de règlement" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Coordonnées de règlement"
                description="Ce que vos clients voient quand ils choisissent une formule."
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

            <p
                v-if="provider !== 'manual'"
                class="rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800 ring-1 ring-brand-200"
            >
                Le paiement en ligne est actif ({{ provider }}). Ces
                coordonnées ne servent plus qu'aux clients qui préfèrent
                régler autrement.
            </p>

            <form class="space-y-6" @submit.prevent="submit">
                <SurfaceCard
                    title="Transfert mobile"
                    description="Laissez vide ce que vous n'acceptez pas : seuls les champs remplis sont affichés."
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="item in mobile" :key="item.key">
                            <label class="block text-xs font-medium text-night-500">
                                {{ item.label }}
                            </label>
                            <input
                                v-model="form[item.key]"
                                type="text"
                                placeholder="+225 07 00 00 00 00"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard title="Virement bancaire">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-night-500">
                                    Banque
                                </label>
                                <input
                                    v-model="form.bank_name"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-night-500">
                                    Titulaire
                                </label>
                                <input
                                    v-model="form.bank_holder"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                IBAN ou numéro de compte
                            </label>
                            <input
                                v-model="form.bank_iban"
                                type="text"
                                class="mt-1 w-full rounded-xl border-line font-mono text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    title="Consignes"
                    description="Affichées sous les coordonnées, dans l'espace abonnement du client."
                >
                    <textarea
                        v-model="form.instructions"
                        rows="4"
                        placeholder="Indiquez votre référence d'abonnement dans le motif du règlement. Votre formule est activée sous 24 heures."
                        class="w-full rounded-xl border-line text-sm leading-relaxed focus:border-brand-400 focus:ring-brand-400"
                    ></textarea>

                    <p class="mt-2 text-xs text-night-400">
                        Demandez toujours la référence : sans elle, vous ne
                        saurez pas quel règlement correspond à quel client.
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

            <!-- Rappels -->

            <SurfaceCard title="Vos formules">
                <ul class="space-y-1.5 text-sm">
                    <li
                        v-for="plan in plans"
                        :key="plan.name"
                        class="flex justify-between"
                    >
                        <span class="text-night-600">{{ plan.label }}</span>
                        <span class="font-medium text-night-900">
                            {{ plan.price ? money(plan.price) + " / mois" : "gratuit" }}
                        </span>
                    </li>
                </ul>

                <p class="mt-3 text-xs text-night-400">
                    Les prix se règlent dans le fichier .env
                    (AI_PRICE_PLAN_PRO, AI_PRICE_PLAN_BUSINESS).
                </p>
            </SurfaceCard>

            <SurfaceCard title="Administrateurs de la plateforme">
                <ul class="space-y-1 text-sm">
                    <li
                        v-for="admin in admins"
                        :key="admin.email"
                        class="flex justify-between"
                    >
                        <span class="text-night-700">{{ admin.name }}</span>
                        <span class="text-night-400">{{ admin.email }}</span>
                    </li>
                </ul>

                <p class="mt-3 text-xs text-night-400">
                    Ce droit ne s'accorde qu'en ligne de commande :
                    php artisan ai:super-admin adresse@exemple.ci
                </p>
            </SurfaceCard>
        </div>
    </AuthenticatedLayout>
</template>
