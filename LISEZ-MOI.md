# État final — tout en un

113 fichiers : l'état complet et cohérent de tout ce qui a été
construit. Cette archive **remplace toutes les précédentes**.

Les lots successifs se chevauchaient, et un fichier ancien pouvait en
écraser un récent selon l'ordre d'extraction. C'est ce qui vient de se
produire deux fois. Ici, chaque fichier est dans sa version définitive.

## Installation

Copie le contenu par-dessus ton projet, puis :

```bash
php artisan migrate
php artisan config:clear
php artisan route:clear
npm run build
php artisan test
```

Attendu : **84 tests, 172 assertions, tout au vert.**

Si un test échoue encore, c'est qu'un fichier n'a pas été écrasé.

## Pourquoi les deux erreurs précédentes

**`MODIFY COLUMN … ENUM`** — la migration des états d'appel existait en
deux versions ; l'ancienne, propre à MySQL, empêchait toute migration
sous SQLite.

**`makeOrganization() undefined`** — les fabriques de test vivent dans
`tests/Pest.php`, livré dans un lot qui n'a pas été installé. Ce même
lot contenait aussi :

- `VerifyTwilioSignature` (sécurité des webhooks vocaux) ;
- la migration `make_client_phone_nullable`, qui corrige un **bug de
  production** : le widget crée des clients sans téléphone alors que la
  colonne était obligatoire. Toute conversation ou tout appel lancé sans
  numéro échouait.

Les deux sont dans cette archive.

## À faire une fois installé

**Sur le serveur**

```cron
* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
```

```bash
php artisan queue:work --tries=3
```

Sans le cron, ni relances ni supervision. Sans le worker, aucune réponse
de l'IA.

**Dans `.env`**

```env
ANTHROPIC_API_KEY=...
TWILIO_AUTH_TOKEN=...          # avant d'ouvrir la voix
AI_AUTOPILOT_LEVEL=suggest     # pour commencer
```

**Dans l'application**

1. `/knowledge` — importe ta FAQ. Sans fiches, l'assistant ne sait rien.
2. `/agents` — coche les compétences de chacun, sinon le routage par
   compétence ne sert à rien.
3. `/autopilot` — passe en `assist` quand les brouillons te conviennent.

**À supprimer**

```bash
rm public/widget/widget.backup*.js
```

Du code obsolète exposé publiquement.

Et si tes pages deviennent blanches alors que le build vient de passer :

```bash
rm public/hot
```

Ce fichier est créé par `npm run dev` et fait chercher les assets sur un
serveur de développement éteint.

## Ce que contient l'archive

| Domaine | Contenu |
|---------|---------|
| Noyau IA | agent à outils, 9 outils, Autopilot à 4 niveaux, journal |
| Tickets | catégorie, priorité, SLA en heures ouvrées, routage |
| Relances | programmation, exécution, clôture automatique |
| Supervision | 9 détections, alertes, notifications |
| Voix | accueil, sonnerie chez l'agent, transfert, signature Twilio |
| Email | webhook entrant, réponses sortantes |
| Connaissances | liste, écriture, import de masse, questions sans réponse |
| Plafonds | compteurs, quotas, coût estimé, alertes |
| Interface | console unifiée, palette cohérente, widget modernisé |
| Tests | 84 tests, 172 assertions |
