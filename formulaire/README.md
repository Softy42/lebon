# Formulaire Maison Mélina

## Sources à conserver

| Emplacement local | Rôle |
| --- | --- |
| `../contact/index.html`, `../contact/formulaire.js` | Formulaire public |
| `../api/formulaire.php`, `../api/.htaccess` | Traitement HTTP public et protections Apache |
| `prive/` | Bibliothèque métier, PHPMailer et protection du dossier privé |
| `config/production.example.php` | Modèle de production sans secrets |
| `config/preproduction.example.php` | Modèle de préproduction sans secrets |
| `tests/` | Contrôles de structure et tests de validation |
| `VERIFICATIONS.md` | Bilan des vérifications et points restant à confirmer |

Le dossier `formulaire/` peut être versionné dans Git avec ces seuls éléments.
Il est entièrement exclu de la publication du site public par le script IONOS.
Les secrets, sessions et compteurs ne doivent pas être ajoutés dans Git.
Les fichiers publics se maintiennent directement dans `contact/` et `api/`, sans copie de livraison.

## Emplacements FTP

| Environnement | Racine du site public | Dossier privé, hors racine web |
| --- | --- | --- |
| Production | `/clickandbuilds/MaisonMelina` | `/melina-form-private-MaisonMelina` |
| Préproduction | `/www/preprod-melina` | `/melina-form-private-preprod-melina` |

Ces chemins sont ceux affichés par le FTP ; ils ne sont pas les chemins absolus du serveur.
L’API calcule le dossier privé à partir de sa racine web, en remontant hors de `www`
ou de `clickandbuilds`. Une autre disposition nécessite de définir le chemin absolu
dans la variable d’environnement PHP `MELINA_FORM_PRIVATE_DIR`.

## Installation et maintenance

1. Utiliser PHP 8.3 ou supérieur avec `curl`, `mbstring`, `openssl`, `session` et JSON.
   Vérifier la compatibilité des autres fonctions avant tout changement de version PHP.
2. Placer le contenu de `prive/`, y compris `.htaccess`, dans le dossier privé choisi.
   Le compte PHP doit pouvoir lire les bibliothèques et y créer le sous-dossier `donnees/`.
3. Pour une nouvelle installation, copier le modèle correspondant sous le nom `config.php`
   dans le dossier privé du serveur. Pour une mise à jour de code, conserver le `config.php`
   existant afin de préserver ses secrets et paramètres.
4. Renseigner sur le serveur une clé applicative propre à l’environnement, la paire reCAPTCHA
   v2 autorisée sur le domaine et le mot de passe de la boîte SMTP.
   Génération de `app_key` : `openssl rand -hex 32`.
   Garder les apostrophes et virgules PHP ; dans une valeur entre apostrophes, écrire
   une apostrophe `\'` et un antislash `\\`.
5. Une nouvelle installation commence en mode `simulation` : validation du formulaire et
   de reCAPTCHA sans email. Passer à `smtp` pour activer les envois réels.
6. Sur l’hébergement de production validé, le dossier privé fonctionne en 700 et `config.php`
   en 600. Vérifier l’accès PHP après toute modification de permissions ; aucune récursion
   n’est nécessaire pour changer les droits du dossier parent.
7. Installer les fichiers de `api/`, vérifier l’initialisation, puis publier le JavaScript
   de `contact/` avant son `index.html`. Les réglages `.htaccess` de la racine du site
   ne sont pas remplacés par cette installation.

La publication GitHub existante reste manuelle. Elle prend en charge les quatre fichiers
publics et conserve ses mécanismes de sauvegarde/restauration. Le dossier privé se maintient
séparément sur le FTP ; il n’est pas pris en charge par ce workflow.

## Configuration connue

- Production : origine `https://www.maison-melina.fr`, SMTP IONOS `smtp.ionos.fr`,
  port 465 et chiffrement SMTPS, compte et expéditeur `contact@maison-melina.fr`.
- Destinataires de production, pour tous les sujets et toutes les maisons :
  `info@maison-melina.fr`, `contact@so-online.fr`, `christine.thevenon2@wanadoo.fr`.
- Préproduction : origine `https://preprod-melina.maison-melina.fr`, compte IONOS identique
  lors des derniers essais, seul destinataire `contact@so-online.fr`.
- Les clés reCAPTCHA existantes ont été réutilisées à la demande de Sophie. Vérifier
  que le domaine de l’environnement reste autorisé dans la console Google.
- L’en-tête Reply-To utilise uniquement l’adresse du visiteur. Son nom figure dans le
  contenu du message. L’affichage du destinataire de réponse reste propre au logiciel email.

## Vérifications sans envoi

Depuis la racine du dépôt :

```sh
python3 -B formulaire/tests/verifier_structure.py
python3 -B .github/scripts/test_deploy_ionos.py
php formulaire/tests/validation.php
```

Les tests PHP n’utilisent aucun compte SMTP ni appel réseau et n’envoient aucun email.
Ils nécessitent PHP avec `mbstring` sur la machine qui les exécute.
Le lint se fait avec `php -l` sur les fichiers PHP.

Pour contrôler l’initialisation de l’API, une requête GET sur `/api/formulaire.php`
avec `X-Melina-Form: 1` et `Origin` égal à l’origine configurée est suffisante.
Elle n’envoie pas d’email. La réponse contient un ticket et une clé publique : ne pas
les recopier dans les comptes rendus. Un HTTP 200 confirme l’initialisation, pas la
connexion SMTP ni la livraison des emails.

Pour les essais navigateur, une nouvelle soumission en mode SMTP envoie réellement aux
destinataires configurés. Les essais supplémentaires avec envoi sont suspendus à la demande de Sophie.

## Protections et exploitation

Validation serveur des champs, sujets et maisons ; reCAPTCHA vérifié côté serveur avec
contrôle du domaine ; origine exacte, ticket de session à usage unique, cookie Secure/
HttpOnly/SameSite Strict ; champ piège et délai minimal de trois secondes ; aucune pièce jointe.
SMTP chiffré avec vérification des certificats, destinataires fixes et contenu HTML échappé.

Limites partagées : initialisations 60/IP/heure et 3000 globales/heure ; soumissions
10/IP/heure et 100 globales/heure ; 5/adresse email/jour après validation CAPTCHA.
Les limites portent sur des adresses IP ou emails hachés avec une clé privée. Les données
expirées sont purgées au passage suivant. Vérifier que les sessions expirées sont bien
nettoyées par PHP sur l’hébergement. Ne pas saturer les limites de production pour les tester.

Les erreurs donnent une référence et, pour les erreurs connues, un code technique borné.
Aucune valeur secrète ni contenu des messages n’est volontairement journalisé par l’application.
Un succès SMTP ne garantit pas le classement en boîte principale. Vérifier les réceptions,
les éventuels retours d’erreur et l’authentification SPF/DKIM/DMARC selon les besoins.

PHPMailer 7.1.1 est conservé avec sa licence et les empreintes d’origine dans
`prive/lib/PHPMailer/ORIGINE.json`. Surveiller ses mises à jour de sécurité lors de la maintenance.
L’ancienne API Vercel doit être désactivée séparément : le remplacement du formulaire ne
l’empêche pas de recevoir des appels directs. Ne pas révoquer un accès partagé avant d’avoir
identifié ses autres usages.

Références : [Google reCAPTCHA](https://developers.google.com/recaptcha/docs/verify),
[PHPMailer](https://github.com/PHPMailer/PHPMailer),
[SMTP IONOS](https://www.ionos.fr/assistance/email/autres-programmes-de-courrier-electronique/configurer-manuellement-un-compte-e-mail-dans-thunderbird-version-7833/).
