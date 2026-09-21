<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
  calls: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
});

const search = ref(props.filters?.search || "");
const type = ref(props.filters?.type || "");
const status = ref(props.filters?.status || "");

const hasFilters = computed(() => {
  return search.value || type.value || status.value;
});

let searchTimeout = null;

watch([search, type, status], () => {
  clearTimeout(searchTimeout);

  searchTimeout = setTimeout(() => {
    router.get(
      route("calls.index"),
      {
        search: search.value || undefined,
        type: type.value || undefined,
        status: status.value || undefined,
      },
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      },
    );
  }, 350);
});

const resetFilters = () => {
  search.value = "";
  type.value = "";
  status.value = "";
};

const typeLabel = (value) => {
  const labels = {
    incoming: "Entrant",
    outgoing: "Sortant",
  };

  return labels[value] || value;
};

const statusLabel = (value) => {
  const labels = {
    ringing: 'Sonne',
    completed: 'Terminé',
    answered: "Répondu",
    missed: "Manqué",
    busy: "Occupé",
    failed: "Échec",
    cancelled: "Annulé",
  };

  return labels[value] || value;
};

const typeClass = (value) => {
  return value === "incoming"
    ? "bg-blue-100 text-blue-700"
    : "bg-purple-100 text-purple-700";
};

const statusClass = (value) => {
  const classes = {
    answered: "bg-emerald-100 text-emerald-700",
    missed: "bg-rose-100 text-rose-700",
    busy: "bg-amber-100 text-amber-700",
    failed: "bg-rose-100 text-rose-700",
    cancelled: "bg-canvas-sunken text-night-600",
  };

  return classes[value] || "bg-canvas-sunken text-night-600";
};

const formatDuration = (seconds) => {
  const total = Number(seconds || 0);

  const minutes = Math.floor(total / 60);
  const remainingSeconds = total % 60;

  if (minutes === 0) {
    return `${remainingSeconds}s`;
  }

  return `${minutes}m ${String(remainingSeconds).padStart(2, "0")}s`;
};

