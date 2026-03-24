# Blog SQL setup (IONOS)

## Variables d'environnement requises

- `BLOG_DB_HOST`
- `BLOG_DB_PORT` (optionnel, défaut `3306`)
- `BLOG_DB_NAME`
- `BLOG_DB_USER`
- `BLOG_DB_PASSWORD`
- `BLOG_DB_POOL_SIZE` (optionnel, défaut `10`)
- `BLOG_ADMIN_USERNAME`
- `BLOG_ADMIN_PASSWORD`
- `BLOG_ADMIN_TOKEN`

## Initialisation

1. Exécuter `docs/blog-sql-schema.sql` sur la base MySQL/MariaDB IONOS.
2. Exécuter `docs/blog-sql-seed.sql` pour créer les catégories de départ.
3. Déployer avec les variables d'environnement ci-dessus.
