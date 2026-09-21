<script setup>
import { computed, ref } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const page = usePage();

const props = defineProps({
    settings: { type: Object, required: true },
    tools: { type: Array, default: () => [] },
    statistics: { type: Object, default: () => ({}) },
    usage: { type: Object, default: () => ({}) },
});

/*
|--------------------------------------------------------------------------
| Consommation
|--------------------------------------------------------------------------
|
| Affichée en tête, avant les réglages : c'est la première chose qu'un
| responsable veut savoir quand il ouvre cet écran.
|
*/

const quotaBar = (entry) => {
    if (!entry || entry.unlimited) {
        return { width: "0%", tone: "bg-brand-500" };
    }

    const ratio = entry.ratio ?? 0;

    return {
        width: Math.min(100, ratio * 100) + "%",
        tone:
            ratio >= 1
                ? "bg-rose-500"
                : ratio >= 0.8
                  ? "bg-amber-500"
                  : "bg-emerald-500",
    };
};

const cost = computed(() =>
    (props.usage.estimated_cost ?? 0).toLocaleString("fr-FR", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
    }),
);

const successMessage = computed(() => page.props.flash?.success ?? null);
const errorMessage = computed(() => page.props.flash?.error ?? null);

/*
|--------------------------------------------------------------------------
| Formulaire
|--------------------------------------------------------------------------
*/

const form = useForm({
    level: props.settings.level ?? "assist",
    persona: props.settings.persona ?? "",
    business_name: props.settings.business_name ?? "",
    tone: props.settings.tone ?? "",
    language: props.settings.language ?? "fr",
    confidence_threshold: props.settings.confidence_threshold ?? 0.6,
    auto_close_after_hours: props.settings.auto_close_after_hours ?? 72,
    escalate_on_negative_sentiment:
        props.settings.escalate_on_negative_sentiment ?? true,
    allowed_actions: [...(props.settings.allowed_actions ?? [])],
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        confidence_threshold: Number(data.confidence_threshold),
        auto_close_after_hours: Number(data.auto_close_after_hours),
        escalate_on_negative_sentiment: Boolean(
            data.escalate_on_negative_sentiment,
        ),
    })).patch(route("autopilot.update"), {
        preserveScroll: true,
    });
};

const toggleTool = (name) => {
    const index = form.allowed_actions.indexOf(name);

    if (index === -1) {
        form.allowed_actions.push(name);
    } else {
        form.allowed_actions.splice(index, 1);
    }
};

/*
|--------------------------------------------------------------------------
| Niveaux
|--------------------------------------------------------------------------
*/

const levels = [
    {
        value: "off",
        name: "Désactivé",
        summary: "L'IA ne traite aucune demande.",
        detail: "Tout arrive directement aux agents. À utiliser pendant une refonte de la base de connaissances.",
        tone: "bg-night-100 text-night-700",
    },
    {
        value: "suggest",
        name: "Suggestion",
        summary: "L'IA rédige, rien n'est envoyé.",
        detail: "Les réponses sont enregistrées comme brouillons internes. Idéal pour évaluer la qualité avant d'ouvrir au public.",
        tone: "bg-amber-100 text-amber-800",
    },
    {
        value: "assist",
        name: "Assisté",
        summary: "L'IA répond, ses actions sont validées.",
        detail: "Les lectures sont libres. Créer un ticket ou programmer une relance passe par votre validation. Le transfert vers un humain reste immédiat.",
        tone: "bg-brand-100 text-brand-700",
    },
    {
        value: "auto",
        name: "Autonome",
        summary: "L'IA exécute tout ce qui est autorisé.",
        detail: "Aucune validation. À n'activer qu'une fois la liste des actions autorisées bien réglée.",
        tone: "bg-emerald-100 text-emerald-800",
    },
];

const currentLevel = computed(
    () => levels.find((level) => level.value === form.level) ?? levels[2],
);

const readTools = computed(() => props.tools.filter((tool) => !tool.is_write));
const writeTools = computed(() => props.tools.filter((tool) => tool.is_write));

