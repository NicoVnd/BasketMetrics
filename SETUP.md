# Démarrage du projet

Le socle Symfony 7.4 est installé avec Twig, Doctrine/Migrations, Stimulus,
AssetMapper, Tailwind CSS, Chart.js, HttpClient, PDFParser et PHPUnit.
L'authentification, la saisie manuelle et l'import ZIP e-Marque avec OCR et
prévisualisation sont disponibles. Les bilans de saison restent à développer.

## Sur cette machine

La configuration `.env.local` contient un secret aléatoire et utilise SQLite
(`var/basket.db`) pour démarrer sans compte externe. Aucun identifiant externe
n'est nécessaire pour afficher l'accueil.

```powershell
symfony server:start -d --no-tls
```

Ouvrir http://127.0.0.1:8000. Le serveur Symfony lance aussi la surveillance Tailwind.
Arrêter avec `symfony server:stop`.

## Après récupération du projet sur une autre machine

Installer PHP 8.3+, Composer et Symfony CLI, puis :

```powershell
composer install
Copy-Item .env.example .env.local
# Remplacer APP_SECRET par un secret aléatoire dans .env.local.
php -d memory_limit=512M bin/console tailwind:build
symfony server:start -d --no-tls
```

Node.js n'est pas requis pour les styles : Tailwind utilise son exécutable autonome.
Il est nécessaire pour l'OCR des imports (voir ci-dessous).
Les versions des dépendances sont enregistrées dans `composer.lock`.

Si Symfony CLI rencontre un verrou de fichier de journal sous Windows, lancer
`./tools/serve.ps1` et, dans un deuxième terminal,
`php bin/console tailwind:build --watch`.

Le lanceur PHP active les limites nécessaires au dépôt des ZIP : 20 Mo pour les
fichiers, 24 Mo pour les requêtes, 256 Mo de mémoire PHP et 180 s d'exécution.

## Connexion Supabase ultérieure

Remplacer DATABASE_URL dans `.env.local` avec les paramètres PostgreSQL exacts
du tableau de bord Supabase, en conservant `sslmode=require`.
Encoder les caractères spéciaux des identifiants dans l'URL.
Ne jamais versionner `.env.local` ni transmettre une clé privilégiée au navigateur.

PHP dispose de la DLL PostgreSQL mais elle n'est pas activée globalement sur cette
machine. Pour les commandes PostgreSQL, utiliser le lanceur local :

```powershell
./tools/php.ps1 bin/console doctrine:query:sql "SELECT 1"
```

Pour le serveur web avec Supabase, activer `extension=pdo_pgsql` dans le php.ini
utilisé par Symfony et redémarrer le serveur. SQLite fonctionne sans ce changement.
Gemini et Supabase Storage restent facultatifs et non configurés.

## Vérifications et développement

```powershell
composer validate
composer check-platform-reqs
php bin/console lint:container
php bin/console lint:twig templates
php bin/phpunit
```

Après récupération de nouvelles migrations, les appliquer avec
`php bin/console doctrine:migrations:migrate`. Après modification des entités :

