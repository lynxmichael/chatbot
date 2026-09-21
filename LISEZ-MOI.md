# Pourquoi l'assistant refusait d'envoyer des photos

## Installation

```bash
php artisan migrate
php artisan config:clear
php artisan queue:restart
npm run build
php artisan test
```

**`queue:restart` est indispensable** : le worker garde l'ancien prompt
en mémoire.

**197 tests, 532 assertions.**

## Le diagnostic

Deux défauts se cumulaient.

**Une liste d'outils figée.** Quand la page Autopilot est enregistrée,
elle stockait la liste des outils cochés à cet instant. Cette liste
était un réglage propre à l'entreprise — et les réglages propres
passent devant la formule.

Conséquence : votre page avait été enregistrée avant que l'envoi de
photos existe. Votre liste ne le contenait pas, et passer en Business
ne changeait rien puisque la liste figée l'emportait.

**Une limite inventée.** Privé de l'outil, l'assistant a comblé le vide
en affirmant « il m'est impossible d'afficher des images depuis cette
fenêtre ». C'est faux, et c'est le genre de phrase qui fait perdre un
client.

## La correction

**On stocke ce qui est coupé, plus ce qui est permis.**

- la formule fixe le plafond : ce que l'entreprise a payé ;
- l'entreprise coupe ensuite ce qu'elle ne veut pas.

Un outil ajouté plus tard arrive donc actif, et monter en gamme
débloque réellement ce qu'on paie.

La migration convertit les listes existantes en conservant les choix
réels : ce qui avait été volontairement décoché reste coupé, ce qui
n'existait pas encore arrive actif.

**Un outil hors formule est verrouillé** dans la page Autopilot, avec la
mention « hors formule ». Cocher la case en trichant sur le formulaire
ne donne rien : le serveur recoupe avec la formule.

**Une consigne interdit d'inventer la limite.** Quand une fiche n'a pas
de photo, l'assistant dit qu'il n'en a pas encore et décrit avec les
informations disponibles. Cette consigne n'apparaît que pour les
entreprises qui ont l'outil, et jamais au téléphone.

## Pour que les photos s'affichent

Le code ne suffit pas : **la fiche doit porter des photos**.

1. `/knowledge` → ouvrez la fiche de la chambre vue sur mer ;
2. section Photos en bas → ajoutez-les avec une légende précise,
   par exemple « Chambre Deluxe, balcon, vue sur mer » ;
3. posez de nouveau la question dans le widget.

La légende compte : c'est ce que l'assistant lit pour choisir la bonne
image.
