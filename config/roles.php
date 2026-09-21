<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rôles au sein d'une entreprise
    |--------------------------------------------------------------------------
    |
    | Chaque entreprise cliente organise son équipe avec ces rôles. Ils
    | sont indépendants du statut d'administrateur de la plateforme, qui
    | concerne l'exploitant du service et non ses clients.
    |
    | Les droits sont nommés « domaine.action ». Un rôle qui possède
    | « * » possède tout : c'est le cas du propriétaire, qui ne doit
    | jamais pouvoir se retrouver enfermé dehors de sa propre entreprise.
    |
    | Pour créer un rôle maison, ajoutez une entrée ici : rien d'autre
    | n'est à modifier.
    |
    */

    'roles' => [

        'owner' => [
            'label' => 'Propriétaire',
            'description' => "Tous les droits, y compris l'abonnement et "
                . "la suppression de comptes.",
            'abilities' => ['*'],
        ],

        'admin' => [
            'label' => 'Administrateur',
            'description' => "Gère l'équipe, les connaissances et le "
                . "comportement de l'assistant. Ne touche pas à l'abonnement.",

            'abilities' => [
                'records.view.all',
                'records.assign',
                'agents.manage',
                'knowledge.manage',
                'autopilot.manage',
                'branding.manage',
                'insights.view',
                'clients.manage',
            ],
        ],

        'supervisor' => [
            'label' => 'Superviseur',
            'description' => "Voit toute l'activité de l'équipe et répartit "
                . "le travail. Ne modifie aucun réglage.",

            'abilities' => [
                'records.view.all',
                'records.assign',
                'insights.view',
                'clients.manage',
            ],
        ],

        'agent' => [
            'label' => 'Agent',
            'description' => "Traite les demandes qui lui sont confiées, "
                . "et celles que personne n'a encore prises.",

            'abilities' => [
                'clients.manage',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Droits et ce qu'ils ouvrent
    |--------------------------------------------------------------------------
    |
    | Sert à l'affichage dans l'écran de gestion des agents : un
    | responsable doit comprendre ce qu'il accorde.
    |
    */

    'abilities' => [
        'records.view.all' => "Voir les conversations, tickets et appels de toute l'équipe",
        'records.assign' => 'Attribuer un dossier à un autre membre',
        'agents.manage' => 'Ajouter, modifier et désactiver des comptes',
        'knowledge.manage' => 'Gérer la base de connaissances',
        'autopilot.manage' => "Régler le comportement de l'assistant",
        'branding.manage' => "Modifier le logo et les couleurs",
        'billing.manage' => "Gérer l'abonnement et les règlements",
        'insights.view' => 'Consulter les alertes de supervision',
        'clients.manage' => 'Gérer les fiches clients',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rôle par défaut
    |--------------------------------------------------------------------------
    */

    'default' => 'agent',
];