const showAdvanced = ref(false);
</script>

<template>
    <Head title="Autopilot" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- En-tête -->

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-night-900">
                        Autopilot
                    </h1>
                    <p class="mt-1 text-sm text-night-400">
                        Ce que votre assistant a le droit de faire, et ce qui
                        passe par vous.
                    </p>
                </div>

                <div class="flex gap-2">
                    <Link
                        :href="route('autopilot.approvals')"
                        class="relative rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:border-line-strong hover:bg-canvas-sunken"
                    >
                        À valider
                        <span
                            v-if="statistics.pending_actions"
                            class="ml-1.5 rounded-full bg-brand-500 px-1.5 py-0.5 text-xs font-semibold text-white"
                        >
                            {{ statistics.pending_actions }}
                        </span>
                    </Link>

                    <Link
                        :href="route('autopilot.insights')"
                        class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:border-line-strong hover:bg-canvas-sunken"
                    >
                        Alertes
                        <span
                            v-if="statistics.critical_insights"
                            class="ml-1.5 rounded-full bg-rose-500 px-1.5 py-0.5 text-xs font-semibold text-white"
                        >
                            {{ statistics.critical_insights }}
                        </span>
                    </Link>
                </div>
            </div>

            <!-- Messages -->

            <div
                v-if="successMessage"
                class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ successMessage }}
            </div>

            <div
                v-if="errorMessage"
                class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            >
                {{ errorMessage }}
            </div>

            <!-- Consommation du mois -->

            <section
                class="mt-8 rounded-2xl border border-line bg-white p-6 shadow-lift"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="text-sm font-semibold text-night-900">
                        Consommation du mois
                    </h2>

                    <p class="text-xs text-night-400">
                        {{ usage.period }} · coût estimé {{ cost }}
                    </p>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <!-- Réponses de l'IA -->

                    <div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-night-600">
                                Réponses de l'assistant
                            </span>

                            <span class="text-sm font-semibold text-night-900">
                                {{ usage.ai_messages?.used ?? 0 }}
                                <template v-if="!usage.ai_messages?.unlimited">
                                    / {{ usage.ai_messages?.limit }}
                                </template>
                                <template v-else>
                                    <span class="text-night-400">illimité</span>
                                </template>
                            </span>
                        </div>

                        <div
                            v-if="!usage.ai_messages?.unlimited"
                            class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-canvas-sunken"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="quotaBar(usage.ai_messages).tone"
                                :style="{ width: quotaBar(usage.ai_messages).width }"
                            ></div>
                        </div>

                        <p
                            v-if="usage.ai_messages?.ratio >= 1"
                            class="mt-2 text-xs font-medium text-rose-600"
                        >
                            Plafond atteint : les demandes partent vers vos
                            agents jusqu'au 1er du mois.
                        </p>
                    </div>

                    <!-- Appels -->

                    <div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-night-600">
                                Appels vocaux
                            </span>

                            <span class="text-sm font-semibold text-night-900">
                                <template v-if="usage.voice_calls?.blocked">
                                    <span class="text-night-400">désactivés</span>
                                </template>
                                <template v-else>
                                    {{ usage.voice_calls?.used ?? 0 }}
                                    <template v-if="!usage.voice_calls?.unlimited">
                                        / {{ usage.voice_calls?.limit }}
                                    </template>
                                </template>
                            </span>
                        </div>

                        <div
                            v-if="
                                !usage.voice_calls?.unlimited &&
                                !usage.voice_calls?.blocked
                            "
                            class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-canvas-sunken"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="quotaBar(usage.voice_calls).tone"
                                :style="{ width: quotaBar(usage.voice_calls).width }"
                            ></div>
                        </div>

                        <p class="mt-2 text-xs text-night-400">
                            {{ usage.voice_minutes ?? 0 }} minute(s) ce mois-ci
                        </p>
                    </div>
                </div>

                <p class="mt-5 text-xs leading-relaxed text-night-400">
                    Le coût estimé correspond aux jetons consommés et aux
                    minutes d'appel. Il sert de repère, pas de facture.
                </p>
            </section>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <!-- Niveau -->

                <section
                    class="rounded-2xl border border-line bg-white p-6 shadow-lift"
                >
                    <h2 class="text-sm font-semibold text-night-900">
                        Niveau d'autonomie
                    </h2>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <button
                            v-for="level in levels"
                            :key="level.value"
                            type="button"
                            class="rounded-xl border p-4 text-left transition"
                            :class="
                                form.level === level.value
                                    ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-300'
                                    : 'border-line hover:border-line-strong hover:bg-canvas-sunken'
                            "
                            @click="form.level = level.value"
                        >
                            <span
                                class="inline-block rounded-md px-2 py-0.5 text-xs font-semibold"
                                :class="level.tone"
                            >
                                {{ level.name }}
                            </span>

                            <p class="mt-2 text-sm font-medium text-night-800">
                                {{ level.summary }}
                            </p>
                        </button>
                    </div>

                    <p
                        class="mt-4 rounded-xl bg-canvas-sunken px-4 py-3 text-sm text-night-500"
                    >
                        {{ currentLevel.detail }}
                    </p>
                </section>

                <!-- Actions autorisées -->

                <section
                    class="rounded-2xl border border-line bg-white p-6 shadow-lift"
                >
                    <h2 class="text-sm font-semibold text-night-900">
                        Actions autorisées
                    </h2>
                    <p class="mt-1 text-sm text-night-400">
                        Une action décochée est inaccessible à l'IA, quel que
                        soit le niveau choisi.
                    </p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <h3
                                class="text-xs font-semibold uppercase tracking-wide text-night-400"
                            >
                                Consultation
                            </h3>

                            <div class="mt-2 space-y-2">
                                <label
                                    v-for="tool in readTools"
                                    :key="tool.name"
                                    class="flex items-start gap-3 rounded-xl border border-line px-4 py-3 transition"
                                    :class="
                                        tool.included
                                            ? 'cursor-pointer hover:bg-canvas-sunken'
                                            : 'cursor-not-allowed bg-canvas-sunken opacity-60'
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        :disabled="!tool.included"
                                        class="mt-0.5 h-4 w-4 rounded border-line-strong text-brand-500 focus:ring-brand-400"
                                        :checked="
                                            form.allowed_actions.includes(
                                                tool.name,
                                            )
                                        "
                                        @change="toggleTool(tool.name)"
                                    />
                                    <span class="min-w-0">
                                        <span
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-mono text-sm text-night-800"
                                            >
                                                {{ tool.name }}
                                            </span>
                                            <span
                                                v-if="!tool.included"
                                                class="rounded-md bg-night-100 px-1.5 py-0.5 text-[11px] font-semibold text-night-600"
                                            >
                                                hors formule
                                            </span>
                                        </span>
                                        <span
                                            class="mt-0.5 block text-xs leading-relaxed text-night-400"
                                        >
                                            {{ tool.description }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <h3
                                class="text-xs font-semibold uppercase tracking-wide text-night-400"
                            >
                                Action
                            </h3>

                            <div class="mt-2 space-y-2">
                                <label
                                    v-for="tool in writeTools"
                                    :key="tool.name"
                                    class="flex items-start gap-3 rounded-xl border border-line px-4 py-3 transition"
                                    :class="
                                        tool.included
                                            ? 'cursor-pointer hover:bg-canvas-sunken'
                                            : 'cursor-not-allowed bg-canvas-sunken opacity-60'
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        :disabled="!tool.included"
                                        class="mt-0.5 h-4 w-4 rounded border-line-strong text-brand-500 focus:ring-brand-400"
                                        :checked="
                                            form.allowed_actions.includes(
                                                tool.name,
                                            )
                                        "
                                        @change="toggleTool(tool.name)"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-mono text-sm text-night-800"
                                            >
                                                {{ tool.name }}
                                            </span>
                                            <span
                                                v-if="
                                                    tool.enabled &&
                                                    tool.decision === 'approve'
                                                "
                                                class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800"
                                            >
                                                soumis à validation
                                            </span>
                                            <span
                                                v-if="!tool.included"
                                                class="rounded-md bg-night-100 px-1.5 py-0.5 text-[11px] font-semibold text-night-600"
                                            >
                                                hors formule
                                            </span>
                                        </span>
                                        <span
                                            class="mt-0.5 block text-xs leading-relaxed text-night-400"
                                        >
                                            {{ tool.description }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Identité -->

                <section
                    class="rounded-2xl border border-line bg-white p-6 shadow-lift"
                >
                    <h2 class="text-sm font-semibold text-night-900">
                        Identité de l'assistant
                    </h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="block text-xs font-medium text-night-500"
                                >Nom de l'assistant</label
                            >
                            <input
                                v-model="form.persona"
                                type="text"
                                placeholder="Awa, assistante du service client"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p class="mt-1 text-xs text-night-400">
                                Le nom sous lequel l'assistant se présente
                                à vos clients, à l'écrit comme au téléphone.
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-medium text-night-500"
                                >Nom commercial</label
                            >
                            <input
                                v-model="form.business_name"
                                type="text"
                                placeholder="MAKOR Telecom"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p class="mt-1 text-xs text-night-400">
                                Le nom de l'entreprise tel que vos clients
                                le connaissent, s'il diffère du nom
                                enregistré.
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label
                                class="block text-xs font-medium text-night-500"
                                >Manière de s'exprimer</label
                            >
                            <input
                                v-model="form.tone"
                                type="text"
                                placeholder="professionnel, clair et chaleureux"
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p class="mt-1 text-xs text-night-400">
                                Décrivez en quelques mots le ton que doit
                                adopter l'assistant. Par exemple
                                « bref et direct », « chaleureux, tutoiement »
                                ou « très formel, vouvoiement ». Le changement
                                s'applique dès le message suivant.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Réglages avancés -->

                <section
                    class="rounded-2xl border border-line bg-white shadow-lift"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-6 py-4 text-left"
                        @click="showAdvanced = !showAdvanced"
                    >
                        <span class="text-sm font-semibold text-night-900"
                            >Réglages avancés</span
                        >
                        <span class="text-sm text-night-400">
                            {{ showAdvanced ? "Masquer" : "Afficher" }}
                        </span>
                    </button>

                    <div
                        v-show="showAdvanced"
                        class="space-y-4 border-t border-line px-6 py-5"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    class="block text-xs font-medium text-night-500"
                                >
                                    Seuil de confiance
                                </label>
                                <input
                                    v-model="form.confidence_threshold"
                                    type="number"
                                    step="0.05"
                                    min="0"
                                    max="1"
                                    class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                                />
                                <p class="mt-1 text-xs text-night-400">
                                    En dessous, l'IA passe la main à un
                                    conseiller.
                                </p>
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-medium text-night-500"
                                >
                                    Clôture automatique (heures)
                                </label>
                                <input
                                    v-model="form.auto_close_after_hours"
                                    type="number"
                                    min="0"
                                    max="720"
                                    class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                                />
                                <p class="mt-1 text-xs text-night-400">
                                    Délai avant fermeture d'un ticket résolu.
                                    0 désactive.
                                </p>
                            </div>
                        </div>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-line px-4 py-3"
                        >
                            <input
                                v-model="form.escalate_on_negative_sentiment"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-line-strong text-brand-500 focus:ring-brand-400"
                            />
                            <span>
                                <span
                                    class="block text-sm font-medium text-night-800"
                                >
                                    Transférer les clients mécontents
                                </span>
                                <span class="mt-0.5 block text-xs text-night-400">
                                    Dès qu'un agacement est détecté, un
                                    conseiller prend le relais.
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Enregistrement -->

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
        </div>
    </AuthenticatedLayout>
</template>
