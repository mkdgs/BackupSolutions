**BRAINSTORM**
==========

**problématique**
Sauvegarde d'un nombre important de sites et applications web sur des structures hétéroclites
- le shell ssh n'est pas toujours disponible
- cron parfois peu fiable, ou très limité
- automatisation de la mise en place du backup
- possibilité de monitorer l'ensemble des backups de manière centralisée


**V0.0**
- POC


**IMPLEMENTATION**
-------------

- langage php
- support de mysql + postgres
- conf auto pour wordpress/prestashop/magento...

*se compose en deux partie*

1. **Client**
  - prépare le backup
  - effectue le transfert dans repertoires privé sur le client
  - ne stock aucun code d'auth (parse les fichiers de conf pour récupérer les identifiants)
  - fichier config json (contenu a sauvegarder, ttl de la sauvegarde)
  - réalise le transfert (via sftp, rsync, gdrive...)

2. **Serveur de backup**
  - orchestre le transfert, n'assure pas obligatoirement le stockage (Cold backup, Nas, google drive...)
  - permet l'accès uniquement en lecture seule sur le client et dans le / du backup préparé
  - fichier config json (url date,+ date de dernière sauvegarde intègre)
  - initialise le backup via requete https (post, pour ne pas avoir de code auth dans les logs http) 
  - transmet les code d'auth 
  - vérifie l'intégrité du backup
  - gestion de la périodicité (journalier/glissant...)
  - log 
  - monitoring/alerte
  - *idéalement:*
    - chiffre les données
    - anonymise/archive/delete selon ttl
    - peut initialiser une restauration (dans un repertoire privé du client, pas d'écrasement en prod)
    - tableau de bord, monitoring, liste des backup

*Bookmarks*
- https://phpbu.de/
- https://github.com/brianreese/backup-google-drive-php
- https://stackoverflow.com/questions/46828881/backup-files-to-google-drive-using-php
- https://rclone.org/
- https://stackoverflow.com/questions/14199404/on-the-fly-anonymisation-of-a-mysql-dump

