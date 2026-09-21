<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
  call: {
    type: Object,
    required: true,
  },
});

const typeLabel = (value) =>
  ({
    incoming: "Entrant",
    outgoing: "Sortant",
  })[value] || value;

const statusLabel = (value) =>
  ({
    ringing: 'Sonne',
    completed: 'Terminé',
    answered: "Répondu",
    missed: "Manqué",
    busy: "Occupé",
    failed: "Échec",
    cancelled: "Annulé",
  })[value] || value;

const formatDuration = (seconds) => {
  const total = Number(seconds || 0);
  const minutes = Math.floor(total / 60);
  const secondsLeft = total % 60;

  return `${minutes} min ${String(secondsLeft).padStart(2, "0")} sec`;
};

const formatDate = (date) => {
  if (!date) return "-";

  return new Intl.DateTimeFormat("fr-FR", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(date));
};

const clientName = () => {
  if (!props.call.client) return "Client inconnu";

  return `${props.call.client.first_name || ""} ${props.call.client.last_name || ""}`.trim();
};

const deleteCall = () => {
  if (!confirm("Voulez-vous vraiment supprimer cet appel ?")) {
    return;
  }

  router.delete(route("calls.destroy", props.call.id));
};
</script>

<template>
  <Head title="Détail de l'appel" />

  <AuthenticatedLayout>
    <div class="pt-8">
      <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
          <div>
            <Link
              :href="route('calls.index')"
              class="text-sm text-brand-600 hover:text-brand-800"
            >
              ← Retour aux appels
            </Link>

            <h1 class="mt-2 text-2xl font-bold text-night-900">
              Détail de l'appel
            </h1>
          </div>

          <div class="flex gap-2">
            <Link
              :href="route('calls.edit', props.call.id)"
              class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600"
            >
              Modifier
            </Link>

            <button
              type="button"
              class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
              @click="deleteCall"
            >
              Supprimer
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <!-- Client -->
      <div class="rounded-xl border bg-white p-6 shadow-sm">
        <h2 class="mb-5 text-lg font-semibold text-night-900">Client</h2>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
          <div>
            <p class="text-sm text-night-400">Nom</p>
            <p class="mt-1 font-semibold text-night-900">
              {{ clientName() }}
            </p>
          </div>

          <div>
            <p class="text-sm text-night-400">Téléphone</p>
            <p class="mt-1 font-semibold text-night-900">
              {{ props.call.phone }}
            </p>
          </div>

          <div>
            <p class="text-sm text-night-400">Email</p>
            <p class="mt-1 text-night-900">
              {{ props.call.client?.email || "-" }}
            </p>
          </div>
        </div>
      </div>

      <!-- Appel -->
      <div class="rounded-xl border bg-white p-6 shadow-sm">
        <h2 class="mb-5 text-lg font-semibold text-night-900">
          Informations de l'appel
        </h2>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
          <div>
            <p class="text-sm text-night-400">Type</p>
            <p class="mt-1 font-semibold text-night-900">
              {{ typeLabel(props.call.type) }}
            </p>
          </div>

          <div>
            <p class="text-sm text-night-400">Statut</p>
            <p class="mt-1 font-semibold text-night-900">
              {{ statusLabel(props.call.status) }}
            </p>
          </div>

          <div>
            <p class="text-sm text-night-400">Durée</p>
            <p class="mt-1 font-semibold text-night-900">
              {{ formatDuration(props.call.duration) }}
            </p>
          </div>

          <div>
            <p class="text-sm text-night-400">Agent</p>
            <p class="mt-1 font-semibold text-night-900">
              {{ props.call.user?.name || "Non attribué" }}
            </p>
          </div>

          <div>
            <p class="text-sm text-night-400">Début</p>
            <p class="mt-1 text-night-900">
              {{ formatDate(props.call.started_at) }}
            </p>
          </div>

          <div>
            <p class="text-sm text-night-400">Fin</p>
            <p class="mt-1 text-night-900">
              {{ formatDate(props.call.ended_at) }}
            </p>
          </div>

          <div class="md:col-span-2">
            <p class="text-sm text-night-400">Motif</p>
            <p class="mt-1 text-night-900">
              {{ props.call.reason || "-" }}
            </p>
          </div>

          <div class="md:col-span-2">
            <p class="text-sm text-night-400">Notes</p>
            <p class="mt-1 whitespace-pre-line text-night-900">
              {{ props.call.notes || "-" }}
            </p>
          </div>
        </div>
      </div>

      <!-- Conversation -->
      <div
        v-if="props.call.conversation"
        class="rounded-xl border bg-white p-6 shadow-sm"
      >
        <h2 class="mb-3 text-lg font-semibold text-night-900">
          Conversation associée
        </h2>

        <Link
          :href="route('conversations.show', props.call.conversation.id)"
          class="font-medium text-brand-600 hover:text-brand-800"
        >
          #{{ props.call.conversation.id }} —
          {{ props.call.conversation.subject || "Sans sujet" }}
        </Link>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
