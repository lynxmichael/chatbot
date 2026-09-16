<script setup>
/*
|--------------------------------------------------------------------------
| Témoin de fraîcheur
|--------------------------------------------------------------------------
|
| Le seul mouvement permanent de l'interface. Il indique que les données
| se rafraîchissent toutes seules, et s'arrête quand ce n'est plus le cas.
|
*/

defineProps({
    active: {
        type: Boolean,
        default: true,
    },

    label: {
        type: String,
        default: "En direct",
    },
});
</script>

<template>
    <span
        class="inline-flex items-center gap-2 text-xs font-medium"
        :class="active ? 'text-emerald-700' : 'text-night-400'"
    >
        <span class="relative flex h-2 w-2">
            <span
                v-if="active"
                class="absolute inline-flex h-full w-full animate-pulse-ring rounded-full bg-emerald-400"
            ></span>

            <span
                class="relative inline-flex h-2 w-2 rounded-full"
                :class="active ? 'bg-emerald-500' : 'bg-night-300'"
            ></span>
        </span>

        {{ label }}
    </span>
</template>
