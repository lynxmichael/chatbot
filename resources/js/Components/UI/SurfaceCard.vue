<script setup>
/*
|--------------------------------------------------------------------------
| Surface
|--------------------------------------------------------------------------
|
| Bloc blanc arrondi utilisé partout. « flush » retire le rembourrage
| pour les contenus qui gèrent le leur, comme les tableaux.
|
*/

defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    flush: { type: Boolean, default: false },
});
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-line bg-white shadow-lift"
    >
        <header
            v-if="title || $slots.header"
            class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4"
        >
            <div class="min-w-0">
                <h2 v-if="title" class="text-sm font-semibold text-night-900">
                    {{ title }}
                </h2>

                <p v-if="description" class="mt-0.5 text-xs text-night-400">
                    {{ description }}
                </p>
            </div>

            <slot name="header" />
        </header>

        <div :class="flush ? '' : 'p-6'">
            <slot />
        </div>
    </section>
</template>
