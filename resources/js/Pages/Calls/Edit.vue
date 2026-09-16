<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
  call: {
    type: Object,
    required: true,
  },
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

const toDateTimeLocal = (value) => {
  if (!value) return "";

  const date = new Date(value);

  const pad = (number) => String(number).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const form = useForm({
  client_id: props.call.client_id || "",
  conversation_id: props.call.conversation_id || "",
  user_id: props.call.user_id || "",
  type: props.call.type || "incoming",
  status: props.call.status || "answered",
  phone: props.call.phone || "",
  duration: props.call.duration || 0,
  reason: props.call.reason || "",
  notes: props.call.notes || "",
  started_at: toDateTimeLocal(props.call.started_at),
  ended_at: toDateTimeLocal(props.call.ended_at),
});

const submit = () => {
  form.put(route("calls.update", props.call.id));
};
</script>

<template>
  <Head title="Modifier l'appel" />

  <div class="min-h-screen bg-gray-50">
    <div class="border-b bg-white">
      <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        <Link
          :href="route('calls.show', props.call.id)"
          class="text-sm text-indigo-600 hover:text-indigo-800"
        >
          ← Retour à l'appel
        </Link>

        <h1 class="mt-2 text-2xl font-bold text-gray-900">Modifier l'appel</h1>
      </div>
    </div>

    <div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
      <form class="space-y-6" @submit.prevent="submit">
        <div class="rounded-xl border bg-white p-6 shadow-sm">
          <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Client *
              </label>

              <select
                v-model="form.client_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              >
                <option value="">Sélectionner</option>

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
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Type *
              </label>

              <select
                v-model="form.type"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              >
                <option value="incoming">Entrant</option>
                <option value="outgoing">Sortant</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Statut *
              </label>

              <select
                v-model="form.status"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
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
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Agent
              </label>

              <select
                v-model="form.user_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              >
                <option value="">Non attribué</option>

                <option
                  v-for="agent in agents"
                  :key="agent.id"
                  :value="agent.id"
                >
                  {{ agent.name }} ({{ agent.role }})
                </option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Conversation
              </label>

              <select
                v-model="form.conversation_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              >
                <option value="">Aucune</option>

                <option
                  v-for="conversation in conversations"
                  :key="conversation.id"
                  :value="conversation.id"
                >
                  #{{ conversation.id }} —
                  {{ conversation.subject || "Sans sujet" }}
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
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Début
              </label>

              <input
                v-model="form.started_at"
                type="datetime-local"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Fin
              </label>

              <input
                v-model="form.ended_at"
                type="datetime-local"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              />
            </div>

            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700">
                Notes
              </label>

              <textarea
                v-model="form.notes"
                rows="5"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"
              ></textarea>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link
            :href="route('calls.show', props.call.id)"
            class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Annuler
          </Link>

          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {{
              form.processing
                ? "Enregistrement..."
                : "Enregistrer les modifications"
            }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
