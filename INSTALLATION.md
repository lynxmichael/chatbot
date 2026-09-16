# Système IA complet — installation

58 fichiers, arborescence identique à ton projet. Copie ce dossier
par-dessus `ai-service-client/` en écrasant les fichiers existants.

---

## Installation

```bash
php artisan migrate
php artisan config:clear
php artisan route:clear
npm run build
php artisan queue:restart
```

Deux processus doivent tourner en permanence :

```bash
php artisan queue:work --tries=3
```

Et un cron unique, qui déclenche tout le reste :

```cron
* * * * * cd /chemin/du/projet && php artisan schedule:run >> /dev/null 2>&1
```

Sans ce cron, les relances ne partent pas et la supervision ne
tourne pas. C'est la seule dépendance système ajoutée.

---

## Ce qui tourne automatiquement

| Fréquence | Commande | Rôle |
|-----------|----------|------|
| 5 min | `ai:follow-ups` | envoie les relances arrivées à échéance |
| 15 min | `ai:supervise --notify` | détecte les dérapages, alerte les responsables |
| 1 h | `ai:auto-close` | ferme les tickets résolus sans retour client |

Chacune se lance aussi à la main pour tester :

```bash
php artisan ai:supervise --organization=1
php artisan ai:follow-ups
php artisan ai:auto-close
```

---

## Variables d'environnement

Rien d'obligatoire au-delà de `ANTHROPIC_API_KEY`. Tout le reste a une
valeur par défaut.

```env
# Comportement général
AI_AUTOPILOT_LEVEL=assist      # off | suggest | assist | auto
AI_MAX_STEPS=6                 # étapes de réflexion par message écrit
AI_HISTORY_LIMIT=30            # messages réinjectés dans le contexte

# Suivi de commandes (facultatif)
AI_ORDERS_URL=https://ton-erp.com/api/orders/{reference}
AI_ORDERS_TOKEN=xxxxx

# Téléphone (facultatif)
AI_VOICE_LANGUAGE=fr-FR
AI_VOICE_TTS=Google.fr-FR-Standard-A
AI_VOICE_FALLBACK_NUMBER=+2250700000000
AI_VOICE_MAX_STEPS=3
```

---

## 1. L'IA répond seule

Le changement de fond par rapport à l'ancien code : l'IA ne reçoit plus
la base de connaissances dans son prompt, elle la **cherche** avec ses
outils, puis agit, puis répond. Boucle « réflexion → outil → vérification
→ action → réponse », jusqu'à 6 étapes par message.

Les 9 outils :

| Outil | Type | Rôle |
|-------|------|------|
| `search_knowledge` | lecture | recherche par mots-clés, titre pondéré |
| `get_client_profile` | lecture | identité, tickets, appels, historique |
| `get_order_status` | lecture | interroge l'ERP via `OrderGateway` |
| `get_ticket_status` | lecture | consultation par numéro `TCK-XXXXXXXX` |
| `create_ticket` | écriture | catégorie, priorité, SLA, agent — anti-doublon |
| `update_ticket` | écriture | statut, priorité, résolution |
| `schedule_follow_up` | écriture | relance de 1 h à 7 jours |
| `escalate_to_human` | écriture | transfert + dossier préparé + ticket |
| `record_insights` | écriture | intention, sentiment, résumé, confiance |

Pour ajouter une capacité : une classe qui implémente `Tool`, une ligne
dans le constructeur de `ToolRegistry`, une entrée dans `allowed_actions`.

---

## 2. Le téléphone

L'IA décroche, comprend, cherche dans le CRM, répond, et transfère
quand il faut. Même `AgentRunner`, mêmes outils, prompt adapté à l'oral.

### Branchement Twilio

Sur le numéro entrant, configure :

```
Voix    POST  https://ton-domaine/api/voice/{token}/incoming
Statut  POST  https://ton-domaine/api/voice/{token}/status
```

`{token}` est le `widget_token` de l'organisation, déjà présent en base.

La transcription et la synthèse vocale sont assurées par Twilio
(`Gather input="speech"` et `Say`) : aucun service tiers en plus.

