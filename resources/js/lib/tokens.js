/*
|--------------------------------------------------------------------------
| Jetons d'état
|--------------------------------------------------------------------------
|
| Une seule définition des libellés et des couleurs pour les statuts, les
| priorités et les canaux. Toutes les pages doivent lire ici : c'est ce qui
| garantit qu'un ticket « urgent » a la même couleur dans la liste, sur le
| tableau de bord et dans une notification.
|
| Les classes sont écrites en toutes lettres pour que Tailwind les détecte
| lors du scan du contenu.
|
*/

const tone = {
    slate: 'bg-night-50 text-night-600 ring-night-200',
    blue: 'bg-sky-50 text-sky-700 ring-sky-200',
    violet: 'bg-brand-50 text-brand-700 ring-brand-200',
    amber: 'bg-amber-50 text-amber-700 ring-amber-200',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rose: 'bg-rose-50 text-rose-700 ring-rose-200',
    green: 'bg-green-50 text-green-700 ring-green-200',
};

const dot = {
    slate: 'bg-night-400',
    blue: 'bg-sky-500',
    violet: 'bg-brand-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    green: 'bg-green-500',
};

/*
|--------------------------------------------------------------------------
| Statuts
|--------------------------------------------------------------------------
*/

export const statusTokens = {
    open: { label: 'Ouvert', tone: 'blue' },
    pending: { label: 'En attente', tone: 'amber' },
    in_progress: { label: 'En cours', tone: 'violet' },
    resolved: { label: 'Résolu', tone: 'emerald' },
    closed: { label: 'Fermé', tone: 'slate' },

    /* Statuts d'appel */
    answered: { label: 'Répondu', tone: 'emerald' },
    missed: { label: 'Manqué', tone: 'rose' },
    busy: { label: 'Occupé', tone: 'amber' },
    failed: { label: 'Échec', tone: 'rose' },
    cancelled: { label: 'Annulé', tone: 'slate' },
};

/*
|--------------------------------------------------------------------------
| Priorités
|--------------------------------------------------------------------------
*/

export const priorityTokens = {
    low: { label: 'Faible', tone: 'slate' },
    normal: { label: 'Normale', tone: 'blue' },
    high: { label: 'Élevée', tone: 'amber' },
    urgent: { label: 'Urgente', tone: 'rose' },
};

/*
|--------------------------------------------------------------------------
| Canaux
|--------------------------------------------------------------------------
*/

export const channelTokens = {
    web: { label: 'Web', tone: 'violet', icon: '🌐' },
    widget: { label: 'Widget', tone: 'violet', icon: '💬' },
    whatsapp: { label: 'WhatsApp', tone: 'green', icon: '📱' },
    email: { label: 'Email', tone: 'slate', icon: '✉️' },
    phone: { label: 'Téléphone', tone: 'amber', icon: '📞' },
};

/*
|--------------------------------------------------------------------------
| Types d'appel
|--------------------------------------------------------------------------
*/

export const callTypeTokens = {
    incoming: { label: 'Entrant', tone: 'blue' },
    outgoing: { label: 'Sortant', tone: 'violet' },
};

/*
|--------------------------------------------------------------------------
| Résolution
|--------------------------------------------------------------------------
*/

const registries = {
    status: statusTokens,
    priority: priorityTokens,
    channel: channelTokens,
    callType: callTypeTokens,
};

export const resolveToken = (kind, value) => {
    const registry = registries[kind] ?? {};

    const token = registry[value] ?? {
        label: value ?? '—',
        tone: 'slate',
    };

    return {
        ...token,
        classes: tone[token.tone] ?? tone.slate,
        dotClass: dot[token.tone] ?? dot.slate,
    };
};

export default resolveToken;
