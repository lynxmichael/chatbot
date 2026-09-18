# Photos dans la base de connaissances

## Installation

```bash
php artisan migrate
php artisan storage:link
php artisan config:clear
php artisan queue:restart
npm run build
```

**146 tests, 332 assertions.**

## Comment ça marche

Les photos se rattachent à une fiche, jamais à l'assistant directement.
On ouvre une fiche — « Chambre Deluxe vue mer », « Attiéké poisson » —
et on y ajoute ses photos avec une légende.

Ensuite, quand un client écrit « je peux voir les chambres ? » :

1. l'assistant cherche dans les fiches ;
2. la recherche lui annonce les photos disponibles, avec leurs
   identifiants et leurs légendes ;
3. il choisit les bonnes et les envoie ;
4. le client les voit sous la réponse, cliquables pour les agrandir.

**L'assistant ne peut envoyer que des identifiants retournés par une
recherche.** C'est ce qui garantit qu'un client demandant une chambre ne
reçoit jamais la photo d'un plat, ni celle d'une autre entreprise.

## Les garde-fous

Vérifiés par des tests, parce qu'une photo envoyée au mauvais client ne
se rattrape pas :

| Situation | Comportement |
|-----------|--------------|
| Photo d'une autre entreprise | refusée |
| Fiche désactivée (offre terminée) | refusée |
| Au téléphone | refusée, avec consigne de décrire à l'oral |
| Plus de quatre photos | tronqué à quatre |
| Identifiant inventé | refusé |

La limite de quatre est délibérée : au-delà, la conversation devient un
catalogue illisible sur un téléphone.

## La légende compte

C'est elle que l'assistant lit pour choisir. « Chambre Deluxe, lit king
size, vue mer » lui permet de répondre juste à « vous avez des chambres
avec vue ? ». Une photo sans légende porte le titre de sa fiche, ce qui
est moins précis.

## Une décision commerciale à valider

J'ai placé `send_images` dans les formules **pro** et **business**,
pas dans le gratuit.

Pour un hôtel ou un restaurant, montrer ses chambres et ses plats est
exactement ce qui fait vendre : c'est un bon argument d'abonnement. Mais
si tu préfères l'offrir pour rendre la démonstration plus convaincante,
ajoute `'send_images'` à la liste `allowed_actions` de la formule
`free`, dans `config/ai.php`.

## Format des photos

JPG, PNG ou WebP, 3 Mo maximum, huit par fiche. Les vignettes sont
recadrées automatiquement ; une photo horizontale rend mieux qu'une
verticale dans la conversation.