### Ce qui se passe pendant un appel

1. Appel entrant → client retrouvé par son numéro, ou créé ;
   conversation `phone` + enregistrement dans `calls`.
2. Le client parle → transcription envoyée à l'agent IA → réponse lue
   à voix haute → nouvelle écoute.
3. L'IA décide de passer la main → annonce + `<Dial>` vers le conseiller.
4. Fin d'appel → durée, statut, transcription complète stockée dans
   `calls.transcript`.

### La contrainte à connaître

L'opérateur attend la réponse HTTP pendant que le client patiente en
ligne. La boucle est donc raccourcie à 3 étapes au téléphone
(`AI_VOICE_MAX_STEPS`) et le traitement reste synchrone. Si tes réponses
vocales arrivent trop lentement, c'est ce nombre qu'il faut baisser, pas
le timeout.

Après trois incompréhensions d'affilée, l'appel bascule
automatiquement vers un humain plutôt que de tourner en rond.

### Sécurité

Le jeton dans l'URL identifie l'organisation. Pour de la production,
ajoute la vérification de signature Twilio (`X-Twilio-Signature`) en
middleware : le jeton seul protège contre les appels accidentels, pas
contre quelqu'un qui connaîtrait l'URL.

---

## 3. L'email

```
POST  https://ton-domaine/api/email/{token}/inbound
```

À brancher sur Postmark, Mailgun ou SendGrid. Les trois formats de
champs sont acceptés.

Un email entrant devient un message dans une conversation, puis suit
exactement le même chemin que le chat. Les réponses de l'IA repartent
en vrai email via `DeliverOutboundMessage`.

Deux détails qui comptent :

- l'historique cité sous la réponse est retiré avant l'envoi au modèle,
  sinon chaque échange renvoie toute la conversation et fait exploser
  le contexte ;
- un en-tête `X-Conversation-Id` est posé sur nos envois, ce qui permet
  de rattacher la réponse du client à la bonne conversation. À défaut,
  le rattachement se fait sur une conversation email ouverte de moins
  de sept jours.

---

## 4. Les relances

`schedule_follow_up` crée une ligne dans `follow_ups`. La commande
planifiée la reprend à l'échéance.

L'IA ne renvoie pas le message prévu à l'avance : elle **revérifie la
situation** avec ses outils au moment de la relance, puis rédige. Une
commande livrée entre-temps ne déclenche donc pas un « nous vérifions
votre livraison » absurde.

Trois cas d'annulation automatique :

- l'Autopilot est repassé en `off` ou `suggest` ;
- un agent humain a repris la conversation ;
- l'organisation a été suspendue.

---

## 5. La supervision

`ai:supervise` analyse le service client et crée des alertes dans
`ai_insights`. Neuf détections :

| Type | Déclencheur |
|------|-------------|
| `sla_breached` | échéance de première réponse dépassée |
| `sla_at_risk` | plus de 80 % du délai consommé |
| `ticket_stale` | aucune activité depuis 48 h |
| `ticket_unassigned` | ouvert depuis 30 min sans responsable |
| `client_waiting` | dernier message du client, sans réponse depuis 2 h |
| `unhappy_client` | sentiment négatif ou agacé détecté |
| `missed_call` | appel manqué sans rappel dans les 2 h |
| `repeated_requests` | 3 tickets ou plus, même catégorie, 30 jours |
| `agent_overloaded` | charge au-dessus de la capacité déclarée |

**La détection est déterministe, pas confiée au modèle.** Une alerte
« SLA dépassé » doit être vraie à 100 %, sinon les agents cessent de
les lire au bout d'une semaine. Le sentiment fait exception : il vient
de l'analyse déjà faite par l'IA pendant les conversations.

Deux mécanismes évitent le bruit :

- le `fingerprint` empêche qu'un même ticket oublié génère une alerte
  à chaque passage du scan ;
- une alerte dont la cause a disparu se referme toute seule.

Seules les alertes **graves et nouvelles** sont notifiées aux
responsables.

---

## 6. La console Autopilot

