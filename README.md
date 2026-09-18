# NGSign pour Nextcloud

Cette application ajoute l’action **Signer avec NGSign** aux PDF dans l’application Files. L’utilisateur sélectionne l’action, renseigne un ou plusieurs signataires, puis le serveur Nextcloud transmet le document à NGSign et lance une transaction `BY_MAIL`.

## Livrables

| Livrable | Usage |
| --- | --- |
| Source de l’app `ngsign` | Installer sur une instance Nextcloud self-hosted existante. |
| Archive `dist/ngsign-<version>.tar.gz` | Livraison autonome au client. |
| Image Docker Hub `nextcloud-ngsign` | Déployer Nextcloud et NGSign ensemble. |

## Installation sur une instance Nextcloud existante

Depuis le clone du dépôt, générer l’archive sans les sources de développement :

```sh
npm ci
npm run package-app
```

Extraire `dist/ngsign-<version>.tar.gz` sous le répertoire `custom_apps` de l’instance ; le dossier final doit être `<nextcloud>/custom_apps/ngsign`. Puis activer l’app :

```sh
sudo -u www-data php occ app:enable ngsign
```

L’utilisateur du serveur web doit pouvoir lire le dossier. Dans **Administration → Paramètres supplémentaires → NGSign**, définir l’URL NGSign, le token et l’expiration. Activez le cron Nextcloud : le suivi des signatures terminées en dépend.

## Déploiement Docker Hub

Créer un fichier `.env` à partir de `.env.example`, en définissant au minimum les mots de passe, `NEXTCLOUD_TRUSTED_DOMAINS`, et l’image voulue :

```sh
IMAGE_NAME=<dockerhub-user>/nextcloud-ngsign:0.1.0
docker compose --env-file .env -f compose.production.yaml up -d
```

Le service `cron` est inclus : il récupère automatiquement les documents signés. Les volumes `nextcloud` et `db` doivent être sauvegardés avant une mise à jour.

## Développement local

1. Copier ce dossier sous `<nextcloud>/custom_apps/ngsign` (ou l’archiver, puis l’installer depuis **Apps → Vos apps**).
2. Activer **NGSign** dans Apps.
3. Dans **Administration → Paramètres supplémentaires → NGSign**, saisir l’URL du serveur NGSign et le bearer token de votre tenant.

Par défaut, l’URL vise le sandbox : `https://sandbox.ng-sign.com/server`.

L’image est basée sur `nextcloud:34.0.4-apache` et l’application déclare sa compatibilité Nextcloud **30 à 34**. Nextcloud 34 est la version retenue pour l’image publiée ; l’image est épinglée, afin qu’une reconstruction ne change pas implicitement de version.

### Démarrage local

```sh
cp .env.example .env
# Éditer .env : remplacer les trois mots de passe et, si nécessaire, NGSIGN_API_TOKEN.
docker compose up --build -d
```

Ouvrir `http://localhost:8080`. L’application est activée automatiquement au démarrage et les variables `NGSIGN_BASE_URL` et `NGSIGN_API_TOKEN` sont injectées côté serveur. Ne mettez jamais le token dans le Dockerfile ou dans une image publiée.

### Publication Docker Hub

Le workflow [docker-publish.yml](.github/workflows/docker-publish.yml) publie une image multi-architecture (`linux/amd64`, `linux/arm64`) lorsqu’un tag Git `v*` est poussé. Créer les secrets GitHub suivants :

- `DOCKERHUB_USERNAME` — votre identifiant Docker Hub ;
- `DOCKERHUB_TOKEN` — un access token Docker Hub avec droit d’écriture.

Puis exécuter :

```sh
git tag v0.1.0
git push origin v0.1.0
```

L’image sera publiée sous `<DOCKERHUB_USERNAME>/nextcloud-ngsign:0.1.0` et `:latest`.

## Flux NGSign implémenté

1. `POST /protected/transaction/pdfs` avec le PDF en Base64.
2. `POST /protected/transaction/{transactionId}/launch` avec un `sigConf` par signataire : signature `CERTIFIED_TIMESTAMP`, mode `BY_MAIL`, OTP `NONE`.

Le bearer token n’est jamais exposé au navigateur. Le contrôleur lit le fichier depuis l’espace du seul utilisateur connecté avant de le transmettre à NGSign.

La durée d'expiration est configurable dans les paramètres NGSign (15 jours par défaut). Elle est envoyée à NGSign avec `expirationDate`; le cron local cesse aussi tout polling après cette date.

### Diagnostic NGSign

Dans **Administration → Paramètres supplémentaires → NGSign**, activez **Debug mode** puis enregistrez. Après chaque lancement, la fenêtre de signature affiche les requêtes et réponses NGSign. Le token est masqué et le contenu Base64 du PDF n'est jamais affiché (seule sa taille est indiquée). Désactivez ce mode après le diagnostic.

## À adapter selon votre tenant

La position de signature initiale reprend la collection Postman fournie : page 1, `xAxis: 81`, `yAxis: 44.28125`. Si l’API de votre tenant renvoie un format différent à l’upload, ajuster l’extraction des identifiants dans `lib/Service/NGSignClient.php`.
