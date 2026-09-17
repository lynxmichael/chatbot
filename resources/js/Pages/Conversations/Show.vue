<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    conversation: {
        type: Object,
        required: true,
    },

    agents: {
        type: Array,
        default: () => [],
    },

    canModify: {
        type: Boolean,
        default: false,
    },

    isOwner: {
        type: Boolean,
        default: false,
    },

    currentUserId: {
        type: Number,
        default: null,
    },
});

/*
|--------------------------------------------------------------------------
| État local
|--------------------------------------------------------------------------
*/

const conversation = ref(props.conversation);

const agents = computed(() => props.agents ?? []);

const canModify = computed(() => props.canModify ?? false);

const isOwner = computed(() => props.isOwner ?? false);

const currentUserId = computed(() => props.currentUserId ?? null);

const messages = computed(() => {
    return conversation.value?.messages ?? [];
});

const sending = ref(false);
const testingAi = ref(false);

const errorMessage = ref("");
const successMessage = ref("");

const updatingConversation = ref(false);

const aiStatus = ref("idle");

/*
|--------------------------------------------------------------------------
| Polling
|--------------------------------------------------------------------------
*/

let conversationPollingInterval = null;
let aiPollingActive = false;
let refreshInProgress = false;

/*
|--------------------------------------------------------------------------
| Formulaire message
|--------------------------------------------------------------------------
*/

const messageForm = useForm({
    message: "",
});

/*
|--------------------------------------------------------------------------
| Actualisation de la conversation
|--------------------------------------------------------------------------
*/

const refreshConversation = (checkAi = false) => {
    /*
     * Empêche plusieurs router.reload() de s'exécuter
     * en même temps.
     */
    if (refreshInProgress) {
        return;
    }

    refreshInProgress = true;

    router.reload({
        only: [
            "conversation",
            "agents",
            "canModify",
            "isOwner",
            "currentUserId",
        ],

        preserveScroll: true,
        preserveState: true,

        onSuccess: (page) => {
            if (page.props.conversation) {
                conversation.value = page.props.conversation;
            }

            /*
            |--------------------------------------------------------------------------
            | Vérification du traitement IA
            |--------------------------------------------------------------------------
            */

            if (checkAi) {
                const currentMessages =
                    page.props.conversation?.messages ?? [];

                const hasPendingMessage = currentMessages.some((item) => {
                    return (
                        item.sender_type === "client" &&
                        (
                            item.ai_processed === false ||
                            item.ai_processed === 0 ||
                            item.ai_processed === "0" ||
                            item.ai_status === "pending" ||
                            item.ai_status === "processing"
                        )
                    );
                });

                if (!hasPendingMessage) {
                    aiStatus.value = "completed";
                    aiPollingActive = false;
                }
            }
        },

        onError: () => {
            if (checkAi) {
                aiStatus.value = "error";
                aiPollingActive = false;
            }
        },

        onFinish: () => {
            refreshInProgress = false;
        },
    });
};

/*
|--------------------------------------------------------------------------
| Envoi d'un message agent
|--------------------------------------------------------------------------
*/

