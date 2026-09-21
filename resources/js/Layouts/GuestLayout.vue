<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

/*
|--------------------------------------------------------------------------
| Mise en page des écrans de connexion
|--------------------------------------------------------------------------
|
| Remplace la version par défaut de Laravel : son logo, son fond gris,
| sa carte blanche centrée. Un client qui se connecte doit voir le
| produit qu'il utilise, pas le framework sur lequel il est construit.
|
*/

const page = usePage();

const appName = computed(() => page.props.app?.name ?? "AI Service Client");
</script>

<template>
    <div class="flex min-h-screen bg-canvas">
        <!-- Panneau de marque, masqué sur mobile -->

        <aside
            class="relative hidden w-[44%] flex-col justify-between overflow-hidden bg-night-800 p-12 text-white lg:flex"
        >
            <div
                class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-500/30 blur-3xl"
            ></div>
            <div
                class="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-brand-400/20 blur-3xl"
            ></div>

            <Link href="/" class="relative flex items-center gap-2.5">
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-sm font-bold"
                >
                    AI
                </span>
                <span class="font-semibold tracking-tight">{{ appName }}</span>
            </Link>

            <div class="relative">
                <p class="text-3xl font-semibold leading-snug tracking-tight">
                    Vos clients obtiennent une réponse, même quand vous dormez.
                </p>

                <p class="mt-4 max-w-sm text-sm leading-relaxed text-night-200">
                    Un assistant qui répond sur votre site et au téléphone, à
                    partir de vos propres informations.
                </p>
            </div>

            <p class="relative text-xs text-night-300">
                © {{ new Date().getFullYear() }} {{ appName }}
            </p>
        </aside>

        <!-- Formulaire -->

        <main class="flex flex-1 flex-col items-center justify-center px-6 py-12">
            <Link href="/" class="mb-8 flex items-center gap-2.5 lg:hidden">
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-sm font-bold text-white"
                >
                    AI
                </span>
                <span class="font-semibold tracking-tight text-night-900">
                    {{ appName }}
                </span>
            </Link>

            <div
                class="w-full max-w-md rounded-2xl border border-line bg-white p-8 shadow-lift"
            >
                <slot />
            </div>
        </main>
    </div>
</template>
