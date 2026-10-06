# Publier Maison Melina sur IONOS

Ce workflow remplace le transfert manuel avec FileZilla. Il se lance manuellement
depuis la branche `main`, apres validation des changements. Un commit seul ne
declenche pas la publication sur IONOS.

## Premiere configuration

Dans Settings > Secrets and variables > Actions, creer trois repository secrets :

- `IONOS_SFTP_HOST` : l'hote affiche dans FileZilla, au format `access....webspace-data.io`.
- `IONOS_SFTP_USER` : le nom d'utilisateur SFTP IONOS, au format `u...`.
- `IONOS_SFTP_PASSWORD` : le mot de passe SFTP utilise avec FileZilla.

Ne pas inscrire ces valeurs dans un fichier du depot. Le dossier de destination
est fixe a `/clickandbuilds/MaisonMelina`, port SFTP 22.

## Verifier puis publier

1. Ouvrir Actions > Publier sur IONOS > Run workflow.
2. Garder la branche `main`. Pour le premier essai, laisser Publier decoche.
3. Attendre le resultat vert : le serveur et la destination ont ete verifies,
   sans modification des fichiers distants. Cela ne teste pas encore les droits
   d'ecriture, le quota disque ou le remplacement atomique des fichiers.
4. Relancer Run workflow en cochant Publier pour transferer le site.
5. Attendre le resultat vert et actualiser https://www.maison-melina.fr/.

Pour les publications suivantes, lancer directement avec Publier coche.
GitHub Desktop et FileZilla ne sont plus necessaires pour cette operation.

## Comportement et limites

- Les fichiers publics suivis par Git sont transferes, y compris `.htaccess` et
  les videos recuperees via Git LFS. Les fichiers `.github`, `.git`, les fichiers
  caches et la documentation Markdown ne sont pas envoyes.
- Les fichiers du meme nom sont remplaces ; les autres fichiers distants sont
  conserves. La suppression d'un fichier dans GitHub ne le retire pas du serveur.
- Chaque fichier est transfere temporairement puis remplace avec l'extension
  SFTP `posix-rename`. Le serveur doit la prendre en charge. En cas d'echec,
  consulter le journal avant de relancer ; la publication de tout le site n'est
  pas une transaction unique et certains fichiers peuvent deja etre a jour.
- Les ressources sont envoyees avant les pages HTML. Le workflow ne cree pas de
  sauvegarde du contenu distant ; l'historique Git permet de retrouver les
  versions suivies dans le depot.
- La cle du serveur est comparee aux empreintes officielles IONOS avant toute
  authentification. Si IONOS change ses cles, verifier leur documentation avant
  de mettre a jour la liste, sans desactiver cette verification.

Sources :
- https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/
- https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow
