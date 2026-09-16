<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
  clients: {
    type: Array,
    default: () => [],
  },
  conversations: {
    type: Array,
    default: () => [],
  },
  agents: {
    type: Array,
    default: () => [],
  },
});

const form = useForm({
  client_id: "",
  conversation_id: "",
  user_id: "",
  type: "incoming",
  status: "answered",
  phone: "",
  duration: 0,
  reason: "",
  notes: "",
  started_at: "",
  ended_at: "",
});

const submit = () => {
  form.post(route("calls.store"));
};
</script>

<template>
  <Head title="Nouvel appel" />

  <div class="min-h-screen bg-gray-50">
    <div class="border-b bg-white">
      <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
          <Link
            :href="route('calls.index')"
            class="text-gray-500 hover:text-gray-800"
          >
            ← Retour
          </Link>

          <div>
            <h1 class="text-2xl font-bold text-gray-900">Nouvel appel</h1>
            <p class="text-sm text-gray-500">Enregistrer un appel client</p>
          </div>
        </div>
      </div>
    </div>

    <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
      <form class="space-y-6" @submit.prevent="submit">
        <div class="rounded-xl border bg-white p-6 shadow-sm">
          <h2 class="mb-5 text-lg font-semibold text-gray-900">
            Informations de l'appel
          </h2>

          <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Client *
              </label>

              <select
                v-model="form.client_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              >
                <option value="">Sélectionner un client</option>

                <option
                  v-for="client in clients"
                  :key="client.id"
                  :value="client.id"
                >
                  {{ client.first_name }}
                  {{ client.last_name }}
                  — {{ client.phone || "Sans téléphone" }}
                </option>
              </select>

              <p v-if="form.errors.client_id" class="mt-1 text-sm text-red-600">
                {{ form.errors.client_id }}
              </p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Téléphone *
              </label>

              <input
                v-model="form.phone"
                type="text"
                placeholder="+225 07 XX XX XX XX"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              />

              <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">
                {{ form.errors.phone }}
              </p>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Type *
              </label>

              <select
                v-model="form.type"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              >
                <option value="incoming">Appel entrant</option>
                <option value="outgoing">Appel sortant</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Statut *
              </label>

              <select
                v-model="form.status"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              >
                <option value="answered">Répondu</option>
                <option value="missed">Manqué</option>
                <option value="busy">Occupé</option>
                <option value="failed">Échec</option>
                <option value="cancelled">Annulé</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Durée (secondes)
              </label>

              <input
                v-model.number="form.duration"
                type="number"
                min="0"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Agent
              </label>

              <select
                v-model="form.user_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              >
                <option value="">Moi-même</option>

                <option
                  v-for="agent in agents"
                  :key="agent.id"
                  :value="agent.id"
                >
                  {{ agent.name }}
                  ({{ agent.role }})
                </option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Conversation associée
              </label>

              <select
                v-model="form.conversation_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              >
                <option value="">Aucune conversation</option>

                <option
                  v-for="conversation in conversations"
                  :key="conversation.id"
                  :value="conversation.id"
                >
                  #{{ conversation.id }} —
                  {{ conversation.subject || "Sans sujet" }} —
                  {{ conversation.client?.first_name }}
                  {{ conversation.client?.last_name }}
                </option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Motif
              </label>

              <input
                v-model="form.reason"
                type="text"
                placeholder="Ex. Réclamation, demande d'information..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Début
              </label>

              <input
                v-model="form.started_at"
                type="datetime-local"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Fin
              </label>

              <input
                v-model="form.ended_at"
                type="datetime-local"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              />
            </div>

            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Notes
              </label>

              <textarea
                v-model="form.notes"
                rows="5"
                placeholder="Notes concernant l'appel..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
              ></textarea>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link
            :href="route('calls.index')"
            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Annuler
          </Link>

          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {{ form.processing ? "Enregistrement..." : "Enregistrer l’appel" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
