<script setup>
/*
|--------------------------------------------------------------------------
| En-tête de page
|--------------------------------------------------------------------------
|
| Même hauteur, même typographie et même place pour les actions sur
| toutes les pages : c'est ce qui donne l'impression d'un produit
| cohérent plutôt que d'un assemblage d'écrans.
|
*/

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight text-night-900">
                {{ title }}
            </h1>

            <p v-if="description" class="mt-1 text-sm text-night-400">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
