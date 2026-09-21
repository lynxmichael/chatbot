# Une IA plus rapide

## Installation

```bash
php artisan config:clear
php artisan queue:restart
php artisan test
```

**`queue:restart` est indispensable.** Sans lui, le worker continue avec
l'ancien code et rien ne change.

Rechargez aussi la page du widget en vidant le cache (Ctrl+Maj+R) :
`widget.js` a changé.

**232 tests, 631 assertions.**

## D'où venaient les 26 secondes

Chaque message déclenchait trois à quatre appels successifs au modèle,
chacun de plusieurs secondes :

1. chercher dans les fiches ;
2. envoyer les photos, le cas échéant ;
3. **enregistrer l'analyse de la conversation** — imposé par le prompt ;
4. rédiger la réponse.

Chaque appel renvoyait les mêmes 4 000 jetons d'instructions, sans
cache. Puis le widget attendait jusqu'à trois secondes avant de
récupérer la réponse, sans rien afficher.

## Ce qui change

| | Avant | Maintenant |
|---|---|---|
| Question courante | 3 appels | **1 appel** |
| Demande de photos | 4 appels | **2 appels** |
| Instructions | relues à chaque appel | en cache |
| Attente affichée | fenêtre immobile | « en train d'écrire » |
| Détection d'une réponse | toutes les 3 s | chaque seconde |

**La recherche se fait avant d'interroger le modèle.** Elle est locale
et quasi instantanée : ses résultats sont transmis d'emblée. Pour une
question courante, le modèle répond dès le premier appel. Les photos
trouvées lui sont annoncées avec leurs identifiants, il peut les envoyer
sans chercher d'abord. L'outil de recherche reste à sa disposition s'il
doit reformuler.

**L'analyse part en arrière-plan.** Intention, sentiment, résumé : ces
données ne servent qu'à la supervision et au dossier des agents. Le
client n'a pas à les attendre. Elles sont calculées après la réponse,
par un modèle plus petit et plus rapide.

**Les instructions sont mises en cache** chez Anthropic. Relues en
cache, elles sont traitées nettement plus vite et coûtent environ dix
fois moins. L'heure affichée à l'assistant est arrondie à l'heure : à la
minute près, elle invalidait le cache chaque minute.

**Le widget montre qu'on s'occupe du client** dès l'envoi, et vérifie
chaque seconde tant qu'une réponse est attendue.

## Vérifier le gain

Chaque réponse inscrit désormais son chronométrage dans
`storage/logs/laravel.log` :

```
Réponse IA produite. {"total_seconds":4.2,"calls":1,
  "timings":[{"step":1,"seconds":4.2,"tools":[],"cached_tokens":3850}]}
```

- `calls` doit valoir 1 pour une question simple ;
- `cached_tokens` supérieur à zéro confirme que le cache fonctionne —
  il sera à zéro au tout premier message, puis rempli ensuite.

## Pour aller plus loin

Le modèle lui-même reste le poste le plus lourd. Dans `.env` :

```env
ANTHROPIC_MODEL=claude-sonnet-4-6
AI_ANALYSIS_MODEL=claude-haiku-4-5-20251001
```

Un modèle plus petit pour les réponses irait plus vite encore, au prix
d'une compréhension moins fine des demandes complexes. Mesurez avec le
journal avant de trancher : sur des questions simples, l'écart de
qualité est souvent invisible.
