# Publication selective et retour arriere sur IONOS

Ouvrir Actions > Publier sur IONOS > Run workflow, branche `main`.
Choisir une action :

- **verifier** : connexion et liste des fichiers qui seraient ajoutes ou modifies.
  Aucun fichier distant n'est ecrit.
- **publier** : sauvegarde des anciennes versions, puis transfert des fichiers
  nouveaux ou modifies uniquement.
- **restaurer** : annule la derniere publication (ou la publication interrompue).
  Les fichiers remplaces retrouvent leur contenu anterieur ; les fichiers ajoutes
  par cette publication sont retires du site, mais restent dans la sauvegarde.

Un commit seul ne publie pas le site. Ne pas relancer un ancien workflow : utiliser
Run workflow sur `main` pour executer la version actuelle de cette commande.

## Selection des fichiers

La premiere publication compare GitHub au commit `9c4dd96363d4e12fbe9fb3085d8583da2af84c18`,
qui precede la desactivation des popups. A la mise en place, cela selectionne les
16 pages HTML modifiees et `popup-config.js`, pas les 200 fichiers du site.

Ensuite, la reference est la derniere publication reussie. Seuls les fichiers
publics ajoutes ou modifies dans Git depuis cette reference sont examines. Le
contenu SHA-256 est compare au fichier sur IONOS : un fichier deja identique
n'est pas transfere. Les autres fichiers distants ne sont pas synchronises.

Les suppressions dans Git ne suppriment pas les fichiers sur le serveur. Un
renommage publie le nouveau chemin et conserve l'ancien. `.github`, `.git`, les
autres fichiers caches et les documents Markdown ne sont jamais publies ;
`.htaccess` est admis seulement s'il a ete modifie. Les videos necessaires sont
recuperees via Git LFS.

## Sauvegardes et restauration

Les sauvegardes sont conservees sur le meme compte IONOS dans
`/.maison-melina-deploy`, en dehors du dossier du site
`/clickandbuilds/MaisonMelina`. Les dossiers sont crees avec les droits 700 et
les sauvegardes avec les droits 600. Cet emplacement ne doit pas etre utilise
comme racine d'un domaine. Aucun mot de passe n'y est enregistre.

Avant le premier remplacement, la commande enregistre tous les anciens contenus
reellement lus sur IONOS et tous les nouveaux contenus, ainsi qu'un journal de
restauration. Si cette sauvegarde echoue (droits ou espace insuffisant), aucun
fichier du site n'est remplace.

Les transferts utilisent un fichier temporaire et `posix-rename` pour remplacer
chaque fichier apres son transfert complet. Le site entier n'est pas une
transaction unique : en cas d'interruption, des fichiers peuvent deja etre
publies. Choisir alors **restaurer** avant de recommencer une publication.

La restauration controle les empreintes des sauvegardes et des fichiers en
ligne avant de commencer. Si un fichier a ete modifie depuis avec FileZilla,
elle s'arrete pour ne pas ecraser ce travail. Une restauration interrompue peut
etre relancee. Les repertoires crees peuvent rester vides apres restauration.

La restauration remet aussi la reference de publication precedente. Les
changements annules seront donc proposes de nouveau lors de la prochaine
publication, tant qu'ils restent dans GitHub. Pour les abandonner definitivement,
il faut egalement les annuler dans GitHub.

Les sauvegardes ne sont pas purgees automatiquement et occupent de l'espace
chez IONOS. Elles permettent d'annuler nos publications, mais ne remplacent pas
une sauvegarde complete de l'hebergement sur un support independant.

## Configuration

Trois repository secrets dans Settings > Secrets and variables > Actions :

- `IONOS_SFTP_HOST` : adresse `access....webspace-data.io` de FileZilla.
- `IONOS_SFTP_USER` : utilisateur SFTP `u...`.
- `IONOS_SFTP_PASSWORD` : mot de passe exact, y compris les espaces qui en font partie.

Le port est 22. Les droits d'ecriture dans le dossier du site et de creation dans
`/.maison-melina-deploy` sont necessaires pour publier. Le mode verifier ne teste
pas ces droits d'ecriture ni l'espace libre. La cle du serveur est comparee aux
empreintes officielles IONOS avant transmission du mot de passe.

Sources :
- https://www.ionos.de/hilfe/hosting/ssh-zugaenge-einrichten-und-verwalten/uebersicht-der-ssh-fingerabdruecke-im-ionos-webhosting/
- https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow
