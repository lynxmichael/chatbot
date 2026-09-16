/*
|--------------------------------------------------------------------------
| Formatage
|--------------------------------------------------------------------------
*/

/**
 * Durée en secondes → « 4 min 12 s », « 1 h 03 ».
 */
export const formatDuration = (seconds) => {
    const total = Number(seconds) || 0;

    if (total < 60) {
        return `${total} s`;
    }

    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const rest = total % 60;

    if (hours > 0) {
        return `${hours} h ${String(minutes).padStart(2, '0')}`;
    }

    return rest > 0
        ? `${minutes} min ${String(rest).padStart(2, '0')} s`
        : `${minutes} min`;
};

/**
 * Nombre → séparateur d'espace insécable fine (2 480 au lieu de 2480).
 */
export const formatNumber = (value) => {
    return new Intl.NumberFormat('fr-FR').format(Number(value) || 0);
};

/**
 * Date ISO ou « d/m/Y H:i » → « il y a 4 min ».
 */
export const formatRelative = (value) => {
    if (!value) {
        return '—';
    }

    const date = parseDate(value);

    if (!date) {
        return String(value);
    }

    const seconds = Math.round((Date.now() - date.getTime()) / 1000);

    if (seconds < 45) {
        return "à l'instant";
    }

    if (seconds < 3600) {
        return `il y a ${Math.round(seconds / 60)} min`;
    }

    if (seconds < 86400) {
        const hours = Math.round(seconds / 3600);
        return `il y a ${hours} h`;
    }

    const days = Math.round(seconds / 86400);

    if (days < 31) {
        return `il y a ${days} j`;
    }

    return date.toLocaleDateString('fr-FR');
};

/**
 * Accepte l'ISO 8601 de Laravel et le « d/m/Y H:i » déjà formaté
 * par certains contrôleurs.
 */
const parseDate = (value) => {
    if (value instanceof Date) {
        return value;
    }

    const text = String(value);

    const french = text.match(
        /^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?$/
    );

    if (french) {
        const [, day, month, year, hour = '00', minute = '00'] = french;

        return new Date(
            Number(year),
            Number(month) - 1,
            Number(day),
            Number(hour),
            Number(minute)
        );
    }

    const parsed = new Date(text);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

/**
 * Initiales pour les pastilles d'avatar.
 */
export const initials = (name) => {
    if (!name) {
        return '?';
    }

    return String(name)
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
};

/**
 * Variation en pourcentage entre deux valeurs.
 */
export const percentChange = (current, previous) => {
    const now = Number(current) || 0;
    const before = Number(previous) || 0;

    if (before === 0) {
        return now === 0 ? 0 : 100;
    }

    return Math.round(((now - before) / before) * 100);
};
