<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

import CountUp from "@/Components/UI/CountUp.vue";
import Sparkline from "@/Components/UI/Sparkline.vue";

/*
|--------------------------------------------------------------------------
| Tuile de métrique
|--------------------------------------------------------------------------
|
| Un chiffre, ce qu'il vaut aujourd'hui, et sa forme sur quatorze jours.
| La bordure porte la couleur de l'état plutôt qu'une ombre : sur un tableau
| de bord dense, huit ombres identiques ne hiérarchisent plus rien.
|
*/

const props = defineProps({
    label: {
        type: String,
        required: true,
    },

    value: {
        type: Number,
        default: 0,
    },

    today: {
        type: Number,
        default: null,
    },

    todayLabel: {
        type: String,
        default: "aujourd'hui",
    },

    series: {
        type: Array,
        default: () => [],
    },

    tone: {
        type: String,
        default: "brand",
        validator: (value) =>
            ["brand", "sky", "amber", "emerald", "rose", "night"].includes(value),
    },

    href: {
        type: String,
        default: null,
    },

    format: {
        type: Function,
        default: null,
    },
});

const toneMap = {
    brand: {
        accent: "bg-brand-500",
        spark: "text-brand-500",
        chip: "bg-brand-50 text-brand-700",
    },
    sky: {
        accent: "bg-sky-500",
        spark: "text-sky-500",
        chip: "bg-sky-50 text-sky-700",
    },
    amber: {
        accent: "bg-amber-500",
        spark: "text-amber-500",
        chip: "bg-amber-50 text-amber-700",
    },
    emerald: {
        accent: "bg-emerald-500",
        spark: "text-emerald-500",
        chip: "bg-emerald-50 text-emerald-700",
    },
    rose: {
        accent: "bg-rose-500",
        spark: "text-rose-500",
        chip: "bg-rose-50 text-rose-700",
    },
    night: {
        accent: "bg-night-500",
        spark: "text-night-400",
        chip: "bg-night-50 text-night-600",
    },
};

const colors = computed(() => toneMap[props.tone] ?? toneMap.brand);

const component = computed(() => (props.href ? Link : "div"));
</script>

<template>
    <component
        :is="component"
        :href="href || undefined"
        class="group relative block overflow-hidden rounded-2xl border border-line bg-canvas-raised p-5 transition duration-200"
        :class="
            href
                ? 'hover:-translate-y-0.5 hover:border-line-strong hover:shadow-lift'
                : ''
        "
    >
        <!-- Filet de couleur : porte l'état, ne décore pas -->
        <span
            class="absolute inset-y-0 left-0 w-[3px] origin-top scale-y-100 transition-transform duration-300"
            :class="colors.accent"
        ></span>

        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-night-400">
                    {{ label }}
                </p>

                <p
                    class="mt-1.5 font-mono text-3xl font-semibold leading-none text-night-800"
                >
                    <CountUp :value="value" :format="format" />
                </p>
            </div>

            <Sparkline
                v-if="series.length > 1"
                :values="series"
                :color-class="colors.spark"
                :width="96"
                :height="32"
                class="shrink-0"
            />
        </div>

        <div class="mt-4 flex items-center gap-2">
            <span
                v-if="today !== null"
                class="inline-flex items-center rounded-md px-2 py-0.5 font-mono text-xs font-medium"
                :class="colors.chip"
            >
                +{{ today }}
            </span>

            <span v-if="today !== null" class="text-xs text-night-400">
                {{ todayLabel }}
            </span>

            <slot name="footer" />
        </div>
    </component>
</template>
