(function () {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    const style = document.createElement('link');

    style.rel = 'stylesheet';
    style.href = `${new URL(
        document.currentScript?.src || window.location.href
    ).origin}/widget/widget.css`;

    document.head.appendChild(style);

    const script = document.currentScript;

    const apiUrl =
        script?.dataset.api ||
        'http://127.0.0.1:8000/api';

    const token = script?.dataset.token;

    if (!token) {
        console.error('[AI Widget] Token du widget manquant.');
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | État
    |--------------------------------------------------------------------------
    */

    let conversationId = null;
    let pollingTimer = null;
    let lastMessageId = 0;
    let humanTransferShown = false;
    let loadingMessages = false;

    const STORAGE_KEY = 'ai_service_conversation_id';

    /*
    |--------------------------------------------------------------------------
    | Création du widget
    |--------------------------------------------------------------------------
    */

    function createWidget() {
        const container = document.createElement('div');

        container.id = 'ai-service-widget';

        container.innerHTML = `
            <button
                id="ai-widget-button"
                type="button"
                aria-label="Ouvrir le service client"
            >
                💬
            </button>

            <div id="ai-widget-window" hidden>
                <div id="ai-widget-header">
                    <span id="ai-widget-logo" class="ai-widget-logo"></span>

                    <div class="ai-widget-identity">
                        <strong id="ai-widget-title">Service client</strong>
                        <small>En ligne</small>
                    </div>

                    <button
                        id="ai-widget-close"
                        type="button"
                        aria-label="Fermer"
                    >
                        ×
                    </button>
                </div>

                <div id="ai-widget-body">
                    <div id="ai-widget-welcome">
                        <strong>Bonjour 👋</strong>
                        <p id="ai-widget-welcome-text">
                            Comment pouvons-nous vous aider ?
                        </p>
                    </div>

                    <div id="ai-widget-messages"></div>
                </div>

                <div id="ai-widget-form-container">
                    <form id="ai-widget-client-form">
                        <input
                            type="text"
                            name="first_name"
                            placeholder="Prénom"
                            required
                        >

                        <input
                            type="text"
                            name="last_name"
                            placeholder="Nom"
                            required
                        >

                        <input
                            type="email"
                            name="email"
                            placeholder="Email"
                        >

                        <input
                            type="tel"
                            name="phone"
                            placeholder="Téléphone"
                        >

                        <textarea
                            name="message"
                            placeholder="Écrivez votre message..."
                            rows="3"
                            required
                        ></textarea>

                        <button type="submit">
                            Démarrer la conversation
                        </button>
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(container);

        bindEvents();
    }

    /*
    |--------------------------------------------------------------------------
    | Événements
    |--------------------------------------------------------------------------
    */

    function bindEvents() {
        const button =
            document.getElementById('ai-widget-button');

        const close =
            document.getElementById('ai-widget-close');

        const form =
            document.getElementById('ai-widget-client-form');

        if (button) {
            button.addEventListener(
                'click',
                openWidget
            );
        }

        if (close) {
            close.addEventListener(
                'click',
                closeWidget
            );
        }

        if (form) {
            form.addEventListener(
                'submit',
                createConversation
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Ouvrir
    |--------------------------------------------------------------------------
    */

    function openWidget() {
        const windowElement =
            document.getElementById(
                'ai-widget-window'
            );

        const button =
            document.getElementById(
                'ai-widget-button'
            );

        if (windowElement) {
            windowElement.hidden = false;
        }

        if (button) {
            button.style.display = 'none';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fermer
    |--------------------------------------------------------------------------
    */

    function closeWidget() {
        const windowElement =
            document.getElementById(
                'ai-widget-window'
            );

        const button =
            document.getElementById(
                'ai-widget-button'
            );

        if (windowElement) {
            windowElement.hidden = true;
        }

        if (button) {
            button.style.display = 'flex';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Création conversation
    |--------------------------------------------------------------------------
    */

    async function createConversation(event) {
        event.preventDefault();

        const form = event.currentTarget;

        if (!form) {
            return;
        }

        const data = new FormData(form);

        const payload = {
            first_name: data.get('first_name'),
            last_name: data.get('last_name'),
            email: data.get('email') || null,
            phone: data.get('phone') || null,
            message: data.get('message'),
        };

        const submitButton =
            form.querySelector(
                'button[type="submit"]'
            );

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent =
                'Connexion...';
        }

        try {
            const response = await fetch(
                `${apiUrl}/widget/conversations`,
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Widget-Token': token,
                    },

                    body: JSON.stringify(payload),
                    cache: 'no-store',
                }
            );

            if (!response.ok) {
                const errorText =
                    await response.text();

                console.error(
                    '[AI Widget] Erreur création conversation:',
                    response.status,
                    errorText
                );

                throw new Error(
                    `Erreur HTTP ${response.status}`
                );
            }

            const result =
                await response.json();

            if (!result.conversation_id) {
                throw new Error(
                    'Identifiant de conversation manquant.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Sauvegarder la conversation
            |--------------------------------------------------------------------------
            */

            conversationId =
                Number(result.conversation_id);

            localStorage.setItem(
                STORAGE_KEY,
                String(conversationId)
            );

            lastMessageId = 0;
            humanTransferShown = false;

            /*
            |--------------------------------------------------------------------------
            | Afficher le premier message du client
            |--------------------------------------------------------------------------
            */

            addMessage(
                'client',
                payload.message
            );

            /*
            |--------------------------------------------------------------------------
            | Remplacer le formulaire initial
            |--------------------------------------------------------------------------
            */

            showMessageForm();

            /*
            |--------------------------------------------------------------------------
            | Démarrer le polling
            |--------------------------------------------------------------------------
            */

            startPolling();

        } catch (error) {
            console.error(
                '[AI Widget] Erreur création conversation:',
                error
            );

            showError(
                'Impossible de démarrer la conversation.'
            );

            if (submitButton) {
                submitButton.disabled = false;

                submitButton.textContent =
                    'Démarrer la conversation';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Afficher le formulaire de discussion
    |--------------------------------------------------------------------------
    */

    function showMessageForm() {
        const formContainer =
            document.getElementById(
                'ai-widget-form-container'
            );

        if (!formContainer) {
            return;
        }

        formContainer.innerHTML = `
            <form id="ai-widget-message-form">
                <textarea
                    name="message"
                    placeholder="Écrivez votre message..."
                    rows="2"
                    required
                ></textarea>

                <div class="ai-widget-actions">
                    <button type="submit" class="ai-widget-send">
                        Envoyer
                    </button>

                    <button
                        type="button"
                        id="ai-widget-call-button"
                        class="ai-widget-call"
                        aria-label="Appeler un conseiller"
                    >
                        <span class="ai-widget-call-icon">\u{1F4DE}</span>
                        Appeler
                    </button>
                </div>

                <!-- Panneau d'appel : visible dès le lancement -->

                <div id="ai-widget-call-panel" class="ai-widget-call-panel" hidden>
                    <div class="ai-widget-call-info">
                        <span
                            id="ai-widget-call-avatar"
                            class="ai-widget-call-avatar"
                        >\u{1F4DE}</span>

                        <div class="ai-widget-call-text">
                            <span
                                id="ai-widget-call-status"
                                class="ai-widget-call-status"
                            >Connexion\u2026</span>

                            <span
                                id="ai-widget-call-timer"
                                class="ai-widget-call-timer"
                                hidden
                            >00:00</span>
                        </div>
                    </div>

                    <button
                        type="button"
                        id="ai-widget-end-call-button"
                        class="ai-widget-hangup"
                        aria-label="Raccrocher"
                    >
                        <span class="ai-widget-hangup-icon">\u2715</span>
                        Raccrocher
                    </button>
                </div>
            </form>
        `;

        const messageForm =
            document.getElementById(
                'ai-widget-message-form'
            );

        if (messageForm) {
            messageForm.addEventListener(
                'submit',
                sendMessage
            );
        }

        const callButton =
            document.getElementById(
                'ai-widget-call-button'
            );

        if (callButton) {
            callButton.addEventListener(
                'click',
                startCall
            );
        }

        const endCallButton =
            document.getElementById(
                'ai-widget-end-call-button'
            );

        if (endCallButton) {
            endCallButton.addEventListener(
                'click',
                endCall
            );
        }
    }
    /*
    |--------------------------------------------------------------------------
    | Gestion appel agent humain
    |--------------------------------------------------------------------------
    */

    let activeCallId = null;
    let activeCallStatus = null;
    let callStatusTimer = null;

    let callTimerInterval = null;
    let callStartedAt = null;

    /*
    |--------------------------------------------------------------------------
    | Retour d'appel
    |--------------------------------------------------------------------------
    |
    | Tonalité générée avec l'API Web Audio : aucun fichier son à héberger.
    | Le client entend donc réellement que « ça sonne » de l'autre côté.
    |
    */

    let audioContext = null;
    let ringbackInterval = null;

    function getAudioContext() {
        const Context = window.AudioContext || window.webkitAudioContext;

        if (!Context) {
            return null;
        }

        if (!audioContext) {
            audioContext = new Context();
        }

        if (audioContext.state === 'suspended') {
            audioContext.resume().catch(function () {});
        }

        return audioContext;
    }

    function playRingback() {
        const context = getAudioContext();

        if (!context || context.state !== 'running') {
            return;
        }

        const gain = context.createGain();

        gain.connect(context.destination);
        gain.gain.setValueAtTime(0.0001, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.09, context.currentTime + 0.05);
        gain.gain.setValueAtTime(0.09, context.currentTime + 1.3);
        gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 1.5);

        [440, 480].forEach(function (frequency) {
            const oscillator = context.createOscillator();

            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            oscillator.connect(gain);
            oscillator.start(context.currentTime);
            oscillator.stop(context.currentTime + 1.5);
        });
    }

    function startRingback() {
        if (ringbackInterval) {
            return;
        }

        playRingback();

        ringbackInterval = setInterval(playRingback, 5000);
    }

    function stopRingback() {
        if (ringbackInterval) {
            clearInterval(ringbackInterval);
            ringbackInterval = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Chronomètre
    |--------------------------------------------------------------------------
    */

    function startCallTimer() {
        stopCallTimer();

        callStartedAt = Date.now();

        const timer = document.getElementById('ai-widget-call-timer');

        if (timer) {
            timer.hidden = false;
            timer.textContent = '00:00';
        }

        callTimerInterval = setInterval(function () {
            const element = document.getElementById('ai-widget-call-timer');

            if (!element || !callStartedAt) {
                return;
            }

            const total = Math.floor((Date.now() - callStartedAt) / 1000);
            const minutes = String(Math.floor(total / 60)).padStart(2, '0');
            const seconds = String(total % 60).padStart(2, '0');

            element.textContent = minutes + ':' + seconds;
        }, 1000);
    }

    function stopCallTimer() {
        if (callTimerInterval) {
            clearInterval(callTimerInterval);
            callTimerInterval = null;
        }

        callStartedAt = null;
    }

    /*
    |--------------------------------------------------------------------------
    | Affichage de l'état de l'appel
    |--------------------------------------------------------------------------
    |
    | Le bouton « Raccrocher » apparaît dès le lancement, pas seulement
    | une fois l'agent en ligne : un client doit toujours pouvoir renoncer
    | pendant la sonnerie.
    |
    */

    function updateCallStatusUI(status, message) {
        const panel = document.getElementById('ai-widget-call-panel');
        const statusElement = document.getElementById('ai-widget-call-status');
        const timerElement = document.getElementById('ai-widget-call-timer');
        const avatar = document.getElementById('ai-widget-call-avatar');
        const callButton = document.getElementById('ai-widget-call-button');
        const endButton = document.getElementById('ai-widget-end-call-button');

        const labels = {
            connecting: 'Connexion\u2026',
            ringing: 'Votre conseiller est appel\u00e9\u2026',
            answered: 'En ligne avec un conseiller',
            completed: 'Appel termin\u00e9',
            cancelled: 'Appel annul\u00e9',
            missed: 'Personne n\u2019a pu r\u00e9pondre',
            busy: 'Tous nos conseillers sont occup\u00e9s',
            failed: 'La connexion a \u00e9chou\u00e9'
        };

        const isLive = status === 'connecting'
            || status === 'ringing'
            || status === 'answered';

        if (panel) {
            panel.hidden = false;
            panel.setAttribute('data-state', status);
        }

        if (statusElement) {
            statusElement.textContent = message || labels[status] || 'Connexion\u2026';
        }

        if (avatar) {
            avatar.textContent = status === 'answered'
                ? '\u{1F3A7}'
                : '\u{1F4DE}';
        }

        /*
         * Le bouton d'appel disparaît tant qu'un appel est en cours,
         * pour éviter les doubles appels.
         */
        if (callButton) {
            callButton.hidden = isLive;
        }

        if (endButton) {
            endButton.hidden = !isLive;
        }

        if (status === 'ringing' || status === 'connecting') {
            startRingback();

            if (timerElement) {
                timerElement.hidden = true;
            }
        } else {
            stopRingback();
        }

        if (status === 'answered') {
            if (!callTimerInterval) {
                startCallTimer();
            }
        } else {
            stopCallTimer();

            if (timerElement) {
                timerElement.hidden = true;
            }
        }

        /*
         * État terminal : le panneau se referme après quelques secondes
         * pour rendre la place à la conversation écrite.
         */
        if (!isLive && panel) {
            setTimeout(function () {
                if (!activeCallId && panel) {
                    panel.hidden = true;
                }
            }, 4000);
        }

        activeCallStatus = status;
    }

    /*
    |--------------------------------------------------------------------------
    | Démarrer appel
    |--------------------------------------------------------------------------
    */

    async function startCall() {
        if (!conversationId) {
            updateCallStatusUI(
                "failed",
                "Veuillez d'abord démarrer une conversation."
            );
            return;
        }

        if (activeCallId) {
            updateCallStatusUI(
                activeCallStatus || "answered",
                "Un appel est déjà en cours."
            );
            return;
        }

        updateCallStatusUI("connecting");

        try {
            const response = await fetch(
                `${apiUrl}/widget/calls`,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-Widget-Token": token
                    },
                    body: JSON.stringify({
                        conversation_id: conversationId
                    }),
                    cache: "no-store"
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message ||
                    "Impossible de démarrer l'appel."
                );
            }

            activeCallId = Number(data.call_id);

            localStorage.setItem(
                "ai_service_call_id",
                String(activeCallId)
            );

            /*
             * Le serveur renvoie l'état réel : ringing tant que le poste
             * du conseiller sonne, busy si personne n'est joignable.
             */
            updateCallStatusUI(data.status || "ringing");

            startCallStatusPolling();

        } catch (error) {
            console.error(
                "[AI Widget] Erreur démarrage appel:",
                error
            );

            activeCallId = null;

            localStorage.removeItem(
                "ai_service_call_id"
            );

            updateCallStatusUI(
                "failed",
                error.message ||
                "Impossible de connecter l'appel."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Vérifier statut appel
    |--------------------------------------------------------------------------
    */

    async function loadCallStatus() {
        if (!activeCallId) {
            return;
        }

        try {
            const response = await fetch(
                `${apiUrl}/widget/calls/${activeCallId}/status`,
                {
                    method: "GET",
                    headers: {
                        "Accept": "application/json",
                        "X-Widget-Token": token
                    },
                    cache: "no-store"
                }
            );

            if (!response.ok) {
                throw new Error(
                    `Erreur HTTP ${response.status}`
                );
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(
                    data.message ||
                    "Statut de l'appel indisponible."
                );
            }

            const status =
                data.call?.status ||
                data.status ||
                "ringing";

            updateCallStatusUI(status, data.message || "");

            /*
             * « completed » s'ajoute aux états terminaux : c'est le cas
             * normal d'un appel mené à son terme, y compris quand c'est
             * le conseiller qui raccroche.
             */
            if (
                [
                    "completed",
                    "cancelled",
                    "missed",
                    "busy",
                    "failed"
                ].includes(status)
            ) {
                finishLocalCall();
            }

        } catch (error) {
            console.error(
                "[AI Widget] Erreur statut appel:",
                error
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Polling appel
    |--------------------------------------------------------------------------
    */

    function startCallStatusPolling() {
        stopCallStatusPolling();

        loadCallStatus();

        /*
         * Sondage rapproché : pendant la sonnerie, deux secondes de
         * retard se remarquent immédiatement.
         */
        callStatusTimer = setInterval(loadCallStatus, 2000);
    }

    function stopCallStatusPolling() {
        if (callStatusTimer) {
            clearInterval(callStatusTimer);
            callStatusTimer = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Terminer appel
    |--------------------------------------------------------------------------
    */

    async function endCall() {
        if (!activeCallId) {
            return;
        }

        const callId = activeCallId;

        /*
         * La sonnerie s'arrête immédiatement au clic : attendre la
         * réponse du serveur donnerait l'impression que le bouton
         * ne répond pas.
         */
        stopRingback();

        const wasAnswered = activeCallStatus === "answered";

        updateCallStatusUI(
            wasAnswered ? "completed" : "cancelled",
            "Fin de l'appel\u2026"
        );

        try {
            const response = await fetch(
                `${apiUrl}/widget/calls/${callId}/end`,
                {
                    method: "POST",
                    headers: {
                        "Accept": "application/json",
                        "X-Widget-Token": token
                    },
                    cache: "no-store"
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message ||
                    "Impossible de terminer l'appel."
                );
            }

            updateCallStatusUI(data.status || "completed");

            finishLocalCall();

        } catch (error) {
            console.error(
                "[AI Widget] Erreur fin appel:",
                error
            );

            finishLocalCall();

            updateCallStatusUI(
                "failed",
                "L'appel a été interrompu."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Nettoyage appel local
    |--------------------------------------------------------------------------
    */

    function finishLocalCall() {
        stopCallStatusPolling();
        stopRingback();
        stopCallTimer();

        activeCallId = null;
        activeCallStatus = null;

        localStorage.removeItem("ai_service_call_id");

        /*
         * Le libellé affiché par updateCallStatusUI est conservé :
         * « personne n'a pu répondre » et « appel terminé » ne disent
         * pas la même chose au client.
         */
        const callButton = document.getElementById("ai-widget-call-button");

        const endButton = document.getElementById("ai-widget-end-call-button");

        const timer = document.getElementById("ai-widget-call-timer");

        if (callButton) {
            callButton.hidden = false;
        }

        if (endButton) {
            endButton.hidden = true;
        }

        if (timer) {
            timer.hidden = true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Restaurer appel après actualisation
    |--------------------------------------------------------------------------
    */

    function restoreActiveCall() {
        const savedCallId =
            localStorage.getItem(
                "ai_service_call_id"
            );

        if (!savedCallId) {
            return;
        }

        const parsedCallId =
            Number(savedCallId);

        if (!parsedCallId) {
            localStorage.removeItem(
                "ai_service_call_id"
            );
            return;
        }

        activeCallId = parsedCallId;

        updateCallStatusUI(
            "answered",
            "📞 Appel en cours avec un agent."
        );

        startCallStatusPolling();
    }
    /*
    |--------------------------------------------------------------------------
    | Envoi message client
    |--------------------------------------------------------------------------
    */

    async function sendMessage(event) {
        event.preventDefault();

        if (!conversationId) {
            return;
        }

        const form = event.currentTarget;

        if (!form) {
            return;
        }

        const textarea =
            form.querySelector('textarea');

        const button =
            form.querySelector(
                'button[type="submit"]'
            );

        if (!textarea) {
            return;
        }

        const message =
            textarea.value.trim();

        if (!message) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Empêcher les doubles clics
        |--------------------------------------------------------------------------
        */

        if (textarea.disabled) {
            return;
        }

        textarea.disabled = true;

        if (button) {
            button.disabled = true;
            button.textContent = 'Envoi...';
        }

        try {
            const response = await fetch(
                `${apiUrl}/widget/conversations/${conversationId}/messages`,
                {
                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Widget-Token': token,
                    },

                    body: JSON.stringify({
                        message: message,
                    }),

                    cache: 'no-store',
                }
            );

            if (!response.ok) {
                const errorText =
                    await response.text();

                console.error(
                    '[AI Widget] Erreur envoi message:',
                    response.status,
                    errorText
                );

                throw new Error(
                    `Erreur HTTP ${response.status}`
                );
            }

            const result =
                await response.json();

            /*
            |--------------------------------------------------------------------------
            | Message envoyé avec succès
            |--------------------------------------------------------------------------
            */

            if (result.message_id) {
                const returnedId =
                    Number(result.message_id);

                if (
                    returnedId >
                    lastMessageId
                ) {
                    lastMessageId =
                        returnedId;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Vider uniquement le textarea
            |--------------------------------------------------------------------------
            */

            textarea.value = '';

            /*
            |--------------------------------------------------------------------------
            | Affichage immédiat côté client
            |--------------------------------------------------------------------------
            */

            addMessage(
                'client',
                message
            );

            expectReply();

        } catch (error) {
            console.error(
                '[AI Widget] Erreur envoi message:',
                error
            );

            showError(
                'Votre message n’a pas pu être envoyé.'
            );

        } finally {
            textarea.disabled = false;

            if (button) {
                button.disabled = false;
                button.textContent = 'Envoyer';
            }

            textarea.focus();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Restauration conversation
    |--------------------------------------------------------------------------
    */

    function restoreConversation() {
        const savedConversationId =
            localStorage.getItem(
                STORAGE_KEY
            );

        if (!savedConversationId) {
            return;
        }

        const parsedId =
            Number(savedConversationId);

        if (!parsedId) {
            localStorage.removeItem(
                STORAGE_KEY
            );

            conversationId = null;

            return;
        }

        conversationId = parsedId;

        humanTransferShown = false;

        /*
        |--------------------------------------------------------------------------
        | Afficher le formulaire de discussion
        |--------------------------------------------------------------------------
        */

        showMessageForm();

        /*
        |--------------------------------------------------------------------------
        | Récupérer l'historique
        |--------------------------------------------------------------------------
        */

        restoreConversationMessages();
    }

    /*
    |--------------------------------------------------------------------------
    | Récupérer l'historique lors d'une reconnexion
    |--------------------------------------------------------------------------
    */

    async function restoreConversationMessages() {
        if (!conversationId) {
            return;
        }

        try {
            const response = await fetch(
                `${apiUrl}/widget/conversations/${conversationId}/messages`,
                {
                    method: 'GET',

                    headers: {
                        'Accept': 'application/json',
                        'X-Widget-Token': token,
                    },

                    cache: 'no-store',
                }
            );

            if (!response.ok) {
                /*
                * Conversation inexistante ou token invalide.
                */
                if (
                    response.status === 404 ||
                    response.status === 401
                ) {
                    localStorage.removeItem(
                        STORAGE_KEY
                    );

                    conversationId = null;

                    return;
                }

                throw new Error(
                    `Erreur HTTP ${response.status}`
                );
            }

            const result =
                await response.json();

            if (
                !Array.isArray(
                    result.messages
                )
            ) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Réinitialiser l'affichage
            |--------------------------------------------------------------------------
            */

            const messagesContainer =
                document.getElementById(
                    'ai-widget-messages'
                );

            if (messagesContainer) {
                messagesContainer.innerHTML = '';
            }

            lastMessageId = 0;

            /*
            |--------------------------------------------------------------------------
            | Afficher tout l'historique
            |--------------------------------------------------------------------------
            */

            result.messages.forEach(
                (message) => {

                    if (!message || !message.id) {
                        return;
                    }

                    const messageId =
                        Number(message.id);

                    if (
                        message.sender_type ===
                        'client'
                    ) {
                        addMessage(
                            'client',
                            message.content
                        );
                    }

                    if (
                        message.sender_type ===
                        'ai'
                    ) {
                        /*
                         * L'indicateur doit disparaître AVANT l'ajout :
                         * sinon la réponse s'afficherait au-dessus des
                         * trois points encore animés.
                         */
                        replyReceived();

                        addMessage(
                            'ai',
                            message.content,
                            message.attachments
                        );
                    }

                    if (
                        message.sender_type ===
                        'agent'
                    ) {
                        replyReceived();

                        addMessage(
                            'agent',
                            message.content,
                            message.attachments
                        );
                    }

                    if (
                        messageId >
                        lastMessageId
                    ) {
                        lastMessageId =
                            messageId;
                    }
                }
            );

            /*
            |--------------------------------------------------------------------------
            | Afficher le transfert humain une seule fois
            |--------------------------------------------------------------------------
            */

            if (
                result.ai_enabled === false
            ) {
                humanTransferShown = true;

                showHumanTransfer();
            }

            /*
            |--------------------------------------------------------------------------
            | Reprendre le polling
            |--------------------------------------------------------------------------
            */

            startPolling();

        } catch (error) {
            console.error(
                '[AI Widget] Erreur restauration conversation:',
                error
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Attente d'une réponse
    |--------------------------------------------------------------------------
    |
    | Deux changements qui ne rendent pas l'IA plus rapide, mais la font
    | paraître bien plus rapide :
    |
    | - un indicateur « en train d'écrire » dès l'envoi : le client sait
    |   qu'on s'occupe de lui, au lieu de fixer une fenêtre immobile ;
    |
    | - un sondage à la seconde tant qu'une réponse est attendue, puis
    |   espacé ensuite. À trois secondes d'intervalle, une réponse prête
    |   pouvait rester invisible jusqu'à trois secondes de plus.
    |
    */

    const FAST_POLL = 1000;
    const IDLE_POLL = 4000;

    let awaitingReply = false;
    let awaitingSince = 0;

    function showTyping() {
        const messages = document.getElementById('ai-widget-messages');

        if (!messages || document.getElementById('ai-widget-typing')) {
            return;
        }

        const typing = document.createElement('div');

        typing.id = 'ai-widget-typing';
        typing.className = 'ai-widget-typing';
        typing.setAttribute('aria-label', 'Réponse en cours de rédaction');
        typing.innerHTML = '<span></span><span></span><span></span>';

        messages.appendChild(typing);
        messages.scrollTop = messages.scrollHeight;
    }

    function hideTyping() {
        const typing = document.getElementById('ai-widget-typing');

        if (typing) {
            typing.remove();
        }
    }

    function expectReply() {
        awaitingReply = true;
        awaitingSince = Date.now();

        showTyping();
        schedulePolling(FAST_POLL);
    }

    function replyReceived() {
        if (!awaitingReply) {
            return;
        }

        awaitingReply = false;

        hideTyping();
        schedulePolling(IDLE_POLL);
    }

    function schedulePolling(interval) {
        if (pollingTimer) {
            clearInterval(pollingTimer);
        }

        pollingTimer = setInterval(function () {
            /*
             * Au-delà d'une minute sans réponse, on cesse de s'agiter :
             * soit un agent a pris le relais, soit quelque chose ne va
             * pas, et l'indicateur deviendrait trompeur.
             */
            if (awaitingReply && Date.now() - awaitingSince > 60000) {
                awaitingReply = false;
                hideTyping();
                schedulePolling(IDLE_POLL);
                return;
            }

            loadMessages();
        }, interval);
    }

    function startPolling() {
        /*
        * Première récupération immédiate.
        */
        loadMessages();

        schedulePolling(awaitingReply ? FAST_POLL : IDLE_POLL);
    }

    /*
    |--------------------------------------------------------------------------
    | Récupération nouveaux messages
    |--------------------------------------------------------------------------
    */

    async function loadMessages() {
        if (!conversationId) {
            return;
        }

        /*
        * Empêcher plusieurs requêtes simultanées.
        */
        if (loadingMessages) {
            return;
        }

        loadingMessages = true;

        try {
            const response = await fetch(
                `${apiUrl}/widget/conversations/${conversationId}/messages?after_id=${lastMessageId}`,
                {
                    method: 'GET',

                    headers: {
                        'Accept': 'application/json',
                        'X-Widget-Token': token,
                    },

                    cache: 'no-store',
                }
            );

            if (!response.ok) {
                /*
                * Si la conversation n'existe plus,
                * supprimer l'identifiant sauvegardé.
                */
                if (
                    response.status === 404
                ) {
                    localStorage.removeItem(
                        STORAGE_KEY
                    );

                    conversationId = null;

                    stopPolling();

                    return;
                }

                throw new Error(
                    `Erreur HTTP ${response.status}`
                );
            }

            const result =
                await response.json();

            if (
                !Array.isArray(
                    result.messages
                )
            ) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Traiter les nouveaux messages
            |--------------------------------------------------------------------------
            */

            result.messages.forEach(
                (message) => {

                    if (!message || !message.id) {
                        return;
                    }

                    const messageId =
                        Number(message.id);

                    /*
                    * Protection contre les doublons.
                    */
                    if (
                        messageId <=
                        lastMessageId
                    ) {
                        return;
                    }

                    if (
                        message.sender_type ===
                        'ai'
                    ) {
                        /*
                         * L'indicateur doit disparaître AVANT l'ajout :
                         * sinon la réponse s'afficherait au-dessus des
                         * trois points encore animés.
                         */
                        replyReceived();

                        addMessage(
                            'ai',
                            message.content,
                            message.attachments
                        );
                    }

                    if (
                        message.sender_type ===
                        'agent'
                    ) {
                        replyReceived();

                        addMessage(
                            'agent',
                            message.content,
                            message.attachments
                        );
                    }

                    /*
                    * Le message client est déjà
                    * affiché au moment de l'envoi.
                    */
                    if (
                        message.sender_type ===
                        'client'
                    ) {
                        // Rien à afficher.
                    }

                    /*
                    * Toujours avancer lastMessageId.
                    */
                    lastMessageId =
                        messageId;
                }
            );

            /*
            |--------------------------------------------------------------------------
            | Transfert humain
            |--------------------------------------------------------------------------
            */

            if (
                result.ai_enabled === false &&
                !humanTransferShown
            ) {
                humanTransferShown = true;

                showHumanTransfer();
            }

        } catch (error) {
            console.error(
                '[AI Widget] Erreur récupération messages:',
                error
            );

        } finally {
            loadingMessages = false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Arrêter le polling
    |--------------------------------------------------------------------------
    */

    function stopPolling() {
        if (pollingTimer) {
            clearInterval(
                pollingTimer
            );

            pollingTimer = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Ajouter un message
    |--------------------------------------------------------------------------
    */

    function addMessage(
        sender,
        content,
        attachments
    ) {
        const messages =
            document.getElementById(
                'ai-widget-messages'
            );

        if (!messages) {
            return;
        }

        const message =
            document.createElement(
                'div'
            );

        message.className =
            `ai-widget-message ${sender}`;

        /*
         * textContent et non innerHTML : le contenu vient du modèle,
         * il ne doit jamais être interprété comme du HTML.
         */
        message.textContent =
            content || '';

        messages.appendChild(
            message
        );

        /*
         * Photos jointes, affichées sous le message.
         */
        if (Array.isArray(attachments) && attachments.length) {
            const gallery = document.createElement('div');

            gallery.className =
                `ai-widget-gallery ${sender}`
                + (attachments.length === 1 ? ' single' : '');

            attachments.forEach(function (attachment) {
                if (!attachment || !attachment.url) {
                    return;
                }

                const figure = document.createElement('figure');

                figure.className = 'ai-widget-photo';

                const image = document.createElement('img');

                image.src = attachment.url;
                image.alt = attachment.caption || '';
                image.loading = 'lazy';

                /*
                 * Ouvrir la photo en grand dans un nouvel onglet : sur
                 * mobile, la vignette ne suffit pas à juger d'une
                 * chambre ou d'un plat.
                 */
                image.addEventListener('click', function () {
                    window.open(attachment.url, '_blank', 'noopener');
                });

                figure.appendChild(image);

                if (attachment.caption) {
                    const caption = document.createElement('figcaption');

                    caption.textContent = attachment.caption;

                    figure.appendChild(caption);
                }

                gallery.appendChild(figure);
            });

            if (gallery.childElementCount) {
                messages.appendChild(gallery);
            }
        }

        messages.scrollTop =
            messages.scrollHeight;
    }

    /*
    |--------------------------------------------------------------------------
    | Notification transfert humain
    |--------------------------------------------------------------------------
    */

    function showHumanTransfer() {
        const messages =
            document.getElementById(
                'ai-widget-messages'
            );

        if (!messages) {
            return;
        }

        /*
        * Protection supplémentaire contre
        * les doublons de notification.
        */
        if (
            messages.querySelector(
                '.ai-widget-transfer'
            )
        ) {
            return;
        }

        const notice =
            document.createElement(
                'div'
            );

        notice.className =
            'ai-widget-transfer';

        notice.textContent =
            'Votre demande a été transférée à un agent humain.';

        messages.appendChild(
            notice
        );

        messages.scrollTop =
            messages.scrollHeight;
    }

    /*
    |--------------------------------------------------------------------------
    | Afficher erreur
    |--------------------------------------------------------------------------
    */

    function showError(message) {
        const messages =
            document.getElementById(
                'ai-widget-messages'
            );

        if (!messages) {
            return;
        }

        const error =
            document.createElement(
                'div'
            );

        error.className =
            'ai-widget-error';

        error.textContent =
            message;

        messages.appendChild(
            error
        );

        messages.scrollTop =
            messages.scrollHeight;
    }

    /*
    |--------------------------------------------------------------------------
    | Initialisation
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Identité visuelle
    |--------------------------------------------------------------------------
    |
    | Le widget est posé sur le site d'un client : il doit porter SES
    | couleurs, pas les nôtres. La configuration est chargée après
    | l'affichage, pour que la bulle apparaisse sans attendre le réseau.
    |
    */

    async function applyBranding() {
        try {
            const response = await fetch(
                `${apiUrl}/widget/config`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Widget-Token': token,
                    },
                    cache: 'no-store',
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            const branding = data.branding;

            if (!branding) {
                return;
            }

            const root = document.getElementById('ai-service-widget');

            if (!root) {
                return;
            }

            /*
             * La couleur est validée côté serveur au format #RRGGBB.
             * On la revalide ici : ce script s'exécute sur une page
             * tierce, et rien de ce qui vient du réseau n'entre dans
             * une feuille de style sans contrôle.
             */
            if (/^#[0-9A-Fa-f]{6}$/.test(branding.color || '')) {
                root.style.setProperty('--aiw-brand', branding.color);
                root.style.setProperty(
                    '--aiw-brand-dark',
                    shade(branding.color, -18)
                );
            }

            const title = document.getElementById('ai-widget-title');

            if (title && branding.name) {
                title.textContent = branding.name;
            }

            const welcome = document.getElementById('ai-widget-welcome-text');

            if (welcome && branding.welcome) {
                welcome.textContent = branding.welcome;
            }

            /*
             * Logo : injecté comme source d'image, jamais comme HTML.
             */
            const holder = document.getElementById('ai-widget-logo');

            if (holder && branding.logo) {
                const image = document.createElement('img');

                image.src = branding.logo;
                image.alt = '';
                image.className = 'ai-widget-logo-image';

                holder.innerHTML = '';
                holder.appendChild(image);
            }
        } catch (error) {
            /*
             * Sans configuration, le widget garde son apparence par
             * défaut. Mieux vaut un widget neutre qu'un widget absent.
             */
            console.warn('[AI Widget] Identité visuelle indisponible.');
        }
    }

    /*
     * Éclaircit ou assombrit une couleur hexadécimale.
     */
    function shade(hex, percent) {
        const value = parseInt(hex.slice(1), 16);

        const channels = [
            (value >> 16) & 255,
            (value >> 8) & 255,
            value & 255,
        ].map(function (channel) {
            const shifted = Math.round(channel * (1 + percent / 100));

            return Math.max(0, Math.min(255, shifted));
        });

        return '#' + channels
            .map(function (channel) {
                return channel.toString(16).padStart(2, '0');
            })
            .join('');
    }

    function initializeWidget() {
        createWidget();

        applyBranding();

        /*
        * Vérifier si une conversation existe déjà.
        */
        restoreConversation();
    }

    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initializeWidget
        );
    } else {
        initializeWidget();
    }

})();



