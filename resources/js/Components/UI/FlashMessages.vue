<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

/*
 * Messages flash partagés par HandleInertiaRequests.
 * Un seul composant évite de recopier le même bloc sur chaque page.
 */

const page = usePage();

const messages = computed(() =>
    [
        { key: "success", tone: "emerald" },
        { key: "warning", tone: "amber" },
        { key: "error", tone: "rose" },
    ]
        .map((entry) => ({
            ...entry,
            text: page.props.flash?.[entry.key] ?? null,
        }))
        .filter((entry) => entry.text),
);

const classes = {
    emerald: "border-emerald-200 bg-emerald-50 text-emerald-800",
    amber: "border-amber-200 bg-amber-50 text-amber-800",
    rose: "border-rose-200 bg-rose-50 text-rose-800",
};
</script>

<template>
    <div v-if="messages.length" class="space-y-2">
        <p
            v-for="message in messages"
            :key="message.key"
            class="rounded-xl border px-4 py-3 text-sm"
            :class="classes[message.tone]"
        >
            {{ message.text }}
        </p>
    </div>
</template>
