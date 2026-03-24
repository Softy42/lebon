# Blog SQL setup (IONOS mutualisé)

## 1) Créer les tables SQL

1. Exécuter `docs/blog-sql-schema.sql` sur la base MySQL/MariaDB IONOS.
2. Exécuter `docs/blog-sql-seed.sql` pour créer les catégories de départ.

## 2) Configurer le backend PHP

1. Copier `api/config.sample.php` en `api/config.php`.
2. Remplir les accès SQL IONOS + identifiants admin blog.
3. Vérifier que `api/.htaccess` est bien présent (bloque l'accès HTTP au fichier `config.php`).

## 3) Endpoints à utiliser

- `GET /api/blog-public.php`
- `POST /api/blog-admin-login.php`
- `GET|POST|PUT|DELETE /api/blog-admin-posts.php`
- `GET|POST|PUT|DELETE /api/blog-admin-categories.php`

## 4) Important FTP

- `api/config.php` doit être présent sur le FTP (non versionné recommandé).
- Le fichier `api/config.sample.php` est un modèle, sans mot de passe réel.