const formatDate = (date) => {
  if (!date) {
    return "-";
  }

  return new Intl.DateTimeFormat("fr-FR", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(new Date(date));
};

const clientName = (client) => {
  if (!client) {
    return "Client inconnu";
  }

  return `${client.first_name || ""} ${client.last_name || ""}`.trim();
};
</script>

<template>
  <Head title="Appels" />

  <AuthenticatedLayout>
    <!-- En-tête -->
    <div class="pt-8">
      <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div
          class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <h1 class="text-2xl font-bold text-night-900">Gestion des appels</h1>

            <p class="mt-1 text-sm text-night-400">
              Historique et suivi des appels clients
            </p>
          </div>

          <Link
            :href="route('calls.create')"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600"
          >
            <svg
              class="mr-2 h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 5a2 2 0 012-2h3.28a2 2 0 011.94 1.515l.82 3.28a2 2 0 01-.57 1.99l-2.12 2.12a16.001 16.001 0 006.36 6.36l2.12-2.12a2 2 0 011.99-.57l3.28.82A2 2 0 0121 18.72V22a2 2 0 01-2 2C9.611 24 0 14.389 0 2a2 2 0 012-2h3z"
              />
            </svg>

            Nouvel appel
          </Link>
        </div>
      </div>
    </div>

    <!-- Contenu -->
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
      <!-- Filtres -->
      <div
        class="mb-6 rounded-xl border border-line bg-white p-5 shadow-sm"
      >
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <!-- Recherche -->
          <div class="md:col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-night-600">
              Rechercher
            </label>

            <div class="relative">
              <svg
                class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-night-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="m21 21-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z"
                />
              </svg>

              <input
                v-model="search"
                type="text"
                placeholder="Client, téléphone, motif..."
                class="w-full rounded-lg border border-line-strong py-2.5 pl-10 pr-3 text-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
              />
            </div>
          </div>

          <!-- Type -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-night-600">
              Type
            </label>

            <select
              v-model="type"
              class="w-full rounded-lg border border-line-strong px-3 py-2.5 text-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
            >
              <option value="">Tous les appels</option>
              <option value="incoming">Entrants</option>
              <option value="outgoing">Sortants</option>
            </select>
          </div>

          <!-- Statut -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-night-600">
              Statut
            </label>

            <select
              v-model="status"
              class="w-full rounded-lg border border-line-strong px-3 py-2.5 text-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-200"
            >
              <option value="">Tous les statuts</option>
              <option value="completed">Terminé</option>
                            <option value="answered">Répondu</option>
              <option value="missed">Manqué</option>
              <option value="busy">Occupé</option>
              <option value="failed">Échec</option>
              <option value="cancelled">Annulé</option>
            </select>
          </div>
        </div>

        <div v-if="hasFilters" class="mt-4 flex justify-end">
          <button
            type="button"
            class="text-sm font-medium text-brand-600 hover:text-brand-800"
            @click="resetFilters"
          >
            Réinitialiser les filtres
          </button>
        </div>
      </div>

      <!-- Tableau -->
      <div
        class="overflow-hidden rounded-xl border border-line bg-white shadow-sm"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-line">
            <thead class="bg-canvas-sunken">
              <tr>
                <th
                  class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                >
                  Client
                </th>

                <th
                  class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                >
                  Type
                </th>

                <th
                  class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                >
                  Statut
                </th>

                <th
                  class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                >
                  Agent
                </th>

                <th
                  class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                >
                  Durée
                </th>

                <th
                  class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-night-400"
                >
                  Date
                </th>

                <th
                  class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-night-400"
                >
                  Action
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-line bg-white">
              <tr
                v-for="call in calls.data"
                :key="call.id"
                class="transition hover:bg-canvas-sunken"
              >
                <!-- Client -->
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="flex items-center">
                    <div
                      class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-700"
                    >
                      {{ clientName(call.client).charAt(0).toUpperCase() }}
                    </div>

                    <div class="ml-3">
                      <div class="text-sm font-semibold text-night-900">
                        {{ clientName(call.client) }}
                      </div>

                      <div class="text-sm text-night-400">
                        {{ call.phone }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Type -->
                <td class="whitespace-nowrap px-6 py-4">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="typeClass(call.type)"
                  >
                    {{ typeLabel(call.type) }}
                  </span>
                </td>

                <!-- Statut -->
                <td class="whitespace-nowrap px-6 py-4">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="statusClass(call.status)"
                  >
                    {{ statusLabel(call.status) }}
                  </span>
                </td>

                <!-- Agent -->
                <td class="whitespace-nowrap px-6 py-4 text-sm text-night-600">
                  {{ call.user?.name || "Non attribué" }}
                </td>

                <!-- Durée -->
                <td class="whitespace-nowrap px-6 py-4 text-sm text-night-600">
                  {{ formatDuration(call.duration) }}
                </td>

                <!-- Date -->
                <td class="whitespace-nowrap px-6 py-4 text-sm text-night-500">
                  {{ formatDate(call.started_at) }}
                </td>

                <!-- Action -->
                <td class="whitespace-nowrap px-6 py-4 text-right">
                  <Link
                    :href="route('calls.show', call.id)"
                    class="font-medium text-brand-600 hover:text-brand-800"
                  >
                    Voir
                  </Link>
                </td>
              </tr>

              <!-- Aucun appel -->
              <tr v-if="calls.data.length === 0">
                <td colspan="7" class="px-6 py-12 text-center">
                  <div class="text-night-300">
                    <svg
                      class="mx-auto h-12 w-12"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M3 5a2 2 0 012-2h3.28a2 2 0 011.94 1.515l.82 3.28a2 2 0 01-.57 1.99l-2.12 2.12a16.001 16.001 0 006.36 6.36l2.12-2.12a2 2 0 011.99-.57l3.28.82A2 2 0 0121 18.72V22a2 2 0 01-2 2C9.611 24 0 14.389 0 2a2 2 0 012-2h3z"
                      />
                    </svg>

                    <p class="mt-3 text-sm font-medium text-night-500">
                      Aucun appel trouvé
                    </p>

                    <p class="mt-1 text-sm text-night-300">
                      Commencez par enregistrer un nouvel appel.
                    </p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div
          v-if="calls.links && calls.links.length > 3"
          class="border-t border-line px-6 py-4"
        >
          <div class="flex flex-wrap items-center justify-center gap-1">
            <template v-for="(link, index) in calls.links" :key="index">
              <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="rounded-lg px-3 py-2 text-sm transition"
                :class="
                  link.active
                    ? 'bg-brand-500 font-semibold text-white'
                    : 'text-night-500 hover:bg-canvas-sunken'
                "
                v-html="link.label"
              />

              <span
                v-else
                class="rounded-lg px-3 py-2 text-sm text-night-200"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
