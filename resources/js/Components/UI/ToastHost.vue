<script setup>
import { ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

/*
|--------------------------------------------------------------------------
| Notifications éphémères
|--------------------------------------------------------------------------
|
| Les contrôleurs renvoient déjà ->with('success', ...) après chaque action.
| Ce composant transforme ces messages flash en bandeaux temporaires, pour
| que l'agent voie que son enregistrement a abouti sans quitter des yeux la
| zone où il travaillait.
|
*/

const page = usePage();

const toasts = ref([]);

let sequence = 0;

const push = (type, message) => {
    const id = ++sequence;

    toasts.value.push({ id, type, message });

    setTimeout(() => dismiss(id), 5200);
};

const dismiss = (id) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
};

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) {
            return;
        }

        if (flash.success) {
            push("success", flash.success);
        }

        if (flash.error) {
            push("error", flash.error);
        }

        if (flash.warning) {
            push("warning", flash.warning);
        }
    },
    { immediate: true, deep: true }
);

const styles = {
    success: {
        ring: "ring-emerald-200",
        bar: "bg-emerald-500",
        icon: "✓",
        iconClass: "bg-emerald-50 text-emerald-700",
    },
    error: {
        ring: "ring-rose-200",
        bar: "bg-rose-500",
        icon: "!",
        iconClass: "bg-rose-50 text-rose-700",
    },
    warning: {
        ring: "ring-amber-200",
        bar: "bg-amber-500",
        icon: "!",
        iconClass: "bg-amber-50 text-amber-700",
    },
};
</script>

<template>
    <div
        class="pointer-events-none fixed bottom-5 right-5 z-50 flex w-[min(22rem,calc(100vw-2.5rem))] flex-col gap-2"
        role="status"
        aria-live="polite"
    >
        <TransitionGroup
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-x-6 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-200 ease-in absolute"
            leave-from-class="opacity-100"
            leave-to-class="translate-x-6 opacity-0"
            move-class="transition duration-200"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto relative flex items-start gap-3 overflow-hidden rounded-xl bg-canvas-raised p-3.5 pl-4 shadow-pop ring-1"
                :class="styles[toast.type].ring"
            >
                <span
                    class="absolute inset-y-0 left-0 w-1"
                    :class="styles[toast.type].bar"
                ></span>

                <span
                    class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                    :class="styles[toast.type].iconClass"
                >
                    {{ styles[toast.type].icon }}
                </span>

                <p class="flex-1 text-sm leading-6 text-night-700">
                    {{ toast.message }}
                </p>

                <button
                    type="button"
                    class="shrink-0 rounded-md p-1 text-night-300 transition hover:bg-canvas-sunken hover:text-night-600"
                    aria-label="Fermer"
                    @click="dismiss(toast.id)"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                        />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
