<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/UI/PageHeader.vue";
import SurfaceCard from "@/Components/UI/SurfaceCard.vue";

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const submit = () => form.post(route("agents.store"));
</script>

<template>
    <Head title="Nouvel agent" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-2xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <PageHeader
                title="Nouvel agent"
                description="Les compétences et la capacité se règlent juste après la création."
            >
                <template #actions>
                    <Link
                        :href="route('agents.index')"
                        class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
                    >
                        Retour
                    </Link>
                </template>
            </PageHeader>

            <form @submit.prevent="submit">
                <SurfaceCard>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-night-500">
                                Nom complet
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-night-500">
                                Email
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p
                                v-if="form.errors.email"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Mot de passe
                            </label>
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                            <p
                                v-if="form.errors.password"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-night-500">
                                Confirmation
                            </label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="mt-1 w-full rounded-xl border-line text-sm focus:border-brand-400 focus:ring-brand-400"
                            />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-50"
                        >
                            Créer l'agent
                        </button>
                    </div>
                </SurfaceCard>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
