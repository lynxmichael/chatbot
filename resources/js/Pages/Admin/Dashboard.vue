<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";
import FlashMessages from "@/Components/UI/FlashMessages.vue";

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    currency: { type: String, default: "XOF" },
    plans: { type: Array, default: () => ["free", "pro", "business"] },
});

/*
|--------------------------------------------------------------------------
| Accueil d'un nouveau client
|--------------------------------------------------------------------------
|
| Pour inscrire vous-même une entreprise après une démonstration, sans
| lui demander de passer par le formulaire public.
|
*/

const showForm = ref(false);

const form = useForm({
    company_name: "",
    owner_name: "",
    email: "",
    phone: "",
    password: "",
    plan: "free",
});

/*
 * Mot de passe provisoire proposé : plus sûr qu'un « motdepasse123 »
 * saisi à la hâte, et le client le changera depuis son profil.
 */
const suggestPassword = () => {
    const alphabet = "abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";

    form.password = Array.from(
        { length: 12 },
        () => alphabet[Math.floor(Math.random() * alphabet.length)],
    ).join("");
};

const submit = () =>
    form.post(route("admin.organizations.store"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });

const money = (amount, currency = props.currency) =>
    new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency,
        maximumFractionDigits: currency === "XOF" ? 0 : 2,
    }).format(amount ?? 0);

const dollars = (amount) =>
    new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
    }).format(amount ?? 0);

const plans = ["free", "pro", "business"];

const changing = ref(null);

const changePlan = (organization, plan) => {
    if (plan === organization.plan) {
        return;
    }

    changing.value = organization.id;

    router.post(
        route("admin.organizations.plan", organization.id),
        { plan },
        {
            preserveScroll: true,
            onFinish: () => (changing.value = null),
        },
    );
};

/*
 * Une entreprise dont le coût dépasse le revenu est signalée : c'est
 * le signe d'une formule mal calibrée, et ça ne se voit qu'en mettant
 * les deux chiffres côte à côte.
 */
const isUnprofitable = (organization) =>
    organization.revenue > 0 && organization.cost * 600 > organization.revenue;

const sorted = computed(() =>
    [...props.organizations].sort((a, b) => b.revenue - a.revenue),
);
</script>

