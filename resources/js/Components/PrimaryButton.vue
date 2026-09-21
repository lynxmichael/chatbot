<template>
    <!--
        Bouton principal des écrans de connexion. Aux couleurs du
        produit, et sans les majuscules espacées de Breeze : « SE
        CONNECTER » crie, « Se connecter » invite.
    -->
    <button
        class="inline-flex items-center justify-center rounded-xl border border-transparent bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition duration-150 ease-in-out hover:bg-brand-600 focus:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2 active:bg-brand-700 disabled:opacity-50"
    >
        <slot />
    </button>
</template>
