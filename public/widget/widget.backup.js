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
                    <div>
                        <strong>AI Service Client</strong>
                        <small>Service client</small>
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
                        <p>
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

                <button type="submit">
                    Envoyer
                </button>
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
                        addMessage(
                            'ai',
                            message.content
                        );
                    }

                    if (
                        message.sender_type ===
                        'agent'
                    ) {
                        addMessage(
                            'agent',
                            message.content
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

    function startPolling() {
        if (pollingTimer) {
            clearInterval(
                pollingTimer
            );
        }

        /*
        * Première récupération immédiate.
        */
        loadMessages();

        /*
        * Puis toutes les 3 secondes.
        */
        pollingTimer = setInterval(
            loadMessages,
            3000
        );
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
                        addMessage(
                            'ai',
                            message.content
                        );
                    }

                    if (
                        message.sender_type ===
                        'agent'
                    ) {
                        addMessage(
                            'agent',
                            message.content
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
        content
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

        message.textContent =
            content || '';

        messages.appendChild(
            message
        );

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

    function initializeWidget() {
        createWidget();

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