const sendMessage = () => {
    /*
     * Protection contre les doubles clics
     * ou deux déclenchements simultanés.
     */
    if (sending.value || messageForm.processing) {
        return;
    }

    errorMessage.value = "";
    successMessage.value = "";

    if (!canModify.value) {
        errorMessage.value =
            "Vous ne pouvez pas répondre à cette conversation car elle ne vous est pas attribuée.";
        return;
    }

    const content = messageForm.message.trim();

    if (!content) {
        errorMessage.value = "Veuillez saisir un message.";
        return;
    }

    sending.value = true;

    messageForm.message = content;

    messageForm.post(
        route(
            "conversations.messages.store",
            conversation.value.id
        ),
        {
            preserveScroll: true,
            preserveState: true,

            onSuccess: (page) => {
                /*
                 * On vide uniquement après succès.
                 */
                messageForm.reset();

                if (page.props.conversation) {
                    conversation.value =
                        page.props.conversation;
                }

                successMessage.value =
                    "Message envoyé avec succès.";

                setTimeout(() => {
                    successMessage.value = "";
                }, 3000);

                /*
                 * Rafraîchissement immédiat pour avoir
                 * la dernière version de la conversation.
                 */
                refreshConversation(false);
            },

            onError: (errors) => {
                console.error(
                    "Erreur lors de l'envoi du message :",
                    errors
                );

                errorMessage.value =
                    errors.message ??
                    "Impossible d'envoyer le message.";
            },

            onFinish: () => {
                sending.value = false;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Test IA
|--------------------------------------------------------------------------
*/

const testAi = () => {
    errorMessage.value = "";
    successMessage.value = "";

    if (!canModify.value) {
        errorMessage.value =
            "Vous n'avez pas les droits pour tester l'IA.";
        return;
    }

    if (!conversation.value.ai_enabled) {
        errorMessage.value =
            "L'intelligence artificielle est désactivée pour cette conversation.";
        return;
    }

    if (testingAi.value || aiProcessing.value) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Récupérer tous les messages utilisables
    |--------------------------------------------------------------------------
    */

    const allMessages = messages.value.filter(
        (item) =>
            item.content &&
            item.content.trim() !== ""
    );

    const lastMessage =
        allMessages.length > 0
            ? allMessages[allMessages.length - 1]
            : null;

    if (!lastMessage) {
        errorMessage.value =
            "Aucun message disponible pour tester l'IA.";
        return;
    }

    const messageContent =
        lastMessage.content.trim();

    testingAi.value = true;
    aiStatus.value = "processing";
    aiPollingActive = true;

    router.post(
        route(
            "conversations.ai-test",
            conversation.value.id
        ),
        {
            message: messageContent,
        },
        {
            preserveScroll: true,
            preserveState: true,

            onSuccess: () => {
                successMessage.value =
                    "Message envoyé à l'IA. Traitement en cours...";

                startAiPolling();
            },

            onError: (errors) => {
                console.error(
                    "Erreur lors du test IA :",
                    errors
                );

                aiStatus.value = "error";
                aiPollingActive = false;

                errorMessage.value =
                    errors.message ??
                    "Impossible de lancer le traitement IA.";
            },

            onFinish: () => {
                testingAi.value = false;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Modification conversation
|--------------------------------------------------------------------------
*/

const updateConversation = (field, value) => {
    errorMessage.value = "";
    successMessage.value = "";

    if (!canModify.value) {
        errorMessage.value =
            "Vous n'avez pas les droits pour modifier cette conversation.";
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Attribution : owner uniquement
    |--------------------------------------------------------------------------
    */

    if (field === "assigned_to" && !isOwner.value) {
        errorMessage.value =
            "Seul le responsable peut attribuer une conversation.";
        return;
    }

    updatingConversation.value = true;

    router.patch(
        route(
            "conversations.update",
            conversation.value.id
        ),
        {
            [field]: value,
        },
        {
            preserveScroll: true,
            preserveState: true,

            onSuccess: (page) => {
                if (page.props.conversation) {
                    conversation.value =
                        page.props.conversation;
                }

                successMessage.value =
                    "Conversation mise à jour avec succès.";

                setTimeout(() => {
                    successMessage.value = "";
                }, 3000);
            },

            onError: (errors) => {
                console.error(
                    "Erreur lors de la mise à jour :",
                    errors
                );

                errorMessage.value =
                    errors.message ??
                    "Impossible de mettre à jour la conversation.";
            },

            onFinish: () => {
                updatingConversation.value = false;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Statut
|--------------------------------------------------------------------------
*/

const changeStatus = (event) => {
    updateConversation(
        "status",
        event.target.value
    );
};

/*
|--------------------------------------------------------------------------
| Priorité
|--------------------------------------------------------------------------
*/

const changePriority = (event) => {
    updateConversation(
        "priority",
        event.target.value
    );
};

/*
|--------------------------------------------------------------------------
| IA
|--------------------------------------------------------------------------
*/

const toggleAi = (event) => {
    updateConversation(
        "ai_enabled",
        event.target.checked
    );
};

/*
|--------------------------------------------------------------------------
| Attribution
|--------------------------------------------------------------------------
*/

const changeAssignedAgent = (event) => {
    const value = event.target.value;

    const assignedTo =
        value ? Number(value) : null;

    updateConversation(
        "assigned_to",
        assignedTo
    );
};

/*
|--------------------------------------------------------------------------
| Agent affecté
|--------------------------------------------------------------------------
*/

const assignedAgent = computed(() => {
    if (!conversation.value?.assigned_to) {
        return null;
    }

    return (
        agents.value.find(
            (agent) =>
                Number(agent.id) ===
                Number(
                    conversation.value.assigned_to
                )
        ) ?? null
    );
});

const isAssignedToCurrentUser = computed(() => {
    return (
        conversation.value?.assigned_to !== null &&
        conversation.value?.assigned_to !== undefined &&
        Number(
            conversation.value.assigned_to
        ) ===
            Number(currentUserId.value)
    );
});

const assignedAgentName = computed(() => {
    if (!assignedAgent.value) {
        return "Non attribuée";
    }

    return assignedAgent.value.name;
});

/*
|--------------------------------------------------------------------------
| Détection du traitement IA
|--------------------------------------------------------------------------
*/

const aiProcessing = computed(() => {
    return messages.value.some((item) => {
        return (
            item.sender_type === "client" &&
            (
                item.ai_status === "pending" ||
                item.ai_status === "processing"
            )
        );
    });
});

/*
|--------------------------------------------------------------------------
| Dernière réponse IA
|--------------------------------------------------------------------------
*/

const latestAiMessage = computed(() => {
    const aiMessages =
        messages.value.filter(
            (item) =>
                item.sender_type === "ai"
        );

    if (aiMessages.length === 0) {
        return null;
    }

    return aiMessages[
        aiMessages.length - 1
    ];
});

/*
|--------------------------------------------------------------------------
| Transfert humain
|--------------------------------------------------------------------------
*/

const humanTransfer = computed(() => {
    const aiMessage =
        latestAiMessage.value;

    const transferRequested =
        aiMessage?.metadata?.transfer_to_human === true;

    return (
        conversation.value?.ai_enabled === false &&
        conversation.value?.status === "open" &&
        transferRequested
    );
});

/*
|--------------------------------------------------------------------------
| Classes messages
|--------------------------------------------------------------------------
*/

const messageClasses = (item) => {
    if (item.sender_type === "client") {
        return "justify-start";
    }

    return "justify-end";
};

/*
|--------------------------------------------------------------------------
| Classes bulles
|--------------------------------------------------------------------------
*/

const bubbleClasses = (item) => {
    if (item.sender_type === "client") {
        return "bg-canvas-sunken text-night-900";
    }

    if (item.sender_type === "ai") {
        return "bg-brand-500 text-white";
    }

    if (item.sender_type === "agent") {
        return "bg-emerald-600 text-white";
    }

    return "bg-canvas-sunken text-night-900";
};

/*
|--------------------------------------------------------------------------
| Expéditeur
|--------------------------------------------------------------------------
*/

const senderLabel = (item) => {
    if (item.sender_type === "client") {
        return "Client";
    }

    if (item.sender_type === "ai") {
        return "IA";
    }

    if (item.sender_type === "agent") {
        return item.user?.name
            ? item.user.name
            : "Agent";
    }

    return "Système";
};

/*
|--------------------------------------------------------------------------
| Statut
|--------------------------------------------------------------------------
*/

const statusLabel = (status) => {
    const labels = {
        open: "Ouverte",
        pending: "En attente",
        resolved: "Résolue",
        closed: "Fermée",
    };

    return labels[status] ?? status;
};

/*
|--------------------------------------------------------------------------
| Priorité
|--------------------------------------------------------------------------
*/

const priorityLabel = (priority) => {
    const labels = {
        low: "Faible",
        normal: "Normale",
        high: "Haute",
        urgent: "Urgente",
    };

    return labels[priority] ?? priority;
};

/*
|--------------------------------------------------------------------------
| Polling permanent de la conversation
|--------------------------------------------------------------------------
|
| Ce polling fonctionne en permanence.
| Il permet à l'agent de voir les nouveaux messages du client
| sans actualiser la page.
|
*/

const startConversationPolling = () => {
    stopConversationPolling();

    /*
     * Première récupération immédiate.
     */
    refreshConversation(false);

    /*
     * Puis toutes les 2 secondes.
     */
    conversationPollingInterval =
        setInterval(() => {
            refreshConversation(false);
        }, 2000);
};

const stopConversationPolling = () => {
    if (conversationPollingInterval) {
        clearInterval(
            conversationPollingInterval
        );

        conversationPollingInterval = null;
    }
};

/*
|--------------------------------------------------------------------------
| Polling spécifique IA
|--------------------------------------------------------------------------
|
| Le polling permanent de la conversation fonctionne déjà.
| Cette fonction sert seulement à suivre l'état du traitement IA.
|
*/

const startAiPolling = () => {
    aiPollingActive = true;

    aiStatus.value = "processing";

    /*
     * On vérifie immédiatement.
     */
    refreshConversation(true);
};

const stopAiPolling = () => {
    aiPollingActive = false;
};

/*
|--------------------------------------------------------------------------
| Cycle de vie
|--------------------------------------------------------------------------
*/

onMounted(() => {
    /*
     * Toujours écouter la conversation.
     *
     * L'agent voit ainsi automatiquement :
     * - les nouveaux messages du client ;
     * - les nouveaux messages des autres agents ;
     * - les changements de statut ;
     * - les changements d'attribution.
     */
    startConversationPolling();
});

onUnmounted(() => {
    stopConversationPolling();
    stopAiPolling();
});
</script>
<template>
  <Head :title="conversation.subject || 'Conversation'" />

  <AuthenticatedLayout>

    <!-- Contenu -->
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <!--
        Le titre était porté par la barre de navigation recopiée.
        Celle-ci ayant disparu au profit du rail commun, la page
        doit annoncer elle-même de quoi elle parle.
      -->
      <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <h1 class="truncate text-2xl font-semibold tracking-tight text-night-900">
            {{ conversation.subject || "Conversation" }}
          </h1>

          <p class="mt-1 text-sm text-night-400">
            {{
              [conversation.client?.first_name, conversation.client?.last_name]
                .filter(Boolean)
                .join(" ") || "Client inconnu"
            }}
          </p>
        </div>

        <Link
          :href="route('conversations.index')"
          class="rounded-xl border border-line bg-white px-4 py-2 text-sm font-medium text-night-700 transition hover:bg-canvas-sunken"
        >
          Retour
        </Link>
      </div>

      <!-- Succès -->
      <div
        v-if="successMessage"
        class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
      >
        ✓ {{ successMessage }}
      </div>

      <!-- Erreur -->
      <div
        v-if="errorMessage"
        class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
      >
        ⚠ {{ errorMessage }}
      </div>

      <!-- Transfert vers un agent humain -->
      <div
        v-if="humanTransfer"
        class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4"
      >
        <div class="flex items-start gap-3">
          <div class="text-xl">👤</div>
          <div>
            <h3 class="font-semibold text-rose-800">
              Conversation transférée à un agent humain
            </h3>
            <p class="mt-1 text-sm text-rose-700">
              L'IA est désactivée pour cette conversation. Un agent humain peut
              maintenant prendre en charge la demande.
            </p>
            <p class="mt-2 text-sm font-medium text-rose-800">
              Agent affecté : {{ assignedAgentName }}
            </p>
          </div>
        </div>
      </div>

      <!-- État IA -->
      <div
        v-if="aiStatus === 'processing'"
        class="mb-5 rounded-xl border border-brand-200 bg-brand-50 p-4"
      >
        <div class="flex items-center gap-3">
          <div
            class="h-5 w-5 animate-spin rounded-full border-2 border-brand-200 border-t-brand-500"
          ></div>

          <div>
            <p class="font-semibold text-brand-800">
              L'IA traite actuellement le message
            </p>

            <p class="mt-1 text-sm text-brand-600">
              Claude analyse la conversation et prépare une réponse...
            </p>
          </div>
        </div>
      </div>

      <!-- IA terminée -->
      <div
        v-if="aiStatus === 'completed'"
        class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
      >
        <div class="flex items-center gap-3">
          <div
            class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"
          >
            ✓
          </div>

          <div>
            <p class="font-semibold text-emerald-800">Réponse IA reçue</p>

            <p class="mt-1 text-sm text-emerald-600">
              La réponse de Claude a été ajoutée à la conversation.
            </p>
          </div>
        </div>
      </div>
      <!-- IA en erreur -->
      <div
        v-if="aiStatus === 'error'"
        class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4"
      >
        <div class="flex items-start gap-3">
          <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600"
          >
            ⚠
          </div>

          <div>
            <p class="font-semibold text-rose-800">Le traitement IA a échoué</p>

            <p class="mt-1 text-sm text-rose-600">
              Une erreur est survenue lors de la génération de la réponse.
            </p>
          </div>
        </div>
      </div>

      <!-- Retour -->
      <div class="mb-5">
        <Link
          :href="route('conversations.index')"
          class="inline-flex items-center text-sm font-medium text-brand-600 hover:text-brand-800"
        >
          ← Retour aux conversations
        </Link>
      </div>

      <!-- En-tête -->
      <div
        class="mb-6 rounded-xl border border-line bg-white p-5 shadow-sm"
      >
        <div
          class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-xl font-bold text-night-900">
                {{ conversation.subject || "Sans objet" }}
              </h2>

              <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="
                  conversation.status === 'open'
                    ? 'bg-emerald-100 text-emerald-700'
                    : conversation.status === 'pending'
                      ? 'bg-amber-100 text-amber-700'
                      : conversation.status === 'resolved'
                        ? 'bg-blue-100 text-blue-700'
                        : 'bg-canvas-sunken text-night-600'
                "
              >
                {{ statusLabel(conversation.status) }}
              </span>

              <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="
                  conversation.priority === 'urgent'
                    ? 'bg-rose-100 text-rose-700'
                    : conversation.priority === 'high'
                      ? 'bg-orange-100 text-orange-700'
                      : conversation.priority === 'low'
                        ? 'bg-canvas-sunken text-night-500'
                        : 'bg-blue-100 text-blue-700'
                "
              >
                {{ priorityLabel(conversation.priority) }}
              </span>
            </div>

            <div
              class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-night-400"
            >
              <span>
                Canal :
                <strong class="text-night-600">
                  {{ conversation.channel }}
                </strong>
              </span>

              <span v-if="conversation.last_message_at">
                Dernier message :
                <strong class="text-night-600">
                  {{ conversation.last_message_at }}
                </strong>
              </span>

              <span>
                IA :
                <strong
                  :class="
                    conversation.ai_enabled ? 'text-emerald-600' : 'text-night-400'
                  "
                >
                  {{ conversation.ai_enabled ? "Activée" : "Désactivée" }}
                </strong>
              </span>
            </div>
          </div>

          <!-- Client -->
          <div class="rounded-lg bg-canvas-sunken px-4 py-3 lg:min-w-[280px]">
            <p
              class="text-xs font-semibold uppercase tracking-wide text-night-300"
            >
              Client
            </p>

            <p class="mt-1 font-semibold text-night-900">
              {{ conversation.client?.name || "Client inconnu" }}
            </p>

            <p v-if="conversation.client?.email" class="text-sm text-night-400">
              {{ conversation.client.email }}
            </p>

            <p v-if="conversation.client?.phone" class="text-sm text-night-400">
              {{ conversation.client.phone }}
            </p>
          </div>
        </div>
      </div>

      <!-- Avertissement -->
      <div
        v-if="!canModify"
        class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4"
      >
        <div class="flex items-start gap-3">
          <div class="mt-0.5 text-amber-600">⚠</div>

          <div>
            <h3 class="font-semibold text-amber-800">
              Conversation non attribuée
            </h3>

            <p class="mt-1 text-sm text-amber-700">
              Cette conversation ne vous est pas attribuée. Vous pouvez la
              consulter, mais vous ne pouvez pas répondre ou modifier ses
              paramètres.
            </p>
          </div>
        </div>
      </div>

      <!-- Layout -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Conversation -->
        <section class="lg:col-span-2">
          <div
            class="flex min-h-[650px] flex-col overflow-hidden rounded-xl border border-line bg-white shadow-sm"
          >
            <!-- Header -->
            <div class="border-b border-line px-5 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="font-semibold text-night-900">Messages</h3>

                  <p class="text-sm text-night-400">
                    {{ messages.length }}
                    message{{ messages.length > 1 ? "s" : "" }}
                  </p>
                </div>

                <span
                  v-if="aiProcessing"
                  class="flex items-center gap-2 rounded-full bg-brand-100 px-3 py-1 text-xs font-medium text-brand-700"
                >
                  <span
                    class="h-2 w-2 animate-pulse rounded-full bg-brand-500"
                  ></span>

                  IA en cours...
                </span>
              </div>
            </div>

            <!-- Messages -->
            <div class="flex-1 space-y-4 overflow-y-auto bg-canvas-sunken p-5">
              <div
                v-if="messages.length === 0"
                class="flex min-h-[400px] items-center justify-center"
              >
                <div class="text-center text-night-400">
                  <div class="mb-2 text-4xl">💬</div>

                  <p>Aucun message dans cette conversation.</p>
                </div>
              </div>

              <div
                v-for="item in messages"
                :key="item.id"
                class="flex"
                :class="messageClasses(item)"
              >
                <div class="max-w-[85%] sm:max-w-[75%]">
                  <!-- Expéditeur -->
                  <div
                    class="mb-1 flex items-center gap-2 text-xs text-night-400"
                    :class="
                      item.sender_type === 'client'
                        ? 'justify-start'
                        : 'justify-end'
                    "
                  >
                    <span class="font-semibold">
                      {{ senderLabel(item) }}
                    </span>

                    <span v-if="item.created_at">
                      {{ item.created_at }}
                    </span>
                  </div>

                  <!-- Bulle -->
                  <div
                    class="rounded-2xl px-4 py-3 text-sm shadow-sm"
                    :class="bubbleClasses(item)"
                  >
                    <p class="whitespace-pre-wrap break-words">
                      {{ item.content }}
                    </p>
                  </div>

                  <!-- IA -->
                  <div
                    v-if="item.sender_type === 'ai'"
                    class="mt-1 text-right text-xs text-night-300"
                  >
                    ✓ Réponse générée par Claude
                  </div>

                  <!-- Agent -->
                  <div
                    v-if="item.sender_type === 'agent'"
                    class="mt-1 text-right text-xs text-night-300"
                  >
                    Réponse agent
                  </div>
                </div>
              </div>
            </div>

            <!-- Réponse -->
            <div class="border-t border-line bg-white p-5">
              <div v-if="canModify" class="space-y-3">
                <textarea
                  v-model="messageForm.message"
                  rows="4"
                  :disabled="sending || messageForm.processing || testingAi"
                  placeholder="Écrire une réponse au client..."
                  class="w-full rounded-xl border border-line-strong px-4 py-3 text-sm shadow-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-200 disabled:bg-canvas-sunken"
                  @keydown.ctrl.enter="sendMessage"
                ></textarea>

                <div
                  class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  <p class="text-xs text-night-300">
                    Ctrl + Entrée pour envoyer
                  </p>

                  <div class="flex gap-2">
                    <!-- Tester IA -->
                    <button
                      v-if="conversation.ai_enabled"
                      type="button"
                      :disabled="
                        testingAi ||
                        aiProcessing ||
                        !canModify ||
                        !conversation.ai_enabled
                      "
                      class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-50"
                      @click="testAi"
                    >
                      {{
                        testingAi || aiProcessing
                          ? "IA en cours..."
                          : "Tester l'IA"
                      }}
                    </button>

                    <!-- Envoyer -->
                    <button
                      type="button"
                      :disabled="
                        sending ||
                        messageForm.processing ||
                        testingAi ||
                        !messageForm.message.trim()
                      "
                      class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                      @click="sendMessage"
                    >
                      {{
                        sending || messageForm.processing
                          ? "Envoi..."
                          : "Envoyer"
                      }}
                    </button>
                  </div>
                </div>
              </div>

              <div
                v-else
                class="rounded-xl border border-line bg-canvas-sunken p-4 text-center"
              >
                <p class="text-sm font-medium text-night-600">
                  Vous ne pouvez pas répondre à cette conversation.
                </p>

                <p class="mt-1 text-xs text-night-400">
                  Elle doit vous être attribuée par le responsable.
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- Panneau -->
        <aside class="space-y-6">
          <!-- Attribution -->
          <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-night-900">Attribution</h3>

            <p class="mt-1 text-sm text-night-400">
              Agent responsable de cette conversation.
            </p>

            <div class="mt-4">
              <label
                for="assigned_to"
                class="mb-2 block text-sm font-medium text-night-600"
              >
                Agent
              </label>

              <select
                id="assigned_to"
                :value="conversation.assigned_to ?? ''"
                :disabled="!isOwner || updatingConversation"
                class="w-full rounded-lg border border-line-strong px-3 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-200 disabled:cursor-not-allowed disabled:bg-canvas-sunken"
                @change="changeAssignedAgent"
              >
                <option value="">Non attribuée</option>

                <option
                  v-for="agent in agents"
                  :key="agent.id"
                  :value="agent.id"
                >
                  {{ agent.name }}
                  {{ agent.role === "owner" ? " (Responsable)" : "" }}
                </option>
              </select>

              <p v-if="!isOwner" class="mt-2 text-xs text-night-400">
                Seul le responsable peut modifier l'attribution.
              </p>
            </div>

            <div class="mt-4 rounded-lg bg-canvas-sunken p-3">
              <p class="text-xs text-night-400">Agent actuellement affecté</p>

              <p class="mt-1 text-sm font-semibold text-night-800">
                {{ assignedAgentName }}
              </p>

              <p
                v-if="isAssignedToCurrentUser"
                class="mt-1 text-xs font-medium text-emerald-600"
              >
                ✓ Cette conversation vous est attribuée
              </p>
            </div>
          </div>

          <!-- Gestion -->
          <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-night-900">Gestion</h3>

            <div class="mt-5 space-y-5">
              <!-- Statut -->
              <div>
                <label
                  for="status"
                  class="mb-2 block text-sm font-medium text-night-600"
                >
                  Statut
                </label>

                <select
                  id="status"
                  :value="conversation.status"
                  :disabled="!canModify || updatingConversation"
                  class="w-full rounded-lg border border-line-strong px-3 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-200 disabled:cursor-not-allowed disabled:bg-canvas-sunken"
                  @change="changeStatus"
                >
                  <option value="open">Ouverte</option>

                  <option value="pending">En attente</option>

                  <option value="resolved">Résolue</option>

                  <option value="closed">Fermée</option>
                </select>
              </div>

              <!-- Priorité -->
              <div>
                <label
                  for="priority"
                  class="mb-2 block text-sm font-medium text-night-600"
                >
                  Priorité
                </label>

                <select
                  id="priority"
                  :value="conversation.priority"
                  :disabled="!canModify || updatingConversation"
                  class="w-full rounded-lg border border-line-strong px-3 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-200 disabled:cursor-not-allowed disabled:bg-canvas-sunken"
                  @change="changePriority"
                >
                  <option value="low">Faible</option>

                  <option value="normal">Normale</option>

                  <option value="high">Haute</option>

                  <option value="urgent">Urgente</option>
                </select>
              </div>

              <!-- IA -->
              <div class="rounded-lg border border-line p-4">
                <div class="flex items-center justify-between gap-4">
                  <div>
                    <p class="text-sm font-semibold text-night-800">
                      Assistant IA
                    </p>

                    <p class="mt-1 text-xs text-night-400">
                      Réponse automatique de Claude
                    </p>
                  </div>

                  <label
                    class="relative inline-flex cursor-pointer items-center"
                  >
                    <input
                      type="checkbox"
                      class="peer sr-only"
                      :checked="conversation.ai_enabled"
                      :disabled="!canModify || updatingConversation"
                      @change="toggleAi"
                    />

                    <div
                      class="h-6 w-11 rounded-full bg-night-200 transition peer-checked:bg-brand-500 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-brand-300 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                    ></div>

                    <div
                      class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5"
                    ></div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Client -->
          <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-night-900">Informations client</h3>

            <div class="mt-4 space-y-4">
              <div>
                <p
                  class="text-xs font-medium uppercase tracking-wide text-night-300"
                >
                  Nom
                </p>

                <p class="mt-1 text-sm font-medium text-night-800">
                  {{ conversation.client?.name || "Non renseigné" }}
                </p>
              </div>

              <div>
                <p
                  class="text-xs font-medium uppercase tracking-wide text-night-300"
                >
                  Email
                </p>

                <p class="mt-1 break-all text-sm text-night-600">
                  {{ conversation.client?.email || "Non renseigné" }}
                </p>
              </div>

              <div>
                <p
                  class="text-xs font-medium uppercase tracking-wide text-night-300"
                >
                  Téléphone
                </p>

                <p class="mt-1 text-sm text-night-600">
                  {{ conversation.client?.phone || "Non renseigné" }}
                </p>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </main>
  </AuthenticatedLayout>
</template>
