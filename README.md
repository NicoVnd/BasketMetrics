# BasketMetrics 🏀

Application web de suivi et d’analyse de matchs de basket amateur. BasketMetrics permet de gérer ses équipes, d’importer des feuilles de match e-Marque / FFBB et de suivre les performances collectives et individuelles au fil des saisons.

Le projet est en cours de développement et a été pensé initialement pour le Basket Club Lezennois.

## Fonctionnalités

- Inscription, connexion et gestion des équipes propres à chaque compte.
- Gestion des joueurs et saisie manuelle des matchs et des statistiques.
- Import d’archives ZIP e-Marque avec OCR local, prévisualisation et correction avant enregistrement.
- Historique des matchs, scores par période issus des imports et consultation des documents sources.
- Bilans de saison : victoires, défaites, points marqués et encaissés, moyennes et classements internes.
- Fiches joueurs avec historique, temps de jeu, statistiques et graphiques d’évolution.
- Analyses de matchs, de joueurs et de saisons avec Gemini, disponibles à la demande et facultatives.

Les statistiques absentes restent inconnues : elles ne sont pas remplacées par zéro. Les analyses IA s’appuient sur les données enregistrées et ne remplacent pas les calculs de l’application.

## Technologies

| Composant | Technologies |
| --- | --- |
| Backend | PHP 8.3+, Symfony 7.4, Doctrine ORM et Migrations |
| Interface | Twig, Tailwind CSS, Symfony UX, Stimulus et Chart.js |
| Base de données | SQLite en local, PostgreSQL pour une configuration distante |
| Import PDF | smalot/pdfparser, Poppler, Tesseract.js et Sharp |
| IA facultative | API Gemini via Symfony HttpClient |
| Tests | PHPUnit |

## Prérequis

- PHP 8.3 ou supérieur avec les extensions `ctype`, `iconv`, `fileinfo`, `zip` et le pilote PDO de la base utilisée (`pdo_sqlite` ou `pdo_pgsql`).
- Composer.
- Symfony CLI pour le serveur de développement.
- Pour l’import avec OCR : Node.js 20+ et Poppler (`pdftoppm` accessible dans le PATH).

Tailwind utilise un exécutable autonome : Node.js sert au traitement OCR.

## Installation

Depuis la racine du dépôt cloné, copier `.env.example` vers `.env` et `.env.local` (fichiers ignorés par Git) :

```bash
# Linux / macOS
cp .env.example .env
cp .env.example .env.local
```

```powershell
# PowerShell
Copy-Item .env.example .env
Copy-Item .env.example .env.local
```

Dans `.env.local`, remplacer `APP_SECRET` par une valeur aléatoire. Pour en générer une :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Installer les dépendances :

```bash
composer install
```

La configuration fournie utilise SQLite, sans service externe :

```dotenv
DATABASE_URL="sqlite:///%kernel.project_dir%/var/basket.db"
```

Créer la base, appliquer les migrations et compiler les styles :

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php -d memory_limit=512M bin/console tailwind:build
```

Pour activer les imports avec OCR, installer également les dépendances Node :

```bash
npm ci --prefix tools/ocr
```

Si nécessaire, renseigner les chemins des exécutables dans `.env.local` :

```dotenv
PDFTOPPM_BINARY=pdftoppm
NODE_BINARY=node
```

## Utilisation

Lancer le serveur de développement :

```bash
symfony server:start -d --no-tls
```

Ouvrir [http://127.0.0.1:8000](http://127.0.0.1:8000), créer un compte, puis une équipe et ses joueurs. Ajouter ensuite un match manuellement ou importer une archive ZIP e-Marque depuis la page de l’équipe.

Lors d’un import, sélectionner le côté de l’équipe (locaux ou visiteurs), vérifier les données extraites et les associations de joueurs, puis confirmer l’enregistrement. Le format actuellement pris en charge est l’export FFBB portrait contenant un fichier `resume…pdf`, accompagné d’une feuille de match et, éventuellement, des positions de tirs. La qualité de l’OCR dépend des documents : une vérification reste nécessaire.

Le serveur Symfony lance automatiquement la surveillance des styles Tailwind. Pour l’arrêter :

```bash
symfony server:stop
```

Les instructions complémentaires, notamment le lancement sous Windows et les détails des imports, se trouvent dans [SETUP.md](SETUP.md).

## Configuration facultative

### Analyses Gemini

Renseigner une clé API et un identifiant de modèle accessible dans votre projet Google AI Studio :

```dotenv
GEMINI_API_KEY=votre_cle_api
GEMINI_MODEL=identifiant_du_modele
```

Sans ces valeurs, l’application reste utilisable sans IA. Les analyses sont déclenchées depuis l’interface et conservées en base. Les appels sont soumis aux quotas et à la tarification de votre projet Gemini.

### PostgreSQL / Supabase

Remplacer `DATABASE_URL` par l’URL PostgreSQL de votre environnement et activer `pdo_pgsql`. Pour Supabase, utiliser les paramètres de connexion du projet avec `sslmode=require`, puis appliquer les migrations à la base cible.

Les secrets doivent rester dans `.env.local` (ignoré par Git) ou dans les variables d’environnement de l’hébergement.

## Vérifications

```bash
composer validate
composer check-platform-reqs
php bin/console lint:container
php bin/console lint:twig templates
php bin/phpunit
```

La suite couvre notamment les calculs statistiques, les saisons, l’authentification, l’isolation des comptes, les imports et les analyses Gemini avec un client HTTP simulé. Les tests fonctionnels utilisent une base SQLite dédiée.

## Structure du projet

```text
assets/        Styles et contrôleurs JavaScript
config/        Configuration Symfony
migrations/    Migrations de la base de données
public/        Point d’entrée web
src/           Contrôleurs, entités, formulaires et services métier
templates/     Vues Twig
tests/         Tests unitaires et fonctionnels
tools/ocr/     Traitement OCR local des documents FFBB
```

## Déploiement

Prévoir un environnement PHP compatible, une base de données et, pour les imports OCR, Node.js et Poppler. Compiler les assets pour la production et appliquer les migrations avant utilisation.

Les documents importés sont conservés localement : l’hébergement doit disposer d’un stockage privé persistant. La commande `php bin/console app:imports:cleanup` permet de purger les aperçus d’import expirés. Le serveur de développement est réservé à une utilisation locale.

## Contribution

Les signalements de bugs et propositions d’amélioration peuvent être ouverts dans les issues du dépôt. Pour une modification, décrire le problème résolu et les vérifications effectuées dans la pull request. Utiliser des données fictives ou anonymisées pour les exemples et les tests.

## Licence

Aucune licence open source n’est actuellement fournie. Le projet est déclaré `proprietary` dans `composer.json`.
