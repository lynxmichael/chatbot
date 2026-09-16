<script setup>
import { computed } from "vue";
import { resolveToken } from "@/lib/tokens";

/*
|--------------------------------------------------------------------------
| Étiquette d'état
|--------------------------------------------------------------------------
|
| Statut, priorité, canal ou type d'appel : une seule étiquette, alimentée
| par la table de jetons partagée.
|
*/

const props = defineProps({
    kind: {
        type: String,
        default: "status",
    },

    value: {
        type: [String, Number],
        default: null,
    },

    dense: {
        type: Boolean,
        default: false,
    },
});

const token = computed(() => resolveToken(props.kind, props.value));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full font-medium ring-1 ring-inset transition-colors"
        :class="[
            token.classes,
            dense ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
        ]"
    >
        <span
            class="h-1.5 w-1.5 rounded-full"
            :class="token.dotClass"
        ></span>

        {{ token.label }}
    </span>
</template>
