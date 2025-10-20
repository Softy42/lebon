# GSC — suivi des URL supprimées définitivement (410)

1. **Vérifier la réponse 410**
   - Utiliser un outil comme https://httpstatus.io pour confirmer que chacune des URL renvoie désormais le code `410 Gone`.

2. **Nettoyer les sources de l'URL**
   - Supprimer ces URL des sitemaps (`sitemap.xml` et dérivés) et regénérer si nécessaire.
   - Vérifier les menus, pieds de page, contenus internes et documents afin qu'aucun lien ne référence encore ces anciennes adresses.

3. **Informer Google Search Console**
   - Ouvrir GSC → "Indexation" → "Pages" → Filtre "Introuvable (404)".
   - Sélectionner une URL concernée puis cliquer sur "Lancer une validation" pour signaler la correction. Répéter si nécessaire pour les autres entrées.
   - Optionnel : dans GSC → "Index" → "Suppressions", lancer une demande si un masquage temporaire est souhaité pendant la période de traitement.

4. **Surveiller le rapport**
   - Revenir dans GSC après quelques jours pour vérifier la disparition progressive des URL du rapport.

## Liste des URL concernées

```
https://maison-melina.fr/maison-a-saint-just-saint-rambert
https://www.maison-melina.fr/MaisonMelina/benefices-economiques/
https://www.maison-melina.fr/MaisonMelina/creer-votre-maison/
https://www.maison-melina.fr/MaisonMelina/nos-maisons/feurs/
https://www.maison-melina.fr/MaisonMelina/nos-maisons/chateauroux/
https://www.maison-melina.fr/MaisonMelina/nos-maisons/
https://www.maison-melina.fr/MaisonMelina/
https://www.maison-melina.fr/MaisonMelina/nos-maisons/saint-didier-en-velay/
https://www.maison-melina.fr/MaisonMelina/nos-maisons/saint-chamond/
https://www.maison-melina.fr/MaisonMelina/presse/
https://www.maison-melina.fr/MaisonMelina/404.html
https://maison-melina.fr/maison-a-saint-didier-en-velay
https://maison-melina.fr/maison-a-feurs
https://maison-melina.fr/nos-realisations
https://maison-melina.fr/contactez-maison-melina
https://maison-melina.fr/mentions-legales-et-politique-de-confidentialite
https://maison-melina.fr/la-colocation-senior
https://maison-melina.fr/creer-votre-maison-partagee
https://maison-melina.fr/maison-partagee-a-belley
```

Ces étapes garantissent que Google prenne en compte la suppression définitive après la mise en place du code 410 dans `.htaccess`.
