# La Météo Glacérienne

Bienvenue sur le projet OpenSource de la Météo Glacérienne, l'objectif du projet est trés simple, partagez ma passion sur la métérologie et de pouvoir entiérement créer vous même votre propre page web de maniére simple et efficace.

Je demande juste en condition de le préciser sur le site que sa été créer par Quentin BOISSET avec le chemin du github ^^

Ce Projet organisé en deux partie : 

- `FrontEnd/` : React, React Router, Redux Toolkit et styles SCSS (j'aime l'organisation en SASS)
- `BackEnd/` : API, Php, BDD

## Démarrer

Pour démarer le sit web, voici les commande a taper sur le terminal suivant :

# FrontEnd
```bash
cd FrontEnd
npm run dev
```

Vous allez voir apparaitre  http://localhost:5173/ (l'url du site en local ^^)

# BackEnd
```bash
cd BackEnd
php -S localhost:8000
```
## Infos pour votre premiére instalation ! 
Créer un fichier .env dans BackEnd et écrirer ceci  :

```bash
APP_ENV=local

DB_HOST=localhost
DB_PORT=3306
DB_NAME=apimeteo
DB_USER=root
DB_PASS=

APP_ENV=production

DB_HOST= (Mettre votre hébergeur)
DB_PORT=3306
DB_NAME= (Mettre votre nom de la BDD)
DB_USER= (Mettre votre Utilisateur)
DB_PASS=(Mettre votre Mot de Passse)
```