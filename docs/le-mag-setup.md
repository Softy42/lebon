# Setup Le Mag (PHP + MySQL)

1. Importer `docs/le-mag-schema.sql` dans phpMyAdmin.
2. Copier le dossier `blog-lib/` et modifier `blog-lib/config.php` (DB + identifiants admin).
3. Vérifier que PHP sessions est actif.
4. Accès public:
   - `/le-mag/`
   - `/le-mag/temoignage.php`
5. Accès back-office:
   - `/admin/le-mag/index.php`
   - identifiant par défaut: `admin`
   - mot de passe par défaut: `melina2026` (à changer dans `config.php`)

## Notes
- Les CTA blog pointent vers `https://maison-m-lina.vercel.app/contact`.
- Auteurs disponibles en V1: Thierry, Christine.
- Les témoignages publiés nécessitent `Autorisation de diffusion` cochée.
- Les images “au-dessus du bloc Témoignage” des articles sont stockées dans `/img/le-mag/` (dossier à créer et à rendre inscriptible par PHP si besoin).
- Si votre base existe déjà, ajoutez les colonnes image :
  ```sql
  ALTER TABLE blog_posts
    ADD COLUMN testimonial_image_path VARCHAR(255) NULL AFTER cta_variant,
    ADD COLUMN testimonial_image_alt VARCHAR(255) NULL AFTER testimonial_image_path;
  ```

## Dépannage rapide (erreur 500)
- Si `/le-mag/` ou `/admin/le-mag/` affiche une erreur 500/503, le cas le plus fréquent est une connexion MySQL incorrecte.
- Vérifiez en priorité `blog-lib/config.php` :
  - `host`
  - `port`
  - `name`
  - `user`
  - `password`
- Vérifiez aussi que l'extension `pdo_mysql` est active sur l'hébergement.