```powershell
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

## Équipes et saisie manuelle

Après connexion, `/` affiche les équipes du compte et le bouton de création.
Dans une équipe : ajouter des joueurs, créer un match, puis renseigner et
modifier les statistiques individuelles. Le score, l'adversaire, la date et
l'état du match peuvent également être corrigés.

Tables ajoutées et migration appliquée à la base locale :

| Table | Rôle |
| --- | --- |
| `team` | Nom, catégorie et propriétaire (`app_user`) |
| `player` | Identité, numéro facultatif et équipe |
| `match_game` | Équipe suivie, adversaire, date, domicile/extérieur, état et scores |
| `player_match_stat` | Une ligne unique par joueur et match : points, secondes jouées, fautes, 2 pts/3 pts/LF réussis |

Les clés étrangères relient les tables. Les contrôles d'accès s'appliquent
aux consultations et aux écritures : seul le propriétaire gère son équipe.
Les formulaires ne permettent de choisir ni propriétaire ni joueur extérieur
à l'équipe pour les statistiques.

Une case vide est conservée en `NULL` ; un zéro explicite reste zéro. Le temps
de jeu se saisit en `min:ss` et est enregistré en secondes. Les incohérences
entre paniers et points ou entre total individuel et score d'équipe sont
signalées sans corriger les données à la place de l'utilisateur. Un match
terminé exige ses deux scores. Dates saisies et affichées en Europe/Paris,
enregistrées en UTC.

Pour ce premier périmètre, un joueur appartient à une équipe et l'adversaire
est un nom saisi sur le match. Le numéro de maillot n'est jamais l'identifiant
du joueur. Les effectifs par saison, transferts, scores par période et bilans
seront ajoutés dans une prochaine étape. Les périodes sont déjà récupérées lors
des imports FFBB.

## Import ZIP e-Marque

Dans une équipe, utiliser **Importer un ZIP e-Marque**. Choisir si l'équipe
correspond aux locaux (A) ou aux visiteurs (B), déposer l'archive et attendre
l'analyse locale. Les documents ne sont transmis à aucun service d'OCR ou d'IA.

La disposition prise en charge est l'export FFBB portrait avec `resume…pdf`
(requis), `feuillematch…pdf` (licences et périodes) et, facultativement,
`positiontir…pdf`. Les PDF du ZIP fourni pour le développement sont des images :
le texte est lu par Poppler + Tesseract.js, avec nettoyage des lignes des tableaux
et une seconde lecture des cellules numériques. Les mises en page non reconnues
sont refusées. Un autre format d'export doit être ajouté avec ses propres tests.

### Dépendances OCR

- PHP `zip` et `fileinfo` (déjà présents sur cette machine).
- Node.js 20+ compatible avec les versions verrouillées de `tools/ocr/package-lock.json`.
- Poppler (`pdftoppm`) : sur cette machine, le chemin fourni par le runtime Codex
  est configuré dans `.env.local`. Sur Linux, installer `poppler-utils`.

Installer les dépendances à la racine du projet :

```powershell
Push-Location tools/ocr
npm ci
Pop-Location
```

Définir `PDFTOPPM_BINARY` et `NODE_BINARY` dans `.env.local` si les exécutables ne
sont pas dans le PATH. Aucune clé API n'est nécessaire. Le modèle OCR français
est installé avec npm : les imports ne téléchargent pas de modèle à l'exécution.
Pour un déploiement, prévoir Node, Poppler, les dépendances OCR et un espace
temporaire inscriptible ; mesurer la mémoire disponible avant d'utiliser une
petite instance gratuite.

### Vérification et enregistrement

- Aucun joueur ou match n'est créé pendant l'analyse : un `import_job` privé
  conserve un aperçu pendant 24 heures.
- Vérifier le score, la date, l'adversaire et les périodes ; corriger toute
  valeur OCR douteuse. Les cellules illisibles restent inconnues (`NULL`).
- Chaque ligne propose un joueur existant ou une création. La licence FFBB
  constitue l'identifiant prioritaire **au sein de l'équipe** ; le nom/prénom
  normalisé (casse, accents, ponctuation) est utilisé à défaut. Un surnom, prénom
  abrégé ou homonyme nécessite un choix explicite. Le numéro n'est jamais une
  identité entre matchs.
- Les licences connues ne sont pas remplacées silencieusement ; deux lignes
  ne peuvent pas cibler le même joueur. Une identité déjà reconnue ne peut pas
  être recréée comme un nouveau joueur par cet import.
- Le numéro, la présence dans le cinq de départ et le statut de capitaine sont
  enregistrés sur `player_match_stat`. Le numéro habituel du joueur existant
  est conservé.
- Un match manuel du même jour peut être sélectionné pour être complété. Les
  valeurs des joueurs concernés sont alors remplacées après confirmation ;
  les autres joueurs ne sont pas supprimés.
- L'empreinte des PDF (indépendante de l'ordre et des métadonnées ZIP) et une clé
  de rencontre empêchent les réimports silencieux. Les contraintes en base et
  une transaction protègent l'enregistrement du match et des statistiques.

`period_score` conserve les périodes. `match_game.import_metadata` conserve
compétition, lieu, poule, arbitres, informations des clubs/entraîneurs lisibles,
indicateurs collectifs et statistiques adverses. Les valeurs adverses et les
informations complémentaires restent marquées comme des données OCR non
corrigées ; elles ne créent pas de joueurs dans l'effectif de l'utilisateur.
Les champs inconnus et les avertissements sont conservés. Les cartes de tirs
restent dans les documents : aucune coordonnée, tentative, passe ou rebond
n'est inventé à partir de l'image.

### Fichiers et protections

Les PDF originaux sont stockés sous `var/imports/<id aléatoire>/`, jamais dans
`public/`. Leur téléchargement vérifie le propriétaire de l'équipe. Les PNG
temporaires sont supprimés après lecture. Annuler un aperçu supprime ses fichiers.
Les imports validés conservent leurs PDF localement ; prévoir un stockage privé
persistant avant tout déploiement sur un hébergeur à disque éphémère.

L'archive est lue sans extraction de ses chemins. Contrôles : PDF uniquement,
signature PDF, chemins absolus/traversée et liens symboliques interdits, archives
chiffrées refusées, 40 entrées maximum, 8 PDF maximum, 10 Mo par document, 64 Mo
décompressés au total, ratio de compression limité, 20 pages par PDF et 30 pages
au total. Le résumé (deux premières pages) et la première page de la feuille sont
analysés ; les autres pages restent consultables comme sources. Les processus
externes utilisent des tableaux d'arguments, des délais bornés et un environnement
réduit sans secrets applicatifs. Le dépôt est limité à 4 analyses par compte et
par tranche de 15 minutes. CSRF et propriété de l'équipe sont contrôlés.

Purger les aperçus expirés (à lancer régulièrement sur l'hébergement) :

```powershell
php bin/console app:imports:cleanup
```

Les tests utilisent des identités fictives et une base SQLite isolée ; aucun PDF
personnel n'est inclus dans les fichiers de tests versionnables. La lecture OCR
réelle a été vérifiée séparément sur le ZIP fourni.

## Authentification

Les pages `/login` et `/register` sont publiques ; le reste de l'application
demande une connexion. L'inscription crée uniquement un compte `ROLE_USER`.
Les identifiants (3 à 32 lettres ASCII, chiffres ou underscores) sont uniques
sans distinction de casse. Les nouveaux mots de passe exigent 12 caractères
minimum et 72 octets maximum pour éviter toute troncature avec bcrypt.

Les mots de passe sont hachés par Symfony ; les formulaires et la déconnexion
POST utilisent des jetons CSRF liés à la session. Les connexions sont limitées
à 5 échecs par identifiant/IP sur 15 minutes, avec une limite globale par IP,
et les inscriptions à 5 tentatives par IP et par heure.

La migration des utilisateurs a été appliquée sur SQLite localement. Elle
contient également une branche PostgreSQL pour Supabase, à vérifier sur une
base de préproduction avant déploiement.

Le compte local `BasketTest` a été créé avec le mot de passe fourni dans le chat.
Il n'est pas créé par les migrations et son mot de passe n'est pas stocké dans
les sources. `php bin/console app:create-test-user` permet sa création en mode
dev avec saisie masquée ; la commande ne modifie pas un compte existant.
Ce compte de démonstration doit rester sur l'environnement de développement.

En production : utiliser HTTPS (imposé par les règles d'accès), un APP_SECRET
propre à l'environnement et configurer les proxys de confiance uniquement avec
les adresses du fournisseur si TLS est terminé par un proxy. Les cookies sont
HttpOnly, SameSite=Lax et Secure en production. Pour plusieurs instances,
partager le stockage des sessions et des limiteurs (par exemple Redis).
Le serveur PHP de développement n'est pas destiné à la production.

Les tests fonctionnels recréent uniquement `var/auth_test.db` et ne touchent
pas à la base de développement. L'inscription utilise un identifiant : il n'y a
pas encore d'adresse email, de vérification email ni de récupération de mot de
passe. Ces parcours nécessitent un choix d'adresse d'expédition et de service
mail. Tous les comptes inscrits disposent actuellement des mêmes accès.


## Suivi des saisons et des joueurs

La page d’une équipe propose un sélecteur de saison, le bilan V/D (et nul si
nécessaire), le pourcentage de victoires, les points marqués/encaissés, les
moyennes, les bilans domicile/extérieur et un graphique des scores. Le tableau
interne se trie par moyenne de points, total de points, minutes moyennes ou
points pour 40 minutes. Cliquer sur un joueur ouvre sa fiche : historique,
numéro porté par match, participations, totaux, moyennes, record et évolution.

Une saison couvre le 1er juillet au 30 juin en heure de Paris. Les bornes UTC
sont calculées avant la sélection en base ; un match à minuit le 1er juillet
appartient bien à la nouvelle saison. Le filtre porte sur la date du match.
La saison actuelle est proposée ; sans match, la plus récente saison contenant
des matchs s’affiche par défaut. Les classements portent sur l’effectif actuel.

Seuls les matchs terminés entrent dans les totaux. Le résultat V/D nécessite
les deux scores. Chaque moyenne individuelle divise le total des valeurs
connues par le nombre de matchs joués où cette valeur est renseignée. Un zéro
est une valeur connue ; une case vide reste inconnue. Le nombre de matchs
utilisé est affiché. Les points pour 40 minutes sont calculés à partir des
points et secondes des mêmes matchs, avec temps strictement positif.

Le champ Participation permet de confirmer « A joué » ou « N’a pas joué ».
En mode déduction, un temps positif, des points, un panier ou une titularisation
confirment une participation ; un temps nul sans autre preuve indique une
non-participation. Sans preuve, la participation reste inconnue. Une faute seule
ne prouve pas une entrée en jeu (faute de banc possible). Une absence explicite
avec des points, un temps positif, des paniers ou une titularisation est rejetée.
Les absences et participations inconnues sont exclues des moyennes, et restent
consultables dans l’historique. Les matchs sans fiche ne sont pas assimilés à
une absence. Les totaux sont recalculés à chaque consultation après correction.

La migration Version20261015160000 ajoute la participation sans modifier les
statistiques existantes. Une sauvegarde locale précède son application :
var/basket-before-season.db. La suite de tests couvre les calculs, les limites
de saison, les valeurs inconnues/zéro, les ex æquo, les doublons, les changements
de numéro via les imports, les corrections et l’isolation des comptes.
