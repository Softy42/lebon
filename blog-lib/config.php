<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'maison_melina',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        // Changez ces identifiants en production
        'username' => 'admin',
        // Mot de passe par défaut: melina2026 (à changer immédiatement)
        'password_hash' => '$2y$12$aAW1BKaX6cPoj5OIaS1nT.TtaV2pLEt/rbhK1iE6kAdJYugqBX9BC',
    ],
    'contact_url' => 'https://maison-m-lina.vercel.app/contact',
    'authors' => ['Thierry', 'Christine'],
];
