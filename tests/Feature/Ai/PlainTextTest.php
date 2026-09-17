<?php

use App\Services\AI\AgentRunner;

/*
|--------------------------------------------------------------------------
| Nettoyage de la mise en forme
|--------------------------------------------------------------------------
|
| La fenêtre de discussion affiche le texte brut : un astérisque oublié
| s'affiche tel quel au client. Une consigne dans le prompt ne suffit
| pas pour quelque chose que le client voit.
|
*/

function plain(string $text): string
{
    $method = new ReflectionMethod(AgentRunner::class, 'toPlainText');

    $method->setAccessible(true);

    return $method->invoke(app(AgentRunner::class), $text);
}

it('retire le gras', function () {
    expect(plain('Nos conseillers : **Du lundi au samedi, de 8h à 18h.**'))
        ->toBe('Nos conseillers : Du lundi au samedi, de 8h à 18h.');
});

it('retire l\'italique et le soulignement', function () {
    expect(plain('Commande *expédiée* hier'))->toBe('Commande expédiée hier')
        ->and(plain('Montant __remboursé__ ce jour'))
        ->toBe('Montant remboursé ce jour');
});

it('retire les titres et le code', function () {
    expect(plain("## Horaires\nOuvert de 8h à 18h."))
        ->toBe("Horaires\nOuvert de 8h à 18h.")
        ->and(plain('Référence `TCK-4F2A9B1C`'))
        ->toBe('Référence TCK-4F2A9B1C');
});

it('transforme les puces en points', function () {
    expect(plain("- Lundi\n- Mardi"))->toBe("• Lundi\n• Mardi");
});

it('garde seulement le texte des liens', function () {
    expect(plain('Voir [nos conditions](https://exemple.ci/cgv) en ligne'))
        ->toBe('Voir nos conditions en ligne');
});

it('ne touche pas au texte ordinaire', function () {
    $texte = "Votre commande arrive demain. Le montant est de 25 000 FCFA, "
        . "payable à la livraison. Référence : TCK-4F2A9B1C.";

    expect(plain($texte))->toBe($texte);
});

it('ne casse pas une multiplication ni un souligné dans un mot', function () {
    expect(plain('Le tarif est de 3 * 4 unités'))
        ->toBe('Le tarif est de 3 * 4 unités')
        ->and(plain('Le champ nom_client est obligatoire'))
        ->toBe('Le champ nom_client est obligatoire');
});

it('préserve un emoji', function () {
    expect(plain('**Ouvert** du lundi au samedi 😊'))
        ->toBe('Ouvert du lundi au samedi 😊');
});
