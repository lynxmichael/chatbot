# Suppression de la mise en forme

## Installation

```bash
php artisan queue:restart
```

**Cette commande est indispensable.** Le worker garde le code PHP en
mémoire : sans redémarrage, la modification n'a aucun effet et les
astérisques reviennent.

Pas de migration, pas de build.

## Ce qui change

Le nettoyage se fait maintenant côté serveur, dans `AgentRunner`, sur
chaque réponse avant enregistrement. Sont retirés :

- le gras et l'italique — `**texte**`, `__texte__`, `*texte*`, `_texte_` ;
- le code — backticks et blocs ``` ;
- les titres en début de ligne — `#`, `##` ;
- les puces `-` et `*`, remplacées par `•` ;
- les liens `[texte](url)`, dont seul le texte est conservé.

La consigne dans le prompt reste, mais elle ne fait plus foi seule. Une
instruction au modèle est une demande, pas une garantie — ce n'est pas
suffisant pour quelque chose que le client voit.

## Ce qui n'est pas touché

Les pièges évités, tous vérifiés par des tests :

- `3 * 4` reste une multiplication ;
- `nom_client` garde son souligné ;
- `25 000 FCFA` et `TCK-4F2A9B1C` sont intacts ;
- les emojis passent.

## Si les astérisques persistent

Regarde la fiche concernée dans `/knowledge`. Si son contenu contient
lui-même des astérisques, l'assistant les recopie — et il a raison de le
faire, c'est ce que tu as écrit.

## Tests

`PlainTextTest` : 8 tests. Suite complète : **92 tests, 183 assertions.**
