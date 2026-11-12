<?php
declare(strict_types=1);
// Copier en config.php UNIQUEMENT sur le serveur, hors du dossier web.
return [
    'mode' => 'simulation', // simulation ou smtp : jamais de faux succès en simulation.
    'origin' => 'https://preprod-melina.maison-melina.fr', // origine exacte, sans chemin ni / final.
    'app_key' => '', // 64 caractères hexadécimaux aléatoires (voir README.md).
    'recaptcha_site_key' => '', // clés reCAPTCHA v2 « Je ne suis pas un robot ».
    'recaptcha_secret' => '',
    'smtp' => [
        'host' => 'smtp.ionos.fr', // à adapter à l’offre de messagerie utilisée.
        'port' => 465,
        'encryption' => 'smtps', // smtps:465 ou starttls:587, jamais en clair.
        'username' => 'contact@maison-melina.fr',
        'password' => '',
        'from' => 'contact@maison-melina.fr', // adresse autorisée par le compte SMTP.
        'to' => 'contact@so-online.fr', // unique destinataire des essais ; à remplacer pour la production.
    ],
];
