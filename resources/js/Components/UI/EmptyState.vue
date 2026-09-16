<script setup>
/*
|--------------------------------------------------------------------------
| État vide
|--------------------------------------------------------------------------
|
| Un écran vide est une invitation à agir, pas un message d'excuse.
|
*/

defineProps({
    icon: {
        type: String,
        default: "—",
    },

    title: {
        type: String,
        required: true,
    },

    description: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div
            class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-canvas-sunken text-xl"
        >
            {{ icon }}
        </div>

        <p class="text-sm font-semibold text-night-700">
            {{ title }}
        </p>

        <p
            v-if="description"
            class="mt-1 max-w-sm text-sm text-night-400"
        >
            {{ description }}
        </p>

        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