<template>
    <Head title="Plateforme" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Plateforme"
                description="Toutes vos entreprises clientes, ce qu'elles rapportent et ce qu'elles coûtent."
            >
                <template #actions>
                    <Link
                        :href="route('admin.payments')"
                        class="relative rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Règlements
                        <span
                            v-if="totals.pending_payments"
                            class="ml-1.5 rounded-full bg-amber-500 px-1.5 py-0.5 text-xs font-semibold text-white"
                        >
                            {{ totals.pending_payments }}
                        </span>
                    </Link>

                    <Link
                        :href="route('admin.settings')"
                        class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Coordonnées
                    </Link>

                    <button
                        type="button"
                        class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"
                        @click="showForm = !showForm"
                    >
                        {{ showForm ? "Annuler" : "Nouvelle entreprise" }}
                    </button>
                </template>
            </PageHeader>

            <FlashMessages />

            <!-- Création d'une entreprise -->

            <SurfaceCard
                v-if="showForm"
                title="Nouvelle entreprise"
                description="Crée l'entreprise, son compte propriétaire et son jeton de widget."
            >
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Nom de l'entreprise
                            </label>
                            <input
                                v-model="form.company_name"
                                type="text"
                                required
                                placeholder="Boutique Awa"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p
                                v-if="form.errors.company_name"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.company_name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Nom du responsable
                            </label>
                            <input
                                v-model="form.owner_name"
                                type="text"
                                required
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p
                                v-if="form.errors.owner_name"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.owner_name }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Email de connexion
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                required
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
                                Téléphone
                            </label>
                            <input
                                v-model="form.phone"
                                type="text"
                                placeholder="+225 07 00 00 00 00"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Mot de passe provisoire
                            </label>

                            <div class="mt-1 flex gap-2">
                                <input
                                    v-model="form.password"
                                    type="text"
                                    required
                                    minlength="8"
                                    class="w-full rounded-xl border-line font-mono text-sm focus:border-brand-400 focus:ring-brand-400"
                                />

                                <button
                                    type="button"
                                    class="shrink-0 rounded-xl border border-line px-3 text-sm text-night-600 transition hover:bg-canvas-sunken"
                                    @click="suggestPassword"
                                >
                                    Générer
                                </button>
                            </div>

                            <p class="mt-1 text-xs text-night-400">
                                À communiquer au client. Il le changera
                                depuis son profil.
                            </p>

                            <p
                                v-if="form.errors.password"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Formule
                            </label>
                            <select
                                v-model="form.plan"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            >
                                <option
                                    v-for="plan in plans"
                                    :key="plan"
                                    :value="plan"
                                >
                                    {{ plan }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-night-400">
                                Une formule payante posée ici n'ouvre
                                aucun règlement : à réserver aux clients
                                déjà réglés ou en essai accordé.
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50"
                        >
                            Créer l'entreprise
                        </button>
                    </div>
                </form>
            </SurfaceCard>

            <!-- Chiffres clés -->

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div class="rounded-xl border border-line bg-white px-4 py-3">
                    <p class="text-xs text-night-400">Revenu mensuel</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-600">
                        {{ money(totals.recurring_revenue) }}
                    </p>
                </div>

                <div class="rounded-xl border border-line bg-white px-4 py-3">
                    <p class="text-xs text-night-400">Coût estimé</p>
                    <p class="mt-1 text-2xl font-semibold text-night-800">
                        {{ dollars(totals.estimated_cost) }}
                    </p>
                </div>

                <div class="rounded-xl border border-line bg-white px-4 py-3">
                    <p class="text-xs text-night-400">Clients payants</p>
                    <p class="mt-1 text-2xl font-semibold text-brand-600">
                        {{ totals.paying }} / {{ totals.organizations }}
                    </p>
                </div>

                <div class="rounded-xl border border-line bg-white px-4 py-3">
                    <p class="text-xs text-night-400">À confirmer</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-600">
                        {{ totals.pending_payments }}
                    </p>
                </div>

                <div class="rounded-xl border border-line bg-white px-4 py-3">
                    <p class="text-xs text-night-400">Conversations du jour</p>
                    <p class="mt-1 text-2xl font-semibold text-night-800">
                        {{ totals.conversations_today }}
                    </p>
                </div>
            </div>

            <!-- Entreprises -->

            <SurfaceCard flush>
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-line text-left text-xs text-night-400"
                        >
                            <th class="px-6 py-3">Entreprise</th>
                            <th class="px-3 py-3">Formule</th>
                            <th class="px-3 py-3">Messages</th>
                            <th class="px-3 py-3">Appels</th>
                            <th class="px-3 py-3">Revenu</th>
                            <th class="px-3 py-3">Coût</th>
                            <th class="px-3 py-3">Échéance</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line">
                        <tr
                            v-for="organization in sorted"
                            :key="organization.id"
                            class="transition hover:bg-canvas-sunken"
                        >
                            <td class="px-6 py-3">
                                <p class="font-medium text-night-900">
                                    {{ organization.name }}
                                </p>
                                <p class="text-xs text-night-400">
                                    {{ organization.agents }} agent(s) · depuis
                                    {{ organization.created_at }}
                                    <template v-if="organization.demo_url">
                                        ·
                                        <a
                                            :href="organization.demo_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="font-medium text-brand-600 hover:underline"
                                        >
                                            tester
                                        </a>
                                    </template>
                                </p>
                            </td>

                            <td class="px-3 py-3">
                                <select
                                    :value="organization.plan"
                                    :disabled="changing === organization.id"
                                    class="rounded-lg border-line py-1 text-xs focus:border-brand-400 focus:ring-brand-400"
                                    @change="
                                        changePlan(
                                            organization,
                                            $event.target.value,
                                        )
                                    "
                                >
                                    <option
                                        v-for="plan in plans"
                                        :key="plan"
                                        :value="plan"
                                    >
                                        {{ plan }}
                                    </option>
                                </select>
                            </td>

                            <td class="px-3 py-3">
                                <span
                                    class="font-medium"
                                    :class="
                                        (organization.messages.ratio ?? 0) >= 1
                                            ? 'text-rose-600'
                                            : 'text-night-700'
                                    "
                                >
                                    {{ organization.messages.used }}
                                </span>
                                <span class="text-night-400">
                                    /
                                    {{
                                        organization.messages.unlimited
                                            ? "∞"
                                            : organization.messages.limit
                                    }}
                                </span>
                            </td>

                            <td class="px-3 py-3 text-night-700">
                                {{ organization.voice_calls }}
                            </td>

                            <td class="px-3 py-3 font-medium text-emerald-700">
                                {{
                                    organization.revenue
                                        ? money(organization.revenue)
                                        : "—"
                                }}
                            </td>

                            <td
                                class="px-3 py-3"
                                :class="
                                    isUnprofitable(organization)
                                        ? 'font-semibold text-rose-600'
                                        : 'text-night-500'
                                "
                            >
                                {{ dollars(organization.cost) }}
                            </td>

                            <td class="px-3 py-3 text-xs">
                                <template v-if="organization.ends_at">
                                    <span
                                        :class="
                                            organization.days_remaining <= 7
                                                ? 'font-semibold text-amber-700'
                                                : 'text-night-500'
                                        "
                                    >
                                        {{ organization.ends_at }}
                                    </span>
                                </template>
                                <span v-else class="text-night-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </SurfaceCard>

            <p class="text-xs text-night-400">
                Le coût est estimé à partir des jetons consommés et des
                minutes d'appel. Une ligne en rouge signale une entreprise
                dont le coût approche ou dépasse ce qu'elle rapporte.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
