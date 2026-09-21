<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $branding['name'] }} — démonstration</title>

    {{--
        Page publique, mais qui ne doit pas être indexée : elle n'a
        d'intérêt que pour qui a reçu le lien.
    --}}
    <meta name="robots" content="noindex, nofollow">

    <style>
        /*
        |--------------------------------------------------------------------------
        | Page de démonstration
        |--------------------------------------------------------------------------
        |
        | Sert de site hôte fictif pour tester le widget en conditions réelles.
        | Le fond est animé : dégradés en mouvement lent et halos flottants,
        | tout en CSS, sans image ni bibliothèque.
        |
        */

        :root {
            --ink: #0b1220;
            --muted: #475569;
            --brand: {{ $branding['color'] }};
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                "Helvetica Neue", Arial, sans-serif;
            color: var(--ink);
            background: #eef2ff;
            overflow-x: hidden;
        }

        /*
        |--------------------------------------------------------------------------
        | Fond animé
        |--------------------------------------------------------------------------
        |
        | Trois couches : un dégradé de base qui dérive, des halos colorés qui
        | flottent, et un voile de grain très léger pour casser les aplats.
        |
        */

        .backdrop {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            background: linear-gradient(
                135deg,
                #eef2ff 0%,
                #f5f3ff 25%,
                #ecfeff 50%,
                #eff6ff 75%,
                #eef2ff 100%
            );
            background-size: 400% 400%;
            animation: drift 28s ease-in-out infinite;
        }

        @keyframes drift {
            0%,
            100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.5;
            will-change: transform;
        }

        .orb-1 {
            width: 480px;
            height: 480px;
            top: -140px;
            left: -100px;
            background: radial-gradient(circle, #a5b4fc, transparent 70%);
            animation: float-1 22s ease-in-out infinite;
        }

        .orb-2 {
            width: 420px;
            height: 420px;
            top: 30%;
            right: -120px;
            background: radial-gradient(circle, #67e8f9, transparent 70%);
            animation: float-2 26s ease-in-out infinite;
        }

        .orb-3 {
            width: 380px;
            height: 380px;
            bottom: -120px;
            left: 25%;
            background: radial-gradient(circle, #f0abfc, transparent 70%);
            animation: float-3 30s ease-in-out infinite;
        }

        @keyframes float-1 {
            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(120px, 80px) scale(1.12);
            }
        }

        @keyframes float-2 {
            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(-100px, 120px) scale(0.9);
            }
        }

        @keyframes float-3 {
            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(80px, -90px) scale(1.08);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Contenu
        |--------------------------------------------------------------------------
        */

        .shell {
            max-width: 1080px;
            margin: 0 auto;
            padding: 28px 24px 120px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 20px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 24px -16px rgba(11, 18, 32, 0.4);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: var(--brand);
            color: #fff;
            font-size: 13px;
        }

        .topbar nav {
            display: flex;
            gap: 22px;
            font-size: 14px;
            color: var(--muted);
        }

        .hero {
            padding: 96px 0 56px;
            max-width: 720px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.9);
            font-size: 13px;
            color: var(--muted);
        }

        .eyebrow .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            animation: pulse 2.2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%,
            100% {
                opacity: 1;
            }
            50% {
                opacity: 0.45;
            }
        }

        h1 {
            margin: 22px 0 0;
            font-size: clamp(38px, 6vw, 60px);
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        h1 .accent {
            background: linear-gradient(120deg, #4f46e5, #06b6d4);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .lede {
            margin: 20px 0 0;
            font-size: 19px;
            line-height: 1.65;
            color: var(--muted);
            max-width: 560px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px;
            margin-top: 56px;
        }

        .card {
            padding: 22px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.62);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.85);
            box-shadow: 0 10px 30px -20px rgba(11, 18, 32, 0.5);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 40px -22px rgba(11, 18, 32, 0.55);
        }

        .card .icon {
            font-size: 22px;
        }

        .card h3 {
            margin: 12px 0 6px;
            font-size: 15px;
            letter-spacing: -0.01em;
        }

        .card p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: var(--muted);
        }

        .hint {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 56px;
            padding: 12px 18px;
            border-radius: 999px;
            background: rgba(11, 18, 32, 0.9);
            color: #fff;
            font-size: 14px;
        }

        .hint .arrow {
            animation: nudge 1.6s ease-in-out infinite;
        }

        @keyframes nudge {
            0%,
            100% {
                transform: translateX(0);
            }
            50% {
                transform: translateX(5px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        @media (max-width: 640px) {
            .topbar nav {
                display: none;
            }

            .hero {
                padding: 64px 0 40px;
            }
        }
    </style>
</head>

<body>

    <div class="backdrop" aria-hidden="true">
        <span class="orb orb-1"></span>
        <span class="orb orb-2"></span>
        <span class="orb orb-3"></span>
    </div>

    <div class="shell">

        <header class="topbar">
            <div class="brand">
                @if ($branding['logo'])
                    <img
                        src="{{ $branding['logo'] }}"
                        alt=""
                        class="brand-mark"
                        style="object-fit: contain; background: #fff;"
                    >
                @else
                    <span class="brand-mark">{{ $initials }}</span>
                @endif

                {{ $branding['name'] }}
            </div>

            <nav>
                <span>Produits</span>
                <span>Tarifs</span>
                <span>Assistance</span>
            </nav>
        </header>

        {{--
            Rappel discret : cette page est un site fictif. Le vrai
            widget, lui, est exactement celui du client.
        --}}
        <p style="margin: 18px 0 0; font-size: 13px; color: var(--muted);">
            Page de démonstration. Le widget en bas à droite est celui de
            <strong>{{ $branding['name'] }}</strong>, avec ses réglages réels.
        </p>

        <section class="hero">
            <span class="eyebrow">
                <span class="dot"></span>
                Service client ouvert
            </span>

            <h1>
                Une réponse
                <span class="accent">immédiate</span>,
                à toute heure.
            </h1>

            <p class="lede">
                Cette page simule le site d'un client sur lequel le widget est
                installé. Posez une question, ou lancez un appel pour faire
                sonner le poste d'un conseiller.
            </p>
        </section>

        <div class="cards">
            <article class="card">
                <div class="icon">💬</div>
                <h3>Discussion écrite</h3>
                <p>
                    L'assistant cherche l'information et répond seul sur les
                    demandes courantes.
                </p>
            </article>

            <article class="card">
                <div class="icon">📞</div>
                <h3>Appel direct</h3>
                <p>
                    Le poste du conseiller sonne réellement. Vous pouvez
                    raccrocher à tout moment.
                </p>
            </article>

            <article class="card">
                <div class="icon">🎫</div>
                <h3>Suivi automatique</h3>
                <p>
                    Un ticket est ouvert et qualifié dès qu'une demande
                    nécessite un suivi.
                </p>
            </article>
        </div>

        <div class="hint">
            Cliquez sur la bulle en bas à droite
            <span class="arrow">→</span>
        </div>

    </div>

    {{--
        URLs construites depuis l'hôte courant : le widget reste ainsi sur
        la même origine que la page, quel que soit le port ou le domaine.
    --}}

    <script
        src="{{ url('/widget/widget.js') }}"
        data-api="{{ url('/api') }}"
        data-token="{{ $token }}"
    ></script>

</body>
</html>