Nouvelle entrée dans le menu, visible uniquement par le propriétaire :

- **Réglages** (`/autopilot`) — niveau, identité de l'assistant, liste
  des actions autorisées, seuils
- **À valider** (`/autopilot/approvals`) — chaque action proposée avec
  ses paramètres exacts, à valider ou refuser
- **Alertes** (`/autopilot/insights`) — ce qui dérape, trié par gravité

### Les quatre niveaux

| Niveau | Lecture | Écriture | Réponse au client |
|--------|---------|----------|-------------------|
| `off` | non | non | aucune |
| `suggest` | oui | non | brouillon interne |
| `assist` | oui | file de validation | envoyée |
| `auto` | oui | exécutée directement | envoyée |

Vérifié sur les 9 outils :

```
off      → exécute 0, valide 0, refuse 9
suggest  → exécute 4, valide 0, refuse 5
assist   → exécute 6, valide 3, refuse 0
auto     → exécute 9, valide 0, refuse 0
```

En `assist`, `escalate_to_human` et `record_insights` s'exécutent quand
même. C'est délibéré : bloquer un transfert vers un humain en attendant
qu'un responsable clique serait l'inverse du but recherché.

### Le garde-fou qui compte

Le prompt indique à l'IA le statut réel de chaque outil. Quand
`create_ticket` est en file de validation, elle le sait et écrit
« votre demande est transmise » au lieu de « votre ticket est ouvert ».
C'est ce qui l'empêche d'annoncer des actions qui n'ont pas eu lieu —
le défaut le plus coûteux d'un service client automatisé.

Quand tu valides une action, la politique n'est pas rejouée : ta
décision fait autorité. C'est tout l'intérêt du mode `assist` — l'IA
propose, tu tranches.

---

## Sécurité

Appliqué dans chaque outil, sans exception :

- toute requête est filtrée par `organization_id` — une entreprise ne
  peut jamais lire les données d'une autre ;
- `get_ticket_status` et `update_ticket` vérifient que le ticket
  appartient bien au client en ligne ;
- une erreur d'outil ne casse pas la conversation : elle est renvoyée au
  modèle avec la consigne de ne rien promettre ;
- si la passerelle commandes n'est pas branchée, l'IA dit qu'elle ne
  sait pas au lieu d'inventer un statut de livraison.

Le journal `ai_actions` enregistre chaque appel d'outil avec son entrée,
sa sortie, son statut et le niveau d'Autopilot en vigueur.

---

## Premier test

Ordre conseillé, du moins risqué au plus engageant.

**1. Le chat en mode brouillon.** Passe l'organisation en `suggest`,
envoie un message, et lis ce que l'IA aurait répondu sans que rien ne
parte au client :

```sql
SELECT sender_type, content FROM messages ORDER BY id DESC LIMIT 5;
SELECT tool, status, created_at FROM ai_actions ORDER BY id DESC LIMIT 20;
```

**2. Passe en `assist`.** Les réponses partent, les actions attendent ta
validation dans `/autopilot/approvals`.

**3. La supervision**, à vide, pour voir ce qu'elle remonte sur tes
données actuelles :

```bash
php artisan ai:supervise --organization=1
```

**4. Le téléphone** en dernier, une fois le reste calibré. C'est là que
les erreurs se voient le plus.

---

## Ce que je n'ai pas pu tester

J'ai vérifié dans un conteneur que tout se charge, que les routes et les
commandes s'enregistrent, que les 4 niveaux d'Autopilot donnent les bonnes
décisions sur les 9 outils, que les SLA se calculent juste sur des cas
limites (vendredi soir, dimanche) et que les 3 pages Vue compilent.

Ce qui reste à valider chez toi, parce que ça demande une vraie clé API,
une vraie base et un vrai numéro :

- un échange complet avec l'API Anthropic, outils compris ;
- la latence réelle du canal vocal chez ton opérateur ;
- le format exact du webhook de ton fournisseur d'emails ;
- le comportement sur ta base de connaissances réelle — c'est elle qui
  détermine la qualité des réponses, bien plus que le code.
