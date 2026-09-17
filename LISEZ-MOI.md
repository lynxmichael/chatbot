# Nuit, conseillers occupés, et transferts

## Réponse courte

Oui, l'IA répond aux appels 24h/24 — elle le faisait déjà. Ce qui ne
marchait pas, c'est ce qui se passait **quand elle décidait de passer la
main**.

## Le trou

`escalate_to_human` choisissait un agent et le contrôleur vocal
composait son numéro, sans jamais vérifier si quelqu'un pouvait
décrocher.

À deux heures du matin, cela donnait : « Je vous mets en relation, ne
quittez pas », puis vingt-cinq secondes de sonnerie dans le vide, puis
un raccrochage au nez du client. C'est pire que de ne pas décrocher du
tout.

Le même problème existait en journée, tous conseillers occupés.

## Ce qui change

**`BusinessHours`** répond à deux questions distinctes qu'on confond
facilement : le service est-il ouvert, et y a-t-il un conseiller
réellement libre ? Un agent déjà en ligne ou en sonnerie ne compte pas.

**Le prompt** annonce désormais la situation du moment. Quand personne
ne peut décrocher, l'IA reçoit une consigne explicite : ne jamais dire
« je vous passe un conseiller », rassembler les informations, ouvrir un
ticket, annoncer un rappel avec une heure.

**`escalate_to_human`** distingue deux issues :

- `immediate` — un conseiller est libre, la mise en relation se fait ;
- `deferred` — personne ne peut décrocher : un ticket est ouvert et un
  rappel est programmé à la réouverture, que le moteur de relances
  existant enverra tout seul.

**Le contrôleur vocal** ne compose plus jamais un numéro que personne ne
décrochera. Il annonce le rappel et raccroche proprement.

## Ce que le client entend, à 2h du matin

> « Votre demande est enregistrée sous le numéro TCK-4F2A9B1C. Un
> conseiller vous rappelle aujourd'hui à 8h. »

Plutôt qu'une sonnerie dans le vide.

## Horaires par organisation

Les horaires globaux de `config/ai.php` peuvent être redéfinis par
entreprise :

```php
$organization->update([
    'ai_settings' => array_merge($organization->ai_settings ?? [], [
        'business_hours' => [
            'days' => [1, 2, 3, 4, 5, 6],
            'start' => '07:30',
            'end' => '19:00',
        ],
    ]),
]);
```

## Tests

`AfterHoursTest` : 9 tests. Ouverture et fermeture, saut du dimanche,
formulation « aujourd'hui à 8h » / « demain à 8h », agent déjà en ligne
qui ne compte pas, rappel programmé hors des heures, transfert immédiat
quand quelqu'un est libre.

**Suite complète : 84 tests, 172 assertions.**
