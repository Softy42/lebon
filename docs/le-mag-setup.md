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
