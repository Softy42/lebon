# Bilan du formulaire — 12 novembre 2026

## Validé

- Nouveau formulaire transféré et actif sur `https://www.maison-melina.fr/contact/`.
  Page et JavaScript comparés octet pour octet à la version préparée : correspondance,
  API locale utilisée et aucune référence Vercel dans ces deux fichiers.
- Passage du seul domaine de production à PHP 8.3 après audit : aucun ancien script
  PHP/CGI trouvé par inventaire récursif du dossier FTP de production. Le seul fichier
  PHP du site public est le nouvel endpoint. Aucun gestionnaire PHP appliqué aux pages
  HTML n’a été trouvé dans le `.htaccess` de production, resté inchangé.
- Après changement PHP : les 17 pages publiques attendues répondent HTTP 200 ; statuts
  et destinations identiques pour les 21 requêtes de référence. Les vérifications manuelles
  de Sophie confirment menu, fiches, galeries, vidéos et lien de demande de visite.
- Initialisation de production en HTTP 200, mode SMTP et ticket présent.
- Refus de production sans envoi : absence d’en-tête spécifique et origine étrangère (403),
  méthode PUT (405), soumission sans ticket (403). Cookie Secure/HttpOnly/SameSite Strict,
  absence de CORS global. En préproduction : CAPTCHA invalide, réutilisation de ticket et
  demande de visite sans maison également refusés.
- Sur le FTP de production, dossier privé en 700 et configuration en 600, accès PHP
  contrôlé après chaque modification sans email.
- Tests navigateur de Sophie en simulation, puis SMTP réel avec le compte OVH de test,
  et enfin avec `contact@maison-melina.fr` via IONOS.
- Réception du test de production confirmée sur `info@maison-melina.fr` et
  `contact@so-online.fr`. La capture montre les trois destinataires dans l’en-tête.
- Demande de visite avec Feurs et Maison EDMOND de Savigneux : coordonnées, sujet,
  noms des maisons, accents et retours à la ligne vérifiés dans le mail reçu.
- Espacement des rubriques de 16 pixels validé par Sophie après un envoi.
- Formulaire sur iPhone : affichage, saisie, choix des maisons, changement de sujet et
  présence du CAPTCHA et du bouton validés sans envoi.
- Destinataire de réponse contrôlé sur un email existant : il correspond à l’adresse
  visiteur de cet essai. L’essai utilisait la même adresse que l’expéditeur SMTP.
- Reply-To ensuite modifié pour transmettre l’adresse seule sans nom ; transfert en
  production confirmé par Sophie et initialisation API toujours correcte. Aucun nouveau
  mail demandé pour vérifier l’affichage futur, qui dépend aussi du logiciel de messagerie.
- Sources publiques intégrées localement et script de publication adapté. Tests simulés
  de publication/restauration et analyses syntaxiques PHP/JavaScript réussis.

## Restant à confirmer ou à traiter

- Réception chez Christine : en attente de son retour, sans nouvel envoi.
- Un essai de production avait affiché une erreur malgré deux réceptions. Un champ SMTP
  vide a été trouvé dans une copie locale FileZilla puis renseigné ; cela ne prouve pas
  l’état du serveur au moment de cet essai. La cause précise de l’erreur n’est pas établie.
- Ancienne API Vercel : pas encore désactivée, destinataires et accès historiques inconnus.
- SPF, DKIM, DMARC et délivrabilité générale : non audités.
- Limites d’envoi non saturées sur le site public ; tests PHP fournis mais non exécutés
  en CLI, car aucun interpréteur PHP n’est disponible sur le Mac de préparation.
- L’affichage JSON du flux Facebook était déjà défectueux avant le changement PHP :
  l’endpoint renvoyait du JavaScript. Aucun correctif hors périmètre appliqué.
- Les permissions restrictives ont été vérifiées en production ; elles n’ont pas été
  modifiées en préproduction lors de ces contrôles.

## Réorganisation locale

Les sources utiles sont regroupées dans `formulaire/`, exclu de la publication publique.
Une seule copie de la bibliothèque et de PHPMailer est conservée. Les fichiers publics
restent dans `contact/` et `api/`. Les modèles de configuration ne contiennent aucun secret.
Les copies de transfert, l’ancienne copie de sauvegarde de préproduction, les doublons,
les correctifs intermédiaires et les rapports bruts temporaires ne sont pas nécessaires
à l’exploitation ; le présent bilan en conserve les conclusions utiles.

Cette réorganisation est locale : aucun commit, push, transfert FTP ou email.
