# Setup Le Mag (production)

Ce module est configuré pour un usage **production-first** :
- aucun identifiant admin par défaut,
- aucun accès DB root toléré en production,
- secrets obligatoires via variables d'environnement.

## 1) Pré-requis

- PHP avec `pdo_mysql`
- MySQL/MariaDB
- Sessions PHP actives
- Dossier d'upload images créé : `/img/le-mag/` (inscriptible par PHP)

## 2) Import du schéma

Importer :
1. `docs/le-mag-schema.sql`
2. `docs/le-mag-seed-content.sql` (optionnel)

## 3) Créer un utilisateur MySQL dédié (obligatoire)

Exemple (à adapter) :

```sql
CREATE USER 'lemag_app'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_STRONG_DB_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON maison_melina.* TO 'lemag_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

> Ne pas utiliser `root` pour l'application.

## 4) Définir les variables d'environnement

Copier `docs/le-mag-env.example` dans votre gestionnaire de secrets/variables d'environnement puis injecter les valeurs réelles sur l'hébergement.

Variables obligatoires :
- `BLOG_DB_NAME`
- `BLOG_DB_USER`
- `BLOG_DB_PASS`
- `BLOG_ADMIN_USER`
- `BLOG_ADMIN_PASSWORD_HASH`

Variables recommandées :
- `BLOG_APP_ENV=production`
- `BLOG_DB_HOST`, `BLOG_DB_PORT`, `BLOG_DB_CHARSET`
- `BLOG_CONTACT_URL`
- `BLOG_AUTHORS`

### Générer le hash du mot de passe admin

```bash
php -r "echo password_hash('VOTRE_MOT_DE_PASSE_LONG_ET_COMPLEXE', PASSWORD_BCRYPT), PHP_EOL;"
```

## 5) Déploiement

- Déployer le code.
- Vérifier que les variables d'environnement sont disponibles pour PHP-FPM/Apache/Nginx.
- Vérifier les droits du dossier `/img/le-mag/`.

## 6) URLs

Public :
- `/le-mag/`
- `/le-mag/article.php?slug=...`
- `/le-mag/temoignage.php`

Back-office :
- `/admin/le-mag/index.php`
- `/admin/le-mag/logout.php`

## 7) Comportements de sécurité implémentés

- Le module échoue au chargement si les secrets obligatoires sont absents.
- En production, utilisateur DB `root` refusé.
- En production, identifiant admin trivial (`admin`, `administrator`) refusé.
- Le hash admin doit être en bcrypt/argon2.
- Session admin renforcée : cookie `HttpOnly`, `SameSite=Strict`, `Secure` si HTTPS, régénération d'ID après login.

## 8) Migration si base existante

Si nécessaire :

```sql
ALTER TABLE blog_posts
  ADD COLUMN testimonial_image_path VARCHAR(255) NULL AFTER cta_variant,
  ADD COLUMN testimonial_image_alt VARCHAR(255) NULL AFTER testimonial_image_path;
```

## 9) Dépannage

Si `/le-mag/` ou `/admin/le-mag/` renvoie une erreur 500 :
- vérifier les variables d'environnement obligatoires,
- vérifier que l'utilisateur DB applicatif existe et a les droits,
- vérifier `pdo_mysql`.
