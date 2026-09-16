<script setup>
import { onBeforeUnmount, ref, watch } from "vue";

/*
|--------------------------------------------------------------------------
| Compteur animé
|--------------------------------------------------------------------------
|
| Le chiffre glisse de son ancienne valeur vers la nouvelle. C'est ce qui
| rend un rafraîchissement visible sans avoir à recharger la page : l'œil
| est attiré par la seule tuile qui a bougé.
|
*/

const props = defineProps({
    value: {
        type: Number,
        default: 0,
    },

    duration: {
        type: Number,
        default: 700,
    },

    format: {
        type: Function,
        default: null,
    },
});

const displayed = ref(props.value);

let frame = null;

const prefersReducedMotion = () =>
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const easeOut = (t) => 1 - Math.pow(1 - t, 3);

const animateTo = (target) => {
    const from = displayed.value;
    const delta = target - from;

    if (delta === 0) {
        return;
    }

    if (prefersReducedMotion()) {
        displayed.value = target;
        return;
    }

    const started = performance.now();

    const step = (now) => {
        const progress = Math.min((now - started) / props.duration, 1);

        displayed.value = Math.round(from + delta * easeOut(progress));

        if (progress < 1) {
            frame = requestAnimationFrame(step);
        }
    };

    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(step);
};

watch(() => props.value, animateTo);

onBeforeUnmount(() => cancelAnimationFrame(frame));
</script>

<template>
    <span class="tabular">
        {{ format ? format(displayed) : displayed }}
    </span>
</template>
