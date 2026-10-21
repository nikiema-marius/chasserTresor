# La chasse au trésor du SIAO — Orange Money × SIAO 2026

Application web mobile-first de chasse au trésor par QR codes. Les participants scannent les QR codes
répartis sur le site du salon avec leur navigateur, sans application à installer. Chaque QR révèle une
lettre. Le but est de reconstituer le mot mystère.

**Stack** : Laravel 12 (PHP 8.3) · Livewire 3 · Tailwind CSS 4 · MySQL 8 · qr-scanner (navigateur) · endroid/qr-code (serveur)

> Laravel 12 a été retenu à la place de Laravel 11, qui ne reçoit plus de correctifs de sécurité depuis mars 2026.
> La structure du projet est identique.

---

## Sommaire

1. [Installation locale](#1-installation-locale)
2. [Variables d'environnement](#2-variables-denvironnement)
3. [Déploiement en production](#3-déploiement-en-production)
4. [Tests automatisés](#4-tests-automatisés)
5. [Procédure de test terrain](#5-procédure-de-test-terrain)
6. [Exploitation pendant le salon](#6-exploitation-pendant-le-salon)
7. [Architecture et sécurité](#7-architecture-et-sécurité)

---

## 1. Installation locale

Prérequis :
- PHP 8.3 avec les extensions `gd`, `zip`, `intl`, `mbstring`, `pdo_mysql`, `bcmath`, `fileinfo`, `openssl` ;
- Composer 2 ;
- Node 20 ou plus récent ;
- MySQL 8.

```bash
composer install
npm install
cp .env.example .env          # puis renseigner DB_* (voir section 2)
php artisan key:generate
php artisan migrate --seed    # crée le super-admin et une campagne de démo « ARTISAN »
npm run build                 # ou « npm run dev » pendant le développement
php artisan serve
```

- Jeu : http://localhost:8000
- Administration : http://localhost:8000/admin

Le seeder affiche **une seule fois** :
- le mot de passe du super-admin (si `ADMIN_SEED_PASSWORD` est vide) ;
- les URL et codes manuels des 14 QR de démonstration.

Si `ADMIN_2FA_REQUIRED=true`, le super-admin doit activer la double authentification à sa première connexion. Installez pour cela
Google Authenticator, Microsoft Authenticator ou FreeOTP sur votre téléphone.

> **Caméra en local** : les navigateurs n'autorisent la caméra qu'en HTTPS ou sur `localhost`.
> Pour tester le scanner depuis un téléphone sur le réseau local, utilisez un tunnel HTTPS
> (`ngrok http 8000`, `cloudflared tunnel`) ou la saisie manuelle du code imprimé sous le QR.

---

## 2. Variables d'environnement

| Variable | Valeur conseillée | Rôle |
|---|---|---|
| `APP_ENV` | `production` | Désactive la campagne de démo, impose HTTPS et des mots de passe forts |
| `APP_DEBUG` | `false` | **Obligatoire en production** |
| `APP_URL` | `https://chasse.exemple.bf` | **Encodée dans les QR codes : à fixer AVANT l'impression** |
| `APP_KEY` | générée une fois | ⚠️ **Ne jamais la changer après la mise en service** (voir encadré ci-dessous) |
| `DB_*` | — | Connexion MySQL 8 |
| `SESSION_ENCRYPT` | `true` | Sessions chiffrées |
| `SESSION_SECURE_COOKIE` | `true` | Cookies envoyés uniquement en HTTPS |
| `CACHE_STORE` | `redis` (ou `database`) | Cache et limites de fréquence |
| `QUEUE_CONNECTION` | `redis` (ou `database`) | Envoi des SMS en file d'attente |
| `ADMIN_PATH` | ex. `gestion-x7k2` | Chemin de l'admin, moins devinable que `/admin` |
| `ADMIN_2FA_REQUIRED` | `false` (`true` conseillé en production) | Double authentification TOTP pour les super-admins |
| `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD` | — | Super-admin initial |
| `GAME_OTP_ENABLED` | `false` | `true` = code SMS à l'inscription ; `false` = code PIN à 4 chiffres |
| `GAME_PHONE_COUNTRIES` | `BF,CI,ML,NE,TG,BJ,SN,GH` | Pays acceptés pour les numéros |
| `GAME_DATA_RETENTION_DAYS` | `180` | RGPD : anonymisation automatique après la fin de la campagne |
| `TRUSTED_PROXIES` | `*` derrière Cloudflare ou un load balancer, vide sinon | Récupération de l'IP réelle |
| `SMS_DRIVER` | `orange`, `vonage`, `null` ou `log` | `log` est interdit en production |

> ⚠️ **`APP_KEY` chiffre le mot mystère et les jetons des QR.** Si elle change après la mise en
> service, le mot devient illisible et les images des QR déjà imprimés ne peuvent plus être régénérées.
> Sauvegardez-la dans un coffre-fort de mots de passe.

### 🔑 Points nécessitant une clé API

**Orange SMS API** (si `SMS_DRIVER=orange`), sur [developer.orange.com](https://developer.orange.com), offre « SMS Africa » :
- `ORANGE_SMS_CLIENT_ID`, `ORANGE_SMS_CLIENT_SECRET` : identifiants de l'application ;
- `ORANGE_SMS_SENDER_ADDRESS` : `tel:+226XXXXXXXX`, le numéro expéditeur associé au contrat ;
- `ORANGE_SMS_SENDER_NAME` : nom d'expéditeur, à faire valider par Orange (prévoir un délai) ;
- un forfait SMS doit être acheté et actif pour le Burkina Faso.

**Vonage** (si `SMS_DRIVER=vonage`) : `VONAGE_KEY`, `VONAGE_SECRET`, `VONAGE_SMS_FROM`.

L'OTP est **désactivé par défaut**. Pour le réactiver : `GAME_OTP_ENABLED=true`, un driver SMS
configuré et un worker de file actif. Chaque campagne peut aussi surcharger ce réglage depuis l'admin.

---

## 3. Déploiement en production

Exigences communes :
- **HTTPS obligatoire**, sans quoi la caméra est refusée par les navigateurs ;
- un cron pour le planificateur ;
- un worker de file pour les SMS.

### Option A — Laravel Forge

1. Créer un serveur (PHP 8.3, MySQL 8, Redis) et un site sur le domaine définitif.
2. Relier le dépôt Git et activer « Quick deploy ».
3. **Environment** : coller le `.env` de production (section 2), puis générer `APP_KEY` une seule fois.
4. **SSL** : activer Let's Encrypt.
5. **Deploy script** :
   ```bash
   cd /home/forge/chasse.exemple.bf
   git pull origin $FORGE_SITE_BRANCH
   $FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
   npm ci && npm run build
   $FORGE_PHP artisan migrate --force
   $FORGE_PHP artisan optimize
   $FORGE_PHP artisan queue:restart
   ```
6. **Queue** : ajouter un worker `redis` (ou `database`) avec `--tries=3 --timeout=30`.
7. **Scheduler** : activer la tâche `php artisan schedule:run` chaque minute.
8. Premier déploiement : `php artisan db:seed --class=AdminSeeder --force`.

### Option B — Serveur manuel (Ubuntu 24.04, Nginx)

```bash
sudo apt install nginx mysql-server redis-server supervisor certbot python3-certbot-nginx \
  php8.3-fpm php8.3-{mysql,gd,zip,intl,mbstring,bcmath,xml,curl,redis}
# Node 20 : via nodesource ou nvm

cd /var/www && sudo git clone <depot> chasse && cd chasse
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && nano .env && php artisan key:generate
php artisan migrate --force && php artisan db:seed --class=AdminSeeder --force
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
```

**Nginx** (`/etc/nginx/sites-available/chasse`) :
```nginx
server {
    listen 80;
    server_name chasse.exemple.bf;
    root /var/www/chasse/public;
    index index.php;
    client_max_body_size 2m;
    server_tokens off;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
    location /build/ { expires 1y; add_header Cache-Control "public, immutable"; }
    gzip on; gzip_types text/css application/javascript image/svg+xml;
}
```
Puis :
```bash
sudo ln -s /etc/nginx/sites-available/chasse /etc/nginx/sites-enabled/
sudo certbot --nginx -d chasse.exemple.bf
```

**Supervisor** (`/etc/supervisor/conf.d/chasse-worker.conf`) :
```ini
[program:chasse-worker]
command=php /var/www/chasse/artisan queue:work --tries=3 --timeout=30 --sleep=3
user=www-data
numprocs=1
autostart=true
autorestart=true
stdout_logfile=/var/www/chasse/storage/logs/worker.log
```

**Cron** (`sudo crontab -u www-data -e`) :
```
* * * * * cd /var/www/chasse && php artisan schedule:run >> /dev/null 2>&1
```

### Dimensionnement

Un VPS de 2 vCPU et 4 Go de RAM, avec MySQL et Redis sur la même machine, absorbe largement
plusieurs milliers de participants. Chaque scan ne fait que quelques requêtes indexées dans une
transaction courte.

Côté participant, les données transférées restent faibles :
- environ 75 Ko compressés au premier chargement ;
- le scanner (environ 16 Ko, plus son worker de 10 Ko) n'est chargé que sur la page Scanner.

---

## 4. Tests automatisés

Les tests utilisent une base **MySQL dédiée**, définie dans `phpunit.xml`. Elle n'est jamais la base de
développement.

```bash
mysql -u root -e "CREATE DATABASE SiaoTresor_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test
```

| Fichier | Scénarios |
|---|---|
| `ScanTest` | scan valide, scan répété, 2 QR de la même position, lettres en double (A@1 / A@6), QR désactivé ou révoqué, jeton invalide ou d'une autre campagne, campagne fermée, en pause, pas encore ouverte ou hors horaires, participant bloqué, délai minimal, mode « premier arrivé », saisie manuelle, limitation de fréquence, lien natif avant et après inscription |
| `ConcurrencyTest` | **8 processus PHP simultanés** (connexions MySQL distinctes) : même QR et même participant, 2 QR de la même position, « premier arrivé » entre 8 participants, compteur sans perte, quota de tentatives non dépassé, une seule réussite |
| `MysteryWordTest` | mot correct (insensible à la casse, aux espaces et aux accents), mot incorrect, tentatives limitées et essai bonus, lettres manquantes, campagne fermée, mot déjà trouvé, le mot n'apparaît jamais dans le HTML |
| `RegistrationTest` | OTP (expiration, 5 essais, limite d'envoi), unicité du téléphone quel que soit le format saisi, PIN (refus des PIN faibles, verrouillage), reconnexion |
| `AdminTest` | accès et rôles, 2FA obligatoire pour les super-admins, limitation des connexions, création de campagne, mot verrouillé, conditions d'activation, cycle de vie des QR, PNG/SVG/PDF/ZIP, exports CSV masqués et protégés contre l'injection de formules |
| `RewardTest` | premiers arrivés, classement par temps, tirage reproductible avec graine, exclusion des suspects, un seul lot par participant, validation par un super-admin uniquement |
| `SecurityAndPrivacyTest` | en-têtes HTTP, Markdown assaini (pas de XSS), export et suppression des données personnelles, purge RGPD |

---

## 5. Procédure de test terrain

À réaliser **sur le site du salon, sur le serveur de production, avant l'ouverture**, avec au moins
3 téléphones : un Android d'entrée de gamme, un iPhone, et un téléphone sur le réseau mobile (sans Wi-Fi).

### J-7 : préparation
1. Dans l'admin, créer la campagne définitive :
   - dates et horaires ;
   - mot mystère ;
   - mode de validation ;
   - nombre de tentatives ;
   - règlement validé par le service juridique, avec son numéro de version.
2. Générer les QR (par exemple 3 par position) et renseigner l'**emplacement physique** de chacun.
3. Télécharger le **PDF A4** et imprimer **un QR test** à part.
4. Vérifier que la page d'admin des campagnes n'affiche **aucun problème bloquant**.

### J-1 : recette sur place (campagne en statut « Active », date de début dans le passé)
| # | Test | Résultat attendu |
|---|---|---|
| 1 | Ouvrir l'URL publique en 3G/4G | Page d'accueil en moins de 3 s |
| 2 | S'inscrire (prénom, nom, téléphone, PIN) | Arrivée sur « Ma partie » |
| 3 | Se réinscrire avec le même numéro | Refus « Ce numéro participe déjà » |
| 4 | Scanner un QR avec le bouton « Scanner » | Lettre affichée avec animation |
| 5 | Rescanner le même QR | « Vous avez déjà scanné ce QR code » |
| 6 | Scanner avec l'**appareil photo natif** (sans ouvrir le site) | Ouverture du site et lettre validée |
| 7 | Refuser l'accès à la caméra | Message de secours et saisie manuelle proposée |
| 8 | Saisir le code imprimé sous un QR | Lettre validée |
| 9 | Scanner un QR désactivé depuis l'admin | « QR code momentanément désactivé » |
| 10 | Fermer le navigateur, revenir, se reconnecter | Progression conservée |
| 11 | Collecter toutes les lettres et proposer un mauvais mot | « Il vous reste N essais » |
| 12 | Proposer le bon mot en minuscules avec des espaces | « BRAVO » et heure de réussite enregistrée |
| 13 | Tester la lisibilité des QR selon la lumière (plein soleil, intérieur sombre) et la distance | Lecture en moins de 2 s |
| 14 | Admin → Tableau de bord | Les scans du test apparaissent |
| 15 | Admin → Journal | Événements présents (connexions, désactivations…) |

### Avant l'ouverture au public
1. **Supprimer les participants de test** : les anonymiser depuis leur fiche, ou repartir d'une campagne neuve.
2. Révoquer le QR test.
3. Vérifier que la campagne est « publique » et « Active ».
4. Poser les QR imprimés (plastifiés), puis relire les emplacements dans l'admin.

---

## 6. Exploitation pendant le salon

- **QR abîmé, volé ou photographié et partagé** : « Régénérer » dans l'admin. L'ancien QR est révoqué ; il faut imprimer le nouveau.
- **QR jamais scanné** (ligne surlignée dans le tableau de bord) : vérifier qu'il est bien visible.
- **Participants suspects** : filtre « Seulement les suspects ». Les signalements (même appareil, complétion trop rapide, rafale de QR invalides…) **ne bloquent jamais automatiquement** : c'est un humain qui décide.
- **Récompenses** : Admin → Récompenses → générer une proposition → vérifier chaque fiche → cocher « J'ai vérifié » → Valider → marquer « Notifié » puis « Lot remis ».
- **Sauvegarde** quotidienne de la base, par exemple `mysqldump --single-transaction`, conservée hors du serveur.
- **Journaux** : `storage/logs/security.log` (événements sensibles, 90 jours) et table `audit_logs`.

---

## 7. Architecture et sécurité

### Choix des bibliothèques
- **Lecture des QR : `qr-scanner` (nimiq)**. Très léger, il utilise le `BarcodeDetector` natif quand il existe, sinon un Web Worker : l'interface reste fluide sur les téléphones d'entrée de gamme. Le QR contenant une URL, l'appareil photo natif sert de solution de secours, en plus de la saisie manuelle.
- **Génération des QR : `endroid/qr-code` v6**. Activement maintenu, il produit du PNG et du SVG avec un libellé. Le PDF d'impression est généré par `dompdf`.

### Anti-triche
- **Jetons** : 192 bits aléatoires. L'URL ne contient jamais la lettre. En base, seul le SHA-256 du jeton sert à la recherche ; le jeton lui-même est chiffré.
- **Validation** : exclusivement côté serveur (`App\Services\ScanService`), dans une transaction.
- **Protection contre les doubles validations** :
  - `SELECT … FOR UPDATE` sur le participant ;
  - contraintes uniques en base (`participant_letters`, `qr_exclusive_claims`) ;
  - rejeu automatique de la transaction en cas d'interblocage ;
  - un ordre des verrous choisi pour éviter les interblocages dus aux clés étrangères.
- **Limitation de fréquence** :
  - scans : par participant, et par IP avec une limite large à cause du partage d'IP des opérateurs mobiles ;
  - SMS, connexion admin, inscription, et requêtes Livewire globales.
- **Mot mystère** : chiffré, jamais envoyé au navigateur, verrouillé au premier scan valide. Tentatives limitées par un index unique.

### Administration
- Rôles : lecteur, administrateur, super-administrateur.
- Double authentification TOTP pour les super-admins : désactivée par défaut, activable avec `ADMIN_2FA_REQUIRED=true` (conseillé en production).
- Chemin d'accès configurable.
- Journal d'audit en ajout seul.

### En-têtes HTTP
- CSP avec nonce ;
- `Referrer-Policy: no-referrer` ;
- `Permissions-Policy` limitant la caméra au site ;
- `X-Frame-Options: DENY` ;
- HSTS.

### Données personnelles (RGPD)
- Consentement au règlement versionné, consentement marketing séparé et facultatif.
- Page « Mes données » : export JSON et suppression.
- Anonymisation automatique après `GAME_DATA_RETENTION_DAYS` jours (`php artisan game:purge`).
- Exports CSV réservés aux super-admins et journalisés, avec téléphones masqués par défaut.
- Le cadre légal (loi burkinabè n° 001-2021/AN, CIL) est **à faire valider par le juriste d'Orange Money**.

### Identité visuelle
- Couleurs : orange `#FF7900`, noir et blanc. Motifs SVG légers inspirés du Faso Dan Fani et du bogolan.
- Polices système uniquement, aucun téléchargement de police.
- Logos officiels : déposer `public/images/logo-partenaires.svg` (fourni par Orange Money et le SIAO). Il remplace automatiquement le logo texte provisoire.
