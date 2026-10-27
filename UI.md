# BasketMetrics — UI Courtside

## Direction artistique

Fond ivoire `#F5F2EB`, surfaces blanches, navigation graphite grisé `#3D4246`, actions mandarine `#FF6B35`, analyse cobalt `#4965ED`. Les liens utilisent une mandarine assombrie `#B33E16` pour rester lisibles sur fond clair. Les victoires et défaites gardent des couleurs sémantiques distinctes.

Les tokens partagés sont dans `assets/styles/app.css` : couleurs, surfaces, espacements, typographies, rayons, bordures, transitions et largeur de navigation. Seuils responsive : 640, 900 et 1200 px. Inter et Sora sont hébergées localement avec `font-display: swap`.

Les KPI forment un bandeau de cinq segments sur grand écran, deux lignes complètes sur tablette et une grille adaptée sur mobile. Le bilan victoires/défaites ouvre le bandeau dans une carte graphite plus large, avec deux grands chiffres et des libellés complets. Le taux de victoire reste visible en bleu, avec une importance secondaire. Le graphique orange/cobalt utilise aussi une ligne pointillée pour différencier les séries sans dépendre uniquement des couleurs. Les repères de terrain sont des SVG décoratifs, exclus de l’arbre accessible.

## Composants et navigation

- `statistics/kpis.html.twig` : indicateurs des services existants.
- `match/list.html.twig` : scores et résultats accessibles.
- `components/court.html.twig` : motif de terrain réutilisable.
- `workspace.html.twig` : navigation et structure partagées.

Les destinations restent distinctes : `/teams`, `/teams/{id}/matches`, `/teams/{id}/players`, `/teams/{id}/season`. La saison et les états actifs sont conservés. La fiche historique `/teams/{id}` reste disponible.

Les listes déroulantes gardent une flèche à droite, une zone réservée au texte et le fonctionnement natif au clavier. Le mode Windows à contraste forcé retrouve la flèche système.

## Mouvement

`motion_controller.js` utilise IntersectionObserver et Web Animations pour une apparition unique des sections (380 ms, décalage plafonné à 135 ms). Le contenu reste visible sans JavaScript. Les animations sont annulées à la déconnexion du contrôleur et lorsque la réduction des animations est activée. Les éléments déjà révélés ne rejouent pas lors d’un retour Turbo.

Les graphiques apparaissent en 480 ms ; les survols de boutons et cartes durent 180 ms. Aucun chiffre n’est remplacé par un compteur animé. Le contrôleur, les graphiques et les styles respectent `prefers-reduced-motion`. Aucune dépendance supplémentaire n’a été ajoutée.

## Données et import

La vue d’ensemble réutilise SeasonStatistics et StatisticsCharts. Les inconnues restent « — » et les moyennes indiquent leur échantillon. Aucun modèle, calcul, API ou traitement OCR/IA n’est modifié par le thème.

Le dépôt ZIP, la validation serveur, la correction et l’enregistrement sont conservés. Pendant l’OCR, un indicateur indéterminé explique le traitement sans inventer de pourcentage. Les écrans rendus côté serveur n’affichent pas de skeleton artificiel.

## Vérifications

Compilation Tailwind, validation des 25 vues Twig, validation du conteneur Symfony et suite PHPUnit : 50 tests, 464 assertions.

Contrastes calculés : texte principal/ivoire 12,44:1 ; texte secondaire/ivoire 5,11:1 ; blanc/cobalt 4,81:1 ; texte du bouton/mandarine 6,36:1. Les principales combinaisons de petits textes dépassent 4,5:1.

Les contrôles visuels utilisent une base QA séparée, jamais la base de production. Les données de démonstration ne font pas partie de l’application.

Les scores des listes sont agrandis et accompagnés du résultat en toutes lettres. Le score de la fiche match est prioritaire : les actions de gestion se trouvent dessous. Sur mobile, les équipes occupent la première ligne et le score dispose de toute la largeur. Les scores inconnus restent neutres et les matchs nuls sont explicitement distingués.

Ordre du menu : Vue d’ensemble, Matchs, Statistiques de saison, Joueurs, Importer un match, puis Mes équipes et Créer une équipe dans le groupe Gestion. Sans équipe sélectionnée, seules les destinations disponibles sont présentées.

Les aplats sombres utilisent #3D4246 ; les textes principaux utilisent #282D31. Contrastes : liens du menu 7,32:1, texte ivoire sur graphite 9,09:1.

`components/basketball_visual.html.twig` fournit des illustrations locales décoratives de ballon et de feuille de match pour les états vides et l’import. Elles ne représentent aucune donnée enregistrée et ne déclenchent aucune requête externe.

## Présentation publique

La route `/decouvrir` présente la plateforme aux visiteurs et aux utilisateurs connectés. `/` affiche cette présentation aux visiteurs anonymes et conserve le dashboard pour les comptes connectés : les anciens liens et la destination après connexion continuent de fonctionner.

Seules les routes exactes `/`, `/decouvrir`, `/login` et `/register` sont publiques. Le contrôleur du dashboard vérifie la présence du compte avant toute requête de données métier. Les équipes, joueurs, imports et analyses restent protégés.

La page comprend une introduction, une illustration du parcours sans statistiques fictives, les étapes d’import/vérification/analyse, les fonctionnalités match/joueur/saison, les principes de traitement des données manquantes, une FAQ native et les appels à l’inscription ou au dashboard selon la session. Aucun tarif, témoignage ou résultat sportif n’est inventé.

Styles dédiés dans `assets/styles/landing.css`, tous limités à la présentation ; menu mobile en `<details>` avec fermeture sur navigation et Échap, FAQ utilisable sans JavaScript, animations partagées respectant la réduction des mouvements. Les métadonnées de description et Open Graph utilisent un point d’entrée canonique `/decouvrir`.
