# Suivi automatique des tickets (point 5 de la feuille de route)

Ce que fait ce module :

1. **Alerte de dépassement de SLA** — toutes les 15 minutes, une
   commande vérifie les tickets ouverts dont le délai SLA est dépassé
   et notifie l'agent assigné (visible dans la cloche de notifications,
   déjà présente dans ton interface — aucune modification front
   nécessaire, elle affiche déjà génériquement `data.ticket_id`).
2. **Fermeture automatique** — chaque nuit à 2h, les tickets passés en
   `resolved` depuis plus de 3 jours (réglable) sans nouvelle activité
   sont automatiquement fermés (`closed`).

## Fichiers NOUVEAUX

- `database/migrations/2026_09_15_100000_add_sla_breached_notified_at_to_tickets_table.php`
  — ajoute une colonne pour ne notifier qu'une seule fois par ticket.
- `app/Notifications/TicketSlaBreachedNotification.php`
- `app/Console/Commands/CheckTicketSlaCommand.php` (`php artisan tickets:check-sla`)
- `app/Console/Commands/CloseResolvedTicketsCommand.php` (`php artisan tickets:close-resolved`)

## Fichiers MODIFIÉS

- `app/Models/Ticket.php` — nouvelle colonne ajoutée au fillable/casts
- `config/tickets.php` — ajout de `auto_close_after_days` (3 par défaut,
  réglable via `TICKET_AUTO_CLOSE_DAYS` dans `.env`)
- `routes/console.php` — planification des deux commandes

## Étapes pour intégrer

1. Copie les fichiers dans ton projet.
2. Lance la migration :
   ```
   php artisan migrate
   ```
3. **Important** : pour que ça tourne vraiment, il faut que le
   planificateur Laravel soit actif sur ton serveur. En production,
   ajoute cette tâche cron (une seule fois, quel que soit le nombre de
   commandes planifiées) :
   ```
   * * * * * cd /chemin/vers/ton/projet && php artisan schedule:run >> /dev/null 2>&1
   ```
   En développement local, tu peux à la place laisser tourner :
   ```
   php artisan schedule:work
   ```
4. Tu peux aussi lancer les commandes manuellement pour tester tout de
   suite, sans attendre le planificateur :
   ```
   php artisan tickets:check-sla
   php artisan tickets:close-resolved
   ```

## Ce qui n'est PAS fait — et qui bloque sur une info dont j'ai besoin

Ta feuille de route décrit aussi, pour le suivi automatique : *vérifier
la commande*, *vérifier le statut de livraison*, et *contacter le
système concerné si une intégration existe*. Je n'ai trouvé **aucun
système de commandes/livraison** dans ce projet — ni modèle, ni table,
ni API. Avant de construire cette partie, j'ai besoin de savoir : ces
commandes existent-elles dans un autre système (boutique en ligne,
ERP...) que l'IA devrait interroger ? Si oui, lequel, et as-tu déjà un
accès API à ce système ? Sans ça, je ne peux que construire une
interface pour saisir/consulter des commandes directement dans cette
application — dis-moi ce qui correspond à ta situation réelle.
