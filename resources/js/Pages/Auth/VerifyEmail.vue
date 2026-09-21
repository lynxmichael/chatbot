<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Vérification de l'email" />

        <div class="mb-4 text-sm text-night-500">
            Merci pour votre inscription. Avant de commencer, confirmez votre
            adresse email en cliquant sur le lien que nous venons de vous
            envoyer. Rien reçu ? Nous pouvons vous en renvoyer un.
        </div>

        <div
            class="mb-4 text-sm font-medium text-emerald-600"
            v-if="verificationLinkSent"
        >
            Un nouveau lien vient d'être envoyé à votre adresse email.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Renvoyer le lien
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-night-500 underline hover:text-night-900 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2"
                    >Se déconnecter</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
