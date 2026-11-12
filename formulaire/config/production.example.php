<?php
declare(strict_types=1);
// PRODUCTION : modèle sans secrets, à installer uniquement dans le dossier privé.
// Ne pas remplacer le config.php de préproduction par ce fichier.
return [
    'mode' => 'simulation', // passer à smtp au moment de l’activation autorisée.
    'origin' => 'https://www.maison-melina.fr',
    'app_key' => '', // générer une nouvelle clé propre à la production.
    'recaptcha_site_key' => '', // paire de clés autorisée sur le domaine de production.
    'recaptcha_secret' => '',
    'smtp' => [
        'host' => 'smtp.ionos.fr', // boîte contact@maison-melina.fr hébergée chez IONOS.
        'port' => 465,
        'encryption' => 'smtps',
        'username' => 'contact@maison-melina.fr',
        'password' => '', // mot de passe de cette boîte, à saisir uniquement sur le serveur.
        'from' => 'contact@maison-melina.fr',
        // Destinataires fixes, pour tous les sujets et toutes les maisons.
        'to' => [
            'info@maison-melina.fr',
            'contact@so-online.fr',
            'christine.thevenon2@wanadoo.fr',
        ],
    ],
];
