<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Promeut un compte au rang d'administrateur de la plateforme.
 *
 * Volontairement réservé à la ligne de commande : personne ne doit
 * pouvoir s'accorder ce droit depuis l'interface.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'ai:super-admin
                            {email? : Adresse du compte à promouvoir}
                            {--revoke : Retirer le droit au lieu de l\'accorder}';

    protected $description = 'Accorde ou retire les droits d\'administration de la plateforme.';

    public function handle(): int
    {
        if (!$this->argument('email')) {
            return $this->listAdmins();
        }

        $email = trim($this->argument('email'));

        /*
         * Recherche insensible à la casse : une adresse saisie avec une
         * majuscule ne doit pas donner « compte introuvable ».
         */
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();

        if (!$user) {
            $this->error('Aucun compte avec cette adresse.');

            /*
             * Montrer les comptes existants évite de chercher à
             * l'aveugle dans la base.
             */
            $accounts = User::query()
                ->orderBy('id')
                ->limit(25)
                ->get(['id', 'name', 'email', 'role', 'is_super_admin']);

            if ($accounts->isEmpty()) {
                $this->line('');
                $this->warn(
                    'Aucun compte n\'existe encore. Inscrivez-vous depuis '
                    . 'l\'application, puis relancez cette commande.'
                );

                return self::FAILURE;
            }

            $this->line('');
            $this->line('Comptes enregistrés :');
            $this->line('');

            $this->table(
                ['#', 'Nom', 'Email', 'Rôle', 'Plateforme'],
                $accounts->map(fn (User $account) => [
                    $account->id,
                    $account->name,
                    $account->email,
                    $account->role,
                    $account->is_super_admin ? 'oui' : '',
                ])->all()
            );

            return self::FAILURE;
        }

        $revoke = (bool) $this->option('revoke');

        if ($revoke && User::where('is_super_admin', true)->count() <= 1) {
            $this->error(
                'Impossible : ce serait le dernier administrateur. '
                . 'Promouvez quelqu\'un d\'autre avant de retirer ce droit.'
            );

            return self::FAILURE;
        }

        $user->update(['is_super_admin' => !$revoke]);

        $this->info(
            $user->name . ($revoke
                ? ' n\'administre plus la plateforme.'
                : ' administre désormais la plateforme.')
        );

        return self::SUCCESS;
    }

    private function listAdmins(): int
    {
        $admins = User::where('is_super_admin', true)->get();

        if ($admins->isEmpty()) {
            $this->warn('Aucun administrateur de plateforme.');
            $this->line('Promouvoir : php artisan ai:super-admin vous@exemple.ci');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Nom', 'Email'],
            $admins->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
            ])->all()
        );

        return self::SUCCESS;
    }
}
