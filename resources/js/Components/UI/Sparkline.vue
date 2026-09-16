<script setup>
import { computed } from "vue";

/*
|--------------------------------------------------------------------------
| Courbe de tendance
|--------------------------------------------------------------------------
|
| SVG écrit à la main : pas de librairie de graphiques à installer, et la
| courbe reste lisible à 120 px de large.
|
*/

const props = defineProps({
    values: {
        type: Array,
        default: () => [],
    },

    width: {
        type: Number,
        default: 120,
    },

    height: {
        type: Number,
        default: 34,
    },

    colorClass: {
        type: String,
        default: "text-brand-500",
    },
});

const points = computed(() => {
    const values = props.values.map((value) => Number(value) || 0);

    if (values.length < 2) {
        return [];
    }

    const max = Math.max(...values);
    const min = Math.min(...values);
    const span = max - min || 1;

    const stepX = props.width / (values.length - 1);
    const padding = 3;
    const usable = props.height - padding * 2;

    return values.map((value, index) => ({
        x: index * stepX,
        y: padding + usable - ((value - min) / span) * usable,
    }));
});

const linePath = computed(() => {
    if (!points.value.length) {
        return "";
    }

    return points.value
        .map((point, index) =>
            `${index === 0 ? "M" : "L"}${point.x.toFixed(1)},${point.y.toFixed(1)}`
        )
        .join(" ");
});

const areaPath = computed(() => {
    if (!points.value.length) {
        return "";
    }

    const last = points.value[points.value.length - 1];

    return `${linePath.value} L${last.x.toFixed(1)},${props.height} L0,${props.height} Z`;
});

const lastPoint = computed(() => points.value[points.value.length - 1] ?? null);

const gradientId = computed(
    () => `spark-${Math.random().toString(36).slice(2, 9)}`
);
</script>

<template>
    <svg
        v-if="linePath"
        :viewBox="`0 0 ${width} ${height}`"
        :width="width"
        :height="height"
        preserveAspectRatio="none"
        class="overflow-visible"
        :class="colorClass"
        aria-hidden="true"
    >
        <defs>
            <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="currentColor" stop-opacity="0.22" />
                <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
            </linearGradient>
        </defs>

        <path :d="areaPath" :fill="`url(#${gradientId})`" />

        <path
            :d="linePath"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="animate-draw-line"
            style="stroke-dasharray: 1000"
        />

        <circle
            v-if="lastPoint"
            :cx="lastPoint.x"
            :cy="lastPoint.y"
            r="2.5"
            fill="currentColor"
        />
    </svg>
</template>
