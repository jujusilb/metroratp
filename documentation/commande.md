# Journal des commandes (Claude)

Historique des commandes shell/PowerShell exécutées par Claude dans ce projet, avec une
explication rapide de leur objectif. Alimenté en continu (session courante + sessions futures).

## Session du 2026-08-10 — Scan du site pour `report.md`

| Commande | Objectif |
|---|---|
| `ls -la` | Vue d'ensemble du dossier racine du projet. |
| `ls documentation/ src/ src/Controller/ src/Entity/ src/Form/ src/Service/ templates/` | Repérer la structure Symfony (contrôleurs, entités, formulaires, templates) avant le scan. |
| `grep -i "TODO\|FIXME\|XXX\|@deprecated\|bug" src/` | Chercher des marqueurs de bugs connus laissés dans le code. |
| `cat .env*` / recherche de fichiers `.env*` | Vérifier la config d'environnement locale (base de données, secret d'app). Aucun `.env` ni `.env.local` trouvé au départ. |
| `Get-Service *mysql*` puis `Test-NetConnection 127.0.0.1:3306` | Vérifier si MySQL local (XAMPP) tournait déjà — non. |
| `C:\xampp\mysql_start.bat` | Démarrer MySQL local (XAMPP) pour pouvoir faire tourner le site en local. |
| Écriture de `.env.local` puis `.env` (`DATABASE_URL`, `APP_ENV`, `APP_SECRET`) | Fichiers de config minimum requis par Symfony pour démarrer — absents du dossier, recréés localement uniquement pour le scan (non commités). |
| `php bin/console --version` | Vérifier que Symfony démarre correctement après config. |
| `mysql ... -e "SHOW DATABASES;"` puis `SELECT COUNT(*) FROM station/utilisateur` | Confirmer que la base locale `metroratp` existe et contient des données (14244 stations, 2 utilisateurs). |
| `php -S 127.0.0.1:8000 -t public public/index.php` + `curl` | Démarrer un serveur PHP local pour tester le site dans le navigateur. |
| `php bin/console debug:router` / `cache:clear` / `debug:container ...` | Diagnostiquer un problème local : aucune route ni aucun service `App\...` n'était enregistré dans le conteneur. |
| `php -r '...Finder...'` (script inline) | Vérifier que Symfony Finder voit bien les fichiers de `src/Controller` (oui, 23 fichiers). |
| `php -r '...new App\Kernel...'` (boot manuel du kernel) | Isoler le problème : le kernel démarre sans erreur mais `getProjectDir()` renvoie `...\metroratp\src` au lieu de `...\metroratp` — signe que **`composer.json` est absent** de ce dossier (racine du projet), ce qui fausse la détection du dossier racine. |
| `ls composer.json composer.lock` | Confirmer l'absence de `composer.json`/`composer.lock` à la racine de cette copie du projet. |
| `ls .git/worktrees/...`, lecture de `gitdir`/`HEAD` | Comprendre l'état du dossier `.git` (incomplet ici — le vrai checkout Git est ailleurs, dans `Documents/.../metroratp/.claude/worktrees/...`). Explique pourquoi `git status`/`git log` échouent dans ce dossier Desktop. |
| `Stop-Process -Name php -Force` | Arrêter le serveur PHP de test lancé plus haut (devenu inutile après le diagnostic ci-dessus). |

**Conclusion du diagnostic local** : ce dossier (`Desktop\metroratp`) semble être une copie du projet à laquelle il manque `composer.json`, `composer.lock`, et un `.git` complet (le vrai dépôt Git vit dans `Documents\...\metroratp`). Le site ne peut donc pas tourner localement depuis ce dossier tel quel. Le scan a été réorienté vers la lecture de code + test du site réel en production (Hostinger, voir `.github/workflows/tests.yml`).

**Suite du diagnostic (toujours 2026-08-10/11)** : `composer.json`/`composer.lock`/`.env` ont été
complétés en cours de route (par un hook ou en parallèle) avec les vraies dépendances du projet —
ça a débloqué l'enregistrement des routes/services. Suite des commandes :

| Commande | Objectif |
|---|---|
| Écriture d'un `composer.json` minimal (`{"autoload":{"psr-4":{"App\\":"src/"}}}`) | Test d'hypothèse : `Kernel::getProjectDir()` remonte les dossiers depuis `src/Kernel.php` jusqu'à trouver `composer.json` — son absence faisait croire au kernel que la racine du projet était `src/` au lieu de la vraie racine, ce qui cassait l'auto-découverte des services `App\...` (aucune route, aucun contrôleur enregistré). Un simple fichier présent suffit à corriger la détection. |
| `rm -rf src/var` | Supprimer un dossier de cache généré par erreur au mauvais endroit (`src/var/cache`) pendant que `getProjectDir()` était encore cassé. |
| `rm -rf var/cache/*` (répété) | Forcer Symfony à recompiler le conteneur de services après chaque changement de config. |
| `php bin/console debug:router` / `debug:container` | Vérifier que les routes et services `App\...` apparaissent bien après correction. |
| Ajout de `DEFAULT_URI` dans `.env` | Variable requise par `symfony/routing` pour générer des URLs en CLI (absente, faisait planter `debug:router`). |
| `php bin/console importmap:require @fontsource/baloo-2` puis `@fontsource/baloo-2/700.css` | La police utilisée par `assets/app.js` n'était pas encore téléchargée dans `assets/vendor/` (AssetMapper) sur cette copie locale — commande officielle Symfony pour la récupérer. |
| `APP_ENV=prod APP_DEBUG=0 php -S ...` (test) | Vérifier si le mode strict d'AssetMapper (`missing_import_mode: strict` en dev, `warn` en prod, voir `config/packages/asset_mapper.yaml`) expliquait une erreur 500 restante sur les imports JS sans extension `.js` (`./js/style-station-picker`). Résultat : erreur identique même en mode "prod" simulé — cette copie locale n'a pas pu être rendue 100% fonctionnelle dans le temps imparti ; **le site réel en production n'a pas ce problème** (vérifié directement dans le navigateur sur https://julien-silberstein.fr). |
| `mysql ... SELECT COUNT(*) FROM station/desserte/troncon/correspondance/ligne/acces/sortie` | Mesurer la taille des tables pour évaluer l'impact du bug "pages de liste sans pagination" (voir `report.md`) : 31787 dessertes, 14244 stations, etc. |
| `grep -n "findAll()" src/Controller/*.php` puis inspection des contrôleurs restants | Confirmer que **tous** les contrôleurs d'administration (pas seulement Station) chargent la table entière sans pagination, malgré `KnpPaginatorBundle` installé et jamais utilisé (`grep PaginatorInterface|knp_paginator src/` → aucun résultat). |
| Navigation navigateur sur `https://julien-silberstein.fr` (accueil, `/inscription`, `/trajet`) | Test du site réel en production en complément du code, puisque cette copie locale n'était pas pleinement fonctionnelle. `/trajet` redirige vers `/login` (authentification requise, confirmé dans `security.yaml`) : le calculateur de trajet n'a donc pas pu être testé en conditions réelles sans compte — analyse basée sur le code (`TrajetController`, `assets/js/trajet-autocomplete.js`) à la place. |
| `Stop-Process -Name php -Force` (final) | Arrêter le serveur PHP local de test, plus nécessaire une fois le scan terminé. |

**Note** : MySQL local (XAMPP) a été laissé démarré (`C:\xampp\mysql_start.bat`) au cas où il serait utile pour la suite ; à arrêter avec `C:\xampp\mysql_stop.bat` si besoin.

## Session du 2026-08-11 — Affichage du mode de transport dans le calculateur de trajet

| Commande | Objectif |
|---|---|
| `grep -n "jest" package.json` / `ls node_modules/.bin/jest*` | Vérifier si Jest est installé pour pouvoir lancer les tests JS existants (`trajet-autocomplete.test.js`) après modification — non disponible sur cette copie locale (cohérent avec `package.json` incomplet, voir session précédente). |
| Écriture de `preview-autocomplete.html` (scratchpad) + `php -S 127.0.0.1:8001 -t <scratchpad>` | Vérifier visuellement le nouveau rendu des suggestions (mode à gauche / station+ville à droite) sans dépendre de l'app Symfony complète (bloquée par le souci AssetMapper de la session précédente) : mini page HTML qui importe directement `creerLigneSuggestion`/`afficherSuggestions` réécrites, avec Bootstrap via CDN pour le rendu des classes `list-group-item`. |
| `Stop-Process -Name php -Force` + suppression du fichier de preview | Nettoyage une fois la vérification visuelle faite. |

## Session du 2026-08-11 (suite) — Erreur 500 urgente sur `/login` (et tout le site)

Le site plantait en local avec une 500 Symfony (`Unable to find asset "./js/style-station-picker"`)
sur `base.html.twig:18`. Diagnostic complet et corrections :

| Commande | Objectif |
|---|---|
| Edit `assets/app.js` : ajout de `.js` aux imports locaux (`./js/style-station-picker.js` etc.) | Cause directe du 500 : AssetMapper (appelé par `importmap('app')` dans `base.html.twig`) exige l'extension explicite sur les imports relatifs, contrairement à Webpack. |
| `symfony server:start -d --port=8000` (remplace `php -S`) | Le serveur PHP intégré (`php -S`) servait les fichiers `.css`/`.js` avec le mauvais `Content-Type` (`text/html`), donc la page s'affichait sans aucun style. Le serveur CLI Symfony gère les types MIME correctement — **à utiliser systématiquement pour ce projet en local**, jamais `php -S`. |
| Création puis suppression d'un compte `claude_test_scan` via le formulaire d'inscription | Pour tester le calculateur de trajet connecté, sans jamais manipuler le mot de passe du vrai compte de l'utilisateur (règle stricte : je ne saisis jamais un mot de passe, même donné volontairement). Compte supprimé en base une fois la vérification terminée. |
| `npx encore dev` (plusieurs fois) | Reconstruire le bundle JS/CSS (`public/build/`) après corrections. A révélé 2 bugs de plus (voir ci-dessous), corrigés un par un jusqu'à un build propre. |
| Edit `assets/stimulus_bootstrap.js` | Le fichier mélangeait le code d'init Stimulus **ancien** (`@symfony/stimulus-bridge`, package npm réel, compatible Encore) et **nouveau** (`@symfony/stimulus-bundle`, n'existe pas sur npm — package PHP-only pensé pour AssetMapper) → erreur de syntaxe ("Identifier already declared") qui bloquait tout le build Encore. Remis à la version stimulus-bridge (la seule qui marche avec Encore, qui est le système réellement utilisé pour livrer le JS). |
| `npm install --save-dev @symfony/stimulus-bridge @symfony/ux-turbo` | Packages npm requis par `assets/stimulus_bootstrap.js`/`assets/controllers.json`, absents de `node_modules` sur cette copie (même souci que `sass`/`sass-loader`, déjà présents eux, et `composer.json` en session précédente : fichiers de config incomplets sur cette copie Desktop). |
| Edit `webpack.config.js` : décommenté `.enableSassLoader()` | Sans ça, Encore ne sait pas compiler `assets/styles/app.scss` (import Sass) → erreur de build. Cohérent avec le style qui s'affichait déjà correctement sur le site réel : cette ligne devait être active sur la vraie config. |
| Edit `templates/base.html.twig` : suppression de `importmap('app')` + de la ligne dupliquée `encore_entry_script_tags('app')` | Cause racine de fond : le template chargeait le JS par **deux systèmes en même temps** (AssetMapper live + bundle Encore précompilé), ce qui produisait des dizaines d'erreurs (imports non résolus type `bootstrap`, écouteurs d'événements dupliqués sur la carte SVG du trajet, etc.) — probablement la vraie explication derrière "le site il bug trop trop". Ne reste que le bundle Encore (déjà celui utilisé pour le déploiement prod, voir `.github/workflows/tests.yml`). |
| Vérification via `fetch('/build/app.js', {cache:'no-store'})` dans le navigateur | Confirmer que le bundle reconstruit contient bien le nouveau code (recherche du texte `suggestion-mode`) — le cache HTTP du navigateur renvoyait une version périmée sur un fetch normal, d'où l'importance du `no-store` pour vérifier après un rebuild en dev. |
| `mysql ... DELETE FROM utilisateur WHERE username='claude_test_scan'` | Nettoyage du compte de test créé pour la vérification. |

**Point d'attention** : à deux reprises pendant cette session, un fichier que je venais de modifier
s'est retrouvé revenu à sa version d'avant mon edit un peu plus tard dans la conversation. Si tu
modifies des fichiers en parallèle dans ton éditeur pendant que je travaille sur les mêmes fichiers,
ça peut expliquer une partie de la confusion — dis-le-moi si c'est le cas.

## Session du 2026-08-11 (suite 2) — "l'autocomplétion ne marche plus" : cache navigateur, pas un bug

Après les corrections ci-dessus, le message "ça marche pas" a persisté. Diagnostic :

| Commande | Objectif |
|---|---|
| Ajout temporaire de marqueurs globaux (`window.__appJsTopLevelRan = true`) au tout début de `assets/app.js`, rebuild, puis lecture de la variable dans le navigateur après chargement de la page | Vérifier si le bundle JS s'exécute vraiment (pas juste "se charge" — un `<script>` peut recevoir un 200 réseau tout en exécutant un contenu mis en cache par le navigateur). Résultat : la variable restait `undefined` alors qu'un `fetch(..., {cache:'no-store'})` dans le **même onglet** montrait le bon contenu — preuve formelle que le navigateur exécutait une version pré-corrections de `app.js` mise en cache, malgré des rechargements "durs" et des onglets neufs. |
| Retrait des marqueurs de debug (`assets/app.js` revenu propre) | Nettoyage, ce n'était qu'un outil de diagnostic. |
| Edit `webpack.config.js` : `.enableVersioning(Encore.isProduction())` → `.enableVersioning()` (actif aussi en dev) | **Vraie correction** : sans ça, chaque rebuild local écrase `public/build/app.js` en gardant le **même nom de fichier**, donc le navigateur peut légitimement continuer à servir sa version en cache indéfiniment (le serveur local Symfony CLI n'envoie pas d'en-tête `Cache-Control` explicite, donc le navigateur applique sa propre heuristique de cache, qui s'est avérée trop agressive ici). Avec le hachage activé, chaque build produit un nom de fichier différent (`app.57c19e46.js`) : il ne peut plus jamais y avoir de conflit de cache. C'est déjà le comportement de la production (`Encore.isProduction()` était déjà vrai là-bas) — **ce problème n'a jamais affecté le vrai site en ligne**, uniquement le développement local répété dans la même session de navigateur. |
| `npx encore dev` puis nouvel onglet + test de l'autocomplétion | Confirmation finale : l'autocomplétion fonctionne, avec le nouveau design (mode à gauche). |
| `mysql ... DELETE FROM utilisateur WHERE username IN ('claude_test_scan','claude_debug2')` | Suppression des 2 comptes de test créés pendant cette session de débogage. |

**À retenir pour la suite** : après chaque `npx encore dev` en local, un simple rechargement de page suffit
maintenant (plus besoin de vider le cache manuellement) grâce au hash de fichier. Si un changement
front (JS/CSS) ne semble "pas pris en compte" malgru un rebuild réussi, vérifier d'abord
`public/build/entrypoints.json` (le nom de fichier a bien changé ?) avant de suspecter autre chose.

## Session du 2026-08-11 (suite 3) — Vérification du filtre par mode dans l'autocomplétion

Demande : vérifier qu'une station multimodale (Châtelet, Gare de Lyon) montre bien l'autocomplétion,
et surtout qu'elle continue à fonctionner quand on décoche des cases "Modes de transport", y compris
avec une seule case restant cochée (ex: Métro seul sur "Nation").

| Commande | Objectif |
|---|---|
| Création d'un compte de test (`claude_debug3`), test dans un onglet neuf : "nation" avec Métro+Tram+RER cochés (Bus décochés), puis Métro seul cochés | Reproduire le scénario décrit. Résultat : **ça fonctionne** dans les deux cas — l'autocomplétion filtre correctement et affiche même un seul mode restant (ex: "Nation", "Nationale", "Assemblée Nationale" en MÉTRO uniquement, sans ligne "Tous" superflue puisqu'un seul mode dessert ces stations une fois le filtre appliqué). |
| Re-test dans l'onglet précédent (`tab-8`, réutilisé après une inscription de compte) | Là, l'autocomplétion ne répondait plus du tout (aucune requête réseau visible). Contrairement à un onglet neuf, ce même test y échouait — donc pas un bug de logique, mais un état de page corrompu après une navigation. Cause probable : Turbo (`@symfony/ux-turbo`, actif depuis la correction de `assets/stimulus_bootstrap.js`) intercepte la redirection après soumission du formulaire d'inscription et remplace le contenu de la page sans redéclencher `DOMContentLoaded` — donc le code d'init de `app.js` (qui s'abonne uniquement à cet événement) ne se relance jamais sur une page arrivée via une navigation Turbo. **Piste à surveiller** : si un jour l'inscription/connexion/edition de formulaire semble "figée" sans erreur visible juste après une redirection, c'est probablement ça — noté dans `report.md`. |
| `mysql ... DELETE FROM utilisateur WHERE username='claude_debug3'` | Nettoyage du compte de test. |

**Conclusion** : le filtre par mode fonctionne correctement dans le code actuel (vérifié avec 1, 3 et 5
cases cochées). Le "ça ne marche plus" du message précédent était bien la conséquence du souci de
cache déjà réglé (voir suite 2) — pas un bug de logique distinct.

## Session du 2026-08-11 (suite 4) — Préparation du push : la copie Desktop avait dérivé du vrai dépôt

Demande : pousser les changements sur Git. `git status` échoue toujours dans ce dossier (`.git`
incomplet, cf sessions précédentes). Pour pousser proprement, il fallait d'abord retrouver le vrai
dépôt.

| Commande | Objectif |
|---|---|
| `gh repo list` | Trouver le nom du dépôt GitHub associé à ce projet (`jujusilb/metroratp`, public) — l'URL n'était stockée nulle part de récupérable dans ce dossier (`.git/config` manquant). |
| `gh repo clone jujusilb/metroratp` (dans le scratchpad) | Cloner une copie propre et à jour de `main` pour comparer avec la copie Desktop, et disposer d'un vrai dépôt Git fonctionnel pour committer/pousser. |
| `diff` fichier par fichier entre le clone propre et la copie Desktop (`assets/js/trajet-autocomplete.js`, `.test.js`, `assets/styles/app.scss`, `templates/base.html.twig`, `webpack.config.js`, `assets/stimulus_bootstrap.js`, `assets/app.js`, `package.json`, `composer.json`) | Découverte majeure : **la copie Desktop avait dérivé du vrai dépôt sur plusieurs fichiers**, pas seulement ceux que j'avais édités intentionnellement. En particulier `webpack.config.js` (version ESM avec `.enableStimulusBridge()` et `import`/`export` au lieu du vrai `require`/`module.exports` tout simple), `assets/app.js` (import supplémentaire de `stimulus_bootstrap.js` qui n'existe pas dans le vrai fichier), `assets/stimulus_bootstrap.js`, et `package.json` (versions differentes de `@symfony/webpack-encore`/`@babel/core`, `sass`/`sass-loader`/`jest` manquants alors qu'ils sont dans le vrai `package.json`). **Conséquence** : une bonne partie du diagnostic "500 partout" des sessions précédentes (duplication `encore_entry_script_tags`+`importmap`, Turbo qui casse la navigation, `@symfony/stimulus-bridge` à réinstaller...) réglait des problèmes qui n'existaient que dans cette copie locale dérivée, pas dans le vrai code sur GitHub. `composer.json` et `base.html.twig`, eux, correspondaient déjà exactement au vrai dépôt (remis en état correctement lors des sessions précédentes). |
| `cp` (depuis le clone propre) sur `assets/app.js`, `assets/stimulus_bootstrap.js`, `webpack.config.js`, `package.json` | Remettre ces 4 fichiers strictement identiques au vrai dépôt (annule les "corrections" des sessions précédentes qui n'avaient de sens que pour la config fabriquée localement). |
| `cp` du `jest.config.js` du clone propre (absent du dossier Desktop) | Encore un fichier manquant sur cette copie locale — nécessaire pour lancer les tests JS. |
| `npm install` (après correction de `package.json`) | Resynchroniser `node_modules` avec les vraies dépendances (retire `@symfony/stimulus-bridge`/`@symfony/ux-turbo` installés par erreur en session précédente). |
| `npm test` | Confirme que les 3 suites de tests JS passent (29/29) avec le vrai `jest.config.js` et le nouveau code de `trajet-autocomplete.js`/`.test.js`. |
| `npx encore dev` (avec le vrai `webpack.config.js`) | Confirme que le vrai pipeline de build (celui utilisé en CI/prod) compile sans erreur avec mes changements. |
| Tentative de vérification visuelle finale (inscription d'un compte test) | Bloquée par une erreur "CSRF token is invalid... double-submit info was used in a previous request but is now missing" reproductible sur plusieurs tentatives/onglets neufs. Probablement un souci de cookies propre à ce navigateur automatisé de test (CSRF "stateless" à double-soumission), pas un bug du site — non résolu par manque de temps, mais sans lien avec les fichiers JS/CSS modifiés (le test unitaire Jest + le test navigateur réussi plus tôt dans la session couvrent déjà le comportement de l'autocomplétion). |
| Correction de `documentation/report.md` | Retrait de 2 "bugs" qui n'en étaient pas (doublon `encore_entry_script_tags`, Turbo cassant la navigation) — artefacts de la dérive locale, jamais présents dans le vrai code. Le bug "mode de transport pas indiqué dans l'autocomplétion" est marqué corrigé. |

**Changements réellement à committer/pousser** (tout le reste a été remis identique au dépôt) :
- `assets/js/trajet-autocomplete.js` — nouveau design (mode à gauche, station à droite)
- `assets/js/trajet-autocomplete.test.js` — tests mis à jour en conséquence
- `assets/styles/app.scss` — classes CSS `.suggestion-station`/`.suggestion-mode`
- `documentation/report.md`, `documentation/commande.md` — nouveaux fichiers de suivi (à confirmer avec l'utilisateur si à committer)

## Session du 2026-08-11 (suite 5) — Pagination + filtres sur Ligne/Desserte/Troncon

Demande : ces 3 pages d'index chargent des milliers d'enregistrements sans pagination (déjà noté
dans `report.md`). Ajout de cases "Modes de transport" + recherche par station + pagination
(`KnpPaginatorBundle`, déjà en dépendance mais jamais branché).

| Commande | Objectif |
|---|---|
| Lecture de `LigneRepository`/`TronconRepository`/`DesserteRepository`/`Troncon.php` (getSensCirculation) | Comprendre les relations existantes avant d'écrire les requêtes filtrées (notamment que `Troncon` n'a pas de lien direct vers `Ligne`/`Station`, seulement via `tronconDesserte -> desserte`). |
| Création de `config/packages/knp_paginator.yaml` (template `bootstrap_v5_pagination`) | KnpPaginatorBundle était installé mais sans configuration — cohérent avec le style Bootstrap 5 déjà utilisé partout ailleurs. |
| Inscription/connexion via `form_input` + clic réel (`computer.left_click`) répétée plusieurs fois, toutes en échec ("CSRF token invalid" / "double-submit info... missing") | Tentative de créer un compte de test dans le navigateur automatisé pour vérifier les pages en conditions réelles. Échec systématique — investigué en profondeur (voir ligne suivante). |
| Lecture de `vendor/symfony/security-csrf/SameOriginCsrfTokenManager.php` + `curl -I`/fetch du vrai `build/app.js` de production (`https://julien-silberstein.fr/metroratp/...`) | Diagnostic complet de l'échec CSRF récurrent dans le navigateur de test : le champ `_token` est bien rempli par un JS (`csrf_protection_controller.js`, fourni par `symfony/stimulus-bundle`) qui n'est **jamais chargé** dans le bundle Encore actuel (ni en local ni en prod, vérifié par grep sur le vrai `app.js` téléchargé) — mais ce n'est **pas un bug réel** : Symfony valide aussi via les en-têtes `Sec-Fetch-Site`/`Origin`/`Referer`, que tout vrai navigateur envoie automatiquement sur une soumission same-origin. Confirmé en pratique avec `curl -H "Origin: ..." -H "Referer: ..."` : la connexion réussit même avec le token factice `"csrf-token"`. Le navigateur de test utilisé dans cette session semble ne pas envoyer ces en-têtes de la même façon, d'où l'échec systématique **uniquement dans cet outil**, sans impact sur les vrais utilisateurs. Pas d'action corrective nécessaire côté code. |
| Création d'un utilisateur de test directement en base (`php bin/console security:hash-password` + `INSERT INTO utilisateur`), puis connexion via `curl` avec `-H "Origin:..."` et un cookie jar | Contournement fiable du souci ci-dessus pour pouvoir tester les nouvelles pages authentifiées sans dépendre du navigateur automatisé. |
| `curl` répétés sur `/ligne`, `/desserte`, `/troncon` avec différentes combinaisons de `modes[]`/`q`/`page` | Vérification bout en bout : total correct sans filtre (1434 lignes, 31449 dessertes, 7241 tronçons), filtre par mode (ex: Métro seul → 16 lignes), recherche par station (ex: "Nation"), pagination (liens `page=N` qui conservent bien les filtres), cas "aucune case cochée" → 0 résultat (voulu). A débusqué un vrai bug pré-existant (`Desserte::getPremiereOuverture()` plantait sur les dessertes sans période d'ouverture, cf `report.md`), corrigé. |
| `DELETE FROM utilisateur WHERE username IN (...)` | Nettoyage des comptes de test créés pendant cette session. |

## Session du 2026-08-11 (suite 6) — Filtres supplémentaires : ligne/gestionnaire, gestionnaire multi-select, "tronçons construits"

Demande : la recherche sur Ligne doit porter sur le numéro/nom de ligne (pas les stations) ; sur
Desserte/Troncon la recherche doit couvrir station OU ligne OU gestionnaire ; ajout d'un filtre
multi-select par gestionnaire (~56 valeurs) sur les 3 pages, et d'un filtre "Tronçons construits"
(Oui/Non/Tous) sur Ligne.

| Commande | Objectif |
|---|---|
| `INSERT INTO utilisateur ...` (réutilisation du hash déjà généré en session précédente) puis suppression en fin de session | Recréer/nettoyer le compte de test direct-en-base pour les vérifications `curl` authentifiées (voir suite 5 pour le pourquoi de cette méthode plutôt que le navigateur). |
| `curl` sur `/ligne?q=2`, `?q=Keolis`, `/desserte?q=2`, `?q=Keolis`, `/troncon?q=2`, `?q=Keolis` | Vérifier la recherche élargie (ligne OU gestionnaire, plus station pour Desserte/Troncon) : 504 lignes matchent "2", 331 "Keolis" ; 10976/6783 dessertes ; 358/2910 tronçons — pas d'erreur. |
| `curl` sur `/ligne?gestionnaires[]=1`, `?avecTroncons=1`, `?avecTroncons=0`, `/desserte?gestionnaires[]=1&gestionnaires[]=21`, `/troncon?gestionnaires[]=1` | Vérifier le multi-select gestionnaire et le filtre "tronçons construits" : 244 lignes RATP, 250 lignes avec tronçons + 1184 sans = 1434 (total exact, cohérent), etc. |

## Session du 2026-08-11 (suite 7) — Correspondances bus<->bus/metro/rer/tram via transfers.txt

Demande : le dossier `documentation/IDFM-gtfs/` contient aussi `transfers.txt`/`pathways.txt`
(GTFS standard) et un PDF référentiel IDFM — est-ce exploitable pour les correspondances impliquant
le bus (jusqu'ici seules Métro/Tram/RER entre eux étaient couvertes, `ConstruireCorrespondancesInterModesCommand`,
volontairement, pour éviter l'explosion combinatoire d'une approche "toutes les paires à un même
arrêt" sur ~1400 lignes de bus).

| Commande | Objectif |
|---|---|
| `pdftotext documentation/IDFM-gtfs/2023_idfm_referentiels.pdf -` | Lire le référentiel IDFM (poppler/`pdftotext` disponible via `/mingw64/bin`, pas besoin de `pdftoppm`). Confirme le concept de "Zone de correspondance" (= notre `Station`) : les correspondances **à l'intérieur** d'une même zone sont déjà implicites, celles **entre deux zones différentes** ne le sont pas — exactement ce que `transfers.txt` documente et que notre modèle ne capturait pas pour le bus. |
| Script d'analyse ad hoc (`analyser_transfers.php`/`analyser_transfers2.php`, scratchpad) | Quantifier avant de se lancer : 179 917 lignes dans `transfers.txt`, 87 454 "intra-ZdC" (déjà implicite chez nous), **92 463 "inter-ZdC" (12 253 paires de Stations) — nouvelle info**, dont 99,9% impliquent au moins un arrêt de bus. Durées : médiane ~7 min, quelques valeurs aberrantes jusqu'à 92 min (filtrées à 30 min max). |
| Script d'estimation (`estimer_correspondances_bus.php`, scratchpad, connexion MySQL directe) | Avant d'implémenter "toutes les paires de dessertes entre les 2 Stations" (même principe que la commande existante) : estimer le volume réel plutôt que de deviner. Résultat : ~106 757 lignes `Correspondance` au total — beaucoup, mais du même ordre de grandeur que ce qui existe déjà (31449 dessertes, 7241 tronçons), donc raisonnable. |
| `documentation/scripts/extraire_correspondances_inter_zdc.php` (nouveau) | Extraction définitive : agrège `transfers.txt` par paire de ZdC (médiane de durée si plusieurs arrêts-transporteurs résolvent vers la même paire), filtre >30 min. → `correspondances_inter_zdc.csv` (12 247 paires). |
| `php bin/console app:construire-correspondances-bus` (nouvelle commande) | Lit le CSV, crée une `Correspondance` pour chaque paire de dessertes entre les deux Stations (toutes combinaisons, comme la commande metro/tram/RER), en pré-chargeant les paires déjà existantes pour rester rejouable sans doublon. Temps GTFS (secondes) converti en distance (mètres, ×0,9 m/s) pour rester cohérent avec `Correspondance::getTempsEstimeMinutes()` qui dérive le temps affiché à partir de la distance. Flush par lots de 2000 (~107k écritures). Résultat : **106 757 créées**. |
| Vérifications SQL (répartition par paire de modes, échantillons bus↔RER/métro) | 102 749 bus↔bus, 227 RER↔bus, 119 métro↔bus, etc. — cohérent avec l'attendu (le bus domine largement le volume d'arrêts). |
| Test direct de `TrajetFinder` (script `php -r`, comme pour les tronçons bus) | Trajet RER A (Châtelet-Les Halles) → bus 21 (Pont Neuf) → 15 arrêts jusqu'à Porte de Saint-Ouen : 17 étapes, fonctionne de bout en bout en forçant `modes=['rer','bus_ratp']` (donc en passant obligatoirement par la nouvelle correspondance). |
| `documentation/scripts/extraire_temps_marche_intra_zdc.php` + `php bin/console app:affiner-distances-correspondances` (nouvelles) | Deuxième volet demandé : remplacer l'estimation par défaut (distance NULL → 3 min fixe) des correspondances metro/tram/RER **existantes** par un vrai temps de marche GTFS, quand disponible, sans jamais écraser une distance déjà saisie manuellement. 32 candidates (distance NULL), 9 affinées (les autres ZdC sans temps de marche connu dans `transfers.txt`). |

## Session du 2026-08-11/12 (suite 8) — API plan-quartier RATP, Accès/Sorties, distances bus, carte Leaflet

Demande : enquêter sur une éventuelle API derrière `ratp.fr/plan-quartier`, puis remplir Accès/Sortie
depuis l'open data IDFM, puis calculer les distances des tronçons de bus (durée déjà présente mais
distance à 0%), puis « compléter la carte avec tous les modes pour toute l'Île-de-France ».

| Commande | Objectif |
|---|---|
| Navigation + `read_network_requests` sur `ratp.fr/plan-quartier` (Browser tool) | Trouver l'API derrière la recherche de lieu : `POST /dpam-place-search`, JSON avec lieu/coordonnées/lignes desservantes. Conclusion : endpoint interne non documenté (module Drupal `ixxi_carto`), protégé Cloudflare, pas une base fiable pour une intégration — recommandation de rester sur Leaflet+OSM déjà proposé. |
| Exploration `documentation/IDFM-gtfs/stops.txt` (`grep`, `awk`) | Découverte que les points d'accès GTFS (`location_type=2`, préfixe `StopPlaceEntrance`) donnent déjà nom/numéro/ZdC parent, mais `wheelchair_boarding` toujours à 0 (inconnu) — pas de PMR exploitable dans le GTFS. |
| `WebFetch`/`WebSearch` sur `data.iledefrance-mobilites.fr` (dataset `acces`, `relations-acces`, `accessibilite-en-gare`) | Confirmer via le schéma officiel du dataset "acces" (2522 lignes) qu'aucun champ PMR n'existe à ce grain ; `accessibilite-en-gare` existe mais par gare entière (459 lignes), pas par accès. |
| L'utilisateur télécharge `acces.csv`, `accessibilite-en-gare.csv`, `zones-d-arrets.csv`, `schema_gares-gf.csv`, etc. depuis le portail open data et les place dans `documentation/IDFM-gtfs/` | Fournir les mêmes données en local plutôt que de les télécharger moi-même (règle : téléchargement = permission explicite). Bonus : `schema_gares-gf.csv` répond à un manque documenté dans `TODO.md`. |
| `src/Command/ConstruireAccesSortiesCommand.php` (nouvelle) + `documentation/scripts/extraire_acces_entrees.php` (nouveau) | Reconstruit Acces/Sortie (purge puis import) en fusionnant `acces.csv` (libellé/numéro officiels) et `stops.txt` (rattachement ZdC fiable). `isAccessible` reste `NULL` (pas de donnée). Résultat : 2513 Accès/Sortie créés (760 stations), remplaçant 1068 lignes manuelles partielles. |
| `src/Command/CalculerDistancesTronconsBusCommand.php` (nouvelle) | Distance à vol d'oiseau (coordonnées ZdC) pour les 6454 tronçons de bus qui n'avaient que la durée. Vérifié : vitesse moyenne implicite 15,3 km/h, réaliste pour un bus parisien. |
| `dbal:run-sql` divers (comptages troncon/type_transport) | Découvrir que la distance manquait aussi pour RER/Tramway (pas seulement bus) — signalé à l'utilisateur, non traité (approximation vol d'oiseau jugée moins fiable sur ces distances plus longues). |
| Ajout `Station::latitude/longitude` + migration `Version20260811232808` + `src/Command/ImporterCoordonneesGeographiquesCommand.php` (nouvelle) | Coordonnées géographiques réelles (vs `schemaX/Y`, plan déformé métro seulement) : 13696 Stations par `codeExterne`, puis repli par nom exact pour les ~534 Stations "originales" sans `codeExterne` (doublons documentés dans `TODO.md`). |
| Détection d'un faux rapprochement ("Saint-Paul" du Marais lié par erreur aux coordonnées d'un arrêt de bus rural homonyme, seul candidat ZdC-lié pour ce libellé) | Tentative de filtrer par mode (Métro/RER/Tramway) du candidat : abandonnée, car même les bonnes jumelles (ex: "Nation") n'ont qu'une Desserte de bus en base. Solution retenue : liste d'exclusion manuelle (`EXCLUSIONS_CONNUES`), même principe que les dictionnaires de correspondance déjà utilisés dans le projet. |
| Refactor `TrajetController` (clés `x1/y1` → `lat1/lon1`) + nouvelle méthode `TronconRepository::tronconsPourCarte()` (SQL brut) | La carte utilise désormais les coordonnées géographiques réelles pour tous les modes. Découverte au passage : `findAllWithDetails()` (utilisé aussi bien pour la carte que par `TrajetFinder::construireGraphe()`) hydrate ~193 000 entités ORM à chaque requête — 12-14s par calcul de trajet. Remplacé pour la carte par une requête SQL dédiée (quasi instantané) ; `TrajetFinder` lui-même reste à optimiser (signalé, pas traité). |
| `npm install leaflet` + réécriture `assets/js/trajet-carte.js` (Leaflet/OSM au lieu du SVG schématique fait main) | Carte réelle avec tuiles OpenStreetMap, tronçons en `L.polyline` (rendu Canvas pour la performance), marqueurs numérotés (`L.divIcon`) pour les stations du trajet. |
| Retour utilisateur : « la carte prend trop de place » | Déplacement de la carte dans une modale Bootstrap plein écran, ouverte via un 3ᵉ bouton "Carte" à côté de "Simple"/"Détaillé" ; Leaflet initialisé au premier `shown.bs.modal` (pas avant, sinon taille ratatinée) avec `invalidateSize()` aux ouvertures suivantes. |
| `.claude/launch.json` : ajout d'une config `symfony` (`symfony server:start --port=8000`) | Config manquante pour prévisualiser l'app via le Browser tool (seule `dev`/webpack existait) — fichier non suivi par git, local à cette session. |
| Création/suppression de comptes de test (`test_carte` en local, `test_verif_carte` en prod, via `security:hash-password` + `INSERT`/`DELETE` direct) | Même méthode que les sessions précédentes pour contourner le souci CSRF spécifique à l'outil de navigation automatisée (voir sessions antérieures) — confirmé de nouveau non reproductible pour un vrai navigateur. Piège rencontré : passer un hash bcrypt (contient des `$`) à travers une commande SSH imbriquée fait ré-interpréter `$2y$13$...` par le shell distant (position parameters) et corrompt le hash — contourné en passant le SQL encodé en base64. |
| `npm ci` échoue en CI (`Missing: @emnapi/core@1.11.3 from lock file`) après `npm install leaflet` | `package-lock.json` généré en incrémental sous Windows incomplet pour certaines dépendances optionnelles. Corrigé par une régénération complète (`rm -rf node_modules package-lock.json && npm install`), vérifiée avec un `npm ci` propre avant de repousser. |
| `git commit`/`push` (2 commits), `gh run watch`, `gh run rerun --failed` (échec transitoire "Préparer la clé SSH", comme lors de sessions précédentes) | Déploiement CI/CD complet, réussi au second essai. |
| `ssh ... php bin/console app:importer-coordonnees-geographiques/app:calculer-distances-troncons-bus/app:construire-acces-sorties --env=prod` | Exécution des 3 commandes de données contre la base de production (le code seul ne suffit pas, ces commandes ne tournent pas automatiquement au déploiement). Résultats cohérents avec le local. |
| `curl` authentifié (cookie jar) sur `/trajet?origine=18&destination=21` en prod | Vérification finale : `data-carte` contient bien des coordonnées géographiques réelles (`lat1`/`lon1`) sur le site en ligne. |

## Session du 2026-08-13 (suite 9) — Conseils de position dans la rame

Demande : exploiter `documentation/IDFM-gtfs/positionnement-dans-la-rame.csv` (repéré en discutant
du contenu du dossier GTFS avec l'utilisateur).

| Commande | Objectif |
|---|---|
| `head`/`awk`/`shuf` sur `positionnement-dans-la-rame.csv` | Comprendre le schéma : pour une Ligne et un arrêt de départ (`stop_point` GTFS), où se placer dans la rame (`position_average`/`position`/`position_max`) pour arriver au plus près d'une sortie (`to_type=access_point`, même identifiant que `acces.csv`) ou d'une correspondance (`to_type=stop_point`). |
| `grep` croisé sur `stops.txt` | Confirmer que les `stop_point` de ce fichier sont directement des `stop_id` GTFS (`location_type=0`), dont le `parent_station` donne la ZdC en un seul saut — pas besoin d'`arrets-transporteur.csv`. |
| Ajout `Acces::codeExterne` (migration + colonne) | Nécessaire pour relier les `to_type=access_point` du nouveau fichier à nos `Acces` existants (même `AccId`). |
| `src/Entity/PositionRame.php` (nouvelle entité), migration, `make:crud`-like (Controller/Form/templates écrits à la main, `make:crud` non-interactif indisponible dans cet environnement) | CRUD admin standard (`/position-rame`), cohérent avec le reste de l'app. |
| `documentation/scripts/extraire_conseils_position.php` (nouveau) | Extrait `positionnement-dans-la-rame.csv` + résolution ZdC via `stops.txt` → `conseils_position.csv` (4675 lignes). |
| Tentative de rattachement de la Ligne par `codeExterne` (comme pour le bus) | **Échec, bug découvert** : le `codeExterne` de nos Ligne de métro est incohérent avec le GTFS actuel (ex: notre ligne "7" pointe vers `C00312`, qui correspond dans le GTFS courant à une ligne de BUS renommée "6402 (ex 7)", pas à la ligne 7 du métro). Confirmé sur `routes.txt`. |
| Correctif : rattachement par **label** (`UPPER(label)`, en préférant la Ligne sans `codeExterne` = l'"originale") | Le jeu de données ne couvre que 18 lignes (métro 1-14+3B+7B, RER A/B) — pas de risque de collision de label dans ce périmètre (contrairement au bus). `app:construire-positions-rame` (nouvelle commande) : 4671/4675 créées. |
| Vérification sur `/station/15` (Châtelet) | **Deuxième bug découvert** : les Sorties ET les nouveaux conseils de position atterrissaient sur la Station ZdC-liée (id 20175, jamais consultée), pas sur la Station "originale" (id 15, celle réellement affichée) — même problème de doublon de Station que pour la carte, mais impactant cette fois l'affichage lui-même, pas juste une donnée dérivée. |
| Correctif : `StationRepository::trouverIdCanoniqueParZdc()` (nouvelle méthode réutilisable) | Résout chaque `codeExterne` vers l'id de Station "originale" homonyme quand elle existe (0 cas ambigu vérifié), sinon vers la Station ZdC-liée elle-même. Réutilisé par `app:construire-acces-sorties` ET `app:construire-positions-rame`. Après correctif : Châtelet (id 15) affiche bien ses 2948 sorties et 2948 conseils de position. |
| Ajustement de l'ordre de purge (`position_rame` avant `acces`, contrainte FK) | `app:construire-acces-sorties` doit désormais purger `position_rame` avant `acces`/`sortie` (sinon violation de contrainte FK) — documenté : `app:construire-positions-rame` doit être rejouée juste après. |
| `php bin/phpunit` (134 tests), vérification navigateur sur `/station/15` | Tout passe, section "Conseils de position" bien affichée avec les bonnes données. |

## Session du 2026-08-13 (suite 10) — Tracé réel des lignes sur la carte + crash mémoire de TrajetFinder

Demande : exploiter `traces-des-lignes-de-transport-en-commun-idfm.csv` /
`traces-des-lignes-regulieres-de-bus-en-ile-de-france.csv` (utilisateur : "je pense que ca peut
etre necessaire ou utile"), après une nouvelle consigne explicite : considérer le degré de
granularité comme infini pour toute donnée de transport IDF (mémorisé).

| Commande | Objectif |
|---|---|
| `head`/`awk` sur les deux fichiers de tracés | `traces-des-lignes-de-transport-en-commun-idfm.csv` (1942 lignes) couvre TOUS les modes (1882 bus, 24 RER, 16 métro, 17 tram) avec un identifiant `IDFM:C0xxxx` cohérent avec notre `codeExterne` — choisi comme source unique, rendant le fichier bus-only redondant. |
| `documentation/scripts/extraire_traces_lignes.php` (nouveau, avec algorithme de Douglas-Peucker écrit à la main) | 76 Mo brut (3,3M points, ~1700/ligne en moyenne) → simplifié (tolérance 3m) + arrondi (5 décimales) → 22,6 Mo (1,26M points, ~650/ligne), forme visuelle inchangée. |
| Ajout `Ligne::trace` (JSON) + migration + `app:importer-traces-lignes` (rattachement par label pour le métro, même contournement que les conseils de position) | 1445/1936 lignes rattachées. |
| `assets/js/trajet-carte.js` : `projeterSurSegment`/`projeterSurLigne`/`extraireSousChemin`/`extraireTraceEntreDeuxPoints` (nouvelles fonctions pures, testées) | Projette les 2 stations d'un tronçon du trajet sur le tracé réel de la Ligne empruntée (en choisissant la bonne branche parmi plusieurs), découpe la portion entre les deux — la carte suit maintenant les rues/rails au lieu d'un trait direct entre stations. |
| Test navigateur sur un vrai trajet (Bastille → Nation, ligne 1) | **Erreur 500 "Allowed memory size exhausted"** au lieu d'afficher la carte. |
| Investigation : `TrajetFinder::construireGraphe()` | Root cause identifiée : cette méthode (documentée comme lente ~12s dans `TODO.md` depuis la veille, jamais corrigée) charge via l'ORM l'intégralité du réseau (tous les Troncon + toutes les Correspondance, ~193 000 entités) à **chaque** calcul de trajet — en ajoutant `Ligne::trace` (potentiellement volumineux) à ce chargement complet, "lent" est devenu "plante". |
| Réécriture complète de `TrajetFinder::construireGraphe()` en SQL brut (ids + poids seulement) + `construireEtapes()` qui ne recharge via l'ORM que les quelques dizaines de `Desserte` du chemin **trouvé** | Même motif "requête légère + recharge par ids" que pour la carte. `Etape::troncon`/`correspondance` (jamais lus nulle part dans le code, vérifié par recherche) ne sont plus peuplés — simplification supplémentaire sans risque. |
| `php bin/console --env=test doctrine:schema:update --force`, `php bin/phpunit` (134 tests) | Tout passe après réécriture — comportement identique, juste beaucoup plus rapide. |
| Vérification navigateur (profiler Symfony) | Avant : ~12-14s, ~193 000 entités gérées, plantage mémoire avec `Ligne::trace`. Après : **2,5s, 30 entités, 58 Mo de pic mémoire** (limite 512 Mo). Confirmé aussi que le tracé réel se calcule correctement (Bastille→Gare de Lyon : 13 points de la vraie courbe de la ligne 1, pas juste 2). |
| `documentation/TODO.md` | Sections mises à jour : tracé réel documenté, section "Performance de TrajetFinder" passée de "pas corrigé" à "corrigé" avec le détail de la cause du crash. |

## Session du 2026-08-13/14 (suite 11) — Coordonnées du plan schématique, tous modes

Demande : exploiter `schema_gares-gf.csv` (repéré comme "bonus find" lors d'une session
précédente — répondait à un manque documenté dans `TODO.md`, jamais traité jusqu'ici).

| Commande | Objectif |
|---|---|
| Inspection du fichier (`php -r` avec `fgetcsv`) | Format différent de ce qu'attendait `app:importer-coordonnees-schema` (entêtes `NOM_GARE`/`MODE_`/`X`/`Y` en majuscules, pas `nom_gare`/`mode`/`x`/`y`) et couvre TOUS les modes ferrés (391 métro, 259 RER, 287 train/Transilien, 255+14 tram, 10 navette), pas seulement le métro comme la version existante de la commande le filtrait. |
| `cp` du fichier vers `documentation/scripts/donnees-extraites/` (275 Ko, assez petit pour être commité tel quel) | `documentation/IDFM-gtfs/` est gitignore ; rendre le fichier disponible en prod sans script d'extraction (inutile ici, taille déjà raisonnable). |
| Extension de `ImporterCoordonneesSchemaCommand` (colonnes + tous modes) puis `--dry-run` | Premier essai alarmant : **4863 stations "positionnées"** sur ~14000 en base — bien trop pour les ~1000 lieux réels de la source, signe de faux positifs (le rapprochement par nom, avec repli par inclusion de mots, matchait aussi des arrêts de bus au nom proche d'une gare, alors que ce dataset ne couvre jamais le bus). |
| Ajout d'un garde-fou : restreindre les Stations candidates à celles desservies par un mode ferré lourd (Métro/RER/Tramway/Train, via EXISTS sur Desserte→Ligne→TypeTransport) | Après correctif : 1071 candidates, **1037 positionnées (97%)**, 34 non trouvées. Vérifié : aucune station bus-seule positionnée, uniquement des hubs multimodaux légitimes (ex: La Défense, Gare de Lyon, servis par RER + bus). |
| `php bin/console app:importer-coordonnees-schema documentation/scripts/donnees-extraites/schema_gares-gf.csv` (exécution réelle) | 1037 Stations mises à jour (contre ~300 avant, métro seulement). |
| `php bin/phpunit` (134 tests) | Tout passe. |
| Note pour l'utilisateur : `Station.schemaX/Y` n'est plus utilisé par aucune fonctionnalité visible depuis que la carte du trajet utilise les vraies coordonnées géographiques (`latitude`/`longitude`) — cet import complète la donnée pour un usage futur (ex: bascule carte réelle / plan schématique), sans changement visible immédiat sur le site. |

## Session du 2026-08-14 (suite 12) — Carte complète du réseau, bulles au survol, lignes cliquables

Demande : lignes du calculateur de trajet cliquables vers leur page ; une carte complète du réseau
(tous arrêts, bulle au survol "Mode:Ligne:Arrêt") ; et la carte du trajet réduite aux seuls arrêts
concernés (au lieu du réseau complet en fond).

| Commande | Objectif |
|---|---|
| Ajout `id`/`ligneId` dans `TrajetController::construireResumeSimple()` + templates | Lignes cliquables vers `/ligne/{id}` dans les vues Simple et Détaillée. |
| `StationRepository::donneesPourCarteComplete()` (SQL brut, ~31500 lignes desserte agrégées) + `CarteController` + `/carte` | Nouvelle page listant toutes les Stations (tous modes), avec pour chacune la liste (mode, ligne, gestionnaire) — testé : 705 ms, 68 Mo de pic mémoire pour 14057 stations. |
| `assets/js/carte-tooltip.js` (nouveau, testé) | Formate "Mode:Ligne:Arrêt" ou "Mode:Gestionnaire:Ligne:Arrêt" (gestionnaire affiché seulement si ≠ RATP) — partagé entre la carte complète et la carte du trajet. |
| `assets/js/carte-reseau.js` (nouveau) | Dessine tous les arrêts (canvas Leaflet, `circleMarker` + `bindTooltip`), couleur selon le mode le plus "lourd" desservi. |
| Suppression de `construireReseauPourAffichage`/`marquerTronconsConcernes`, ajout `construireInfosStationsPourAffichage` | La carte du trajet ne montre plus que les arrêts/tronçons du trajet trouvé (plus de fond de réseau complet), avec les mêmes bulles au survol. |
| **Long diagnostic (carte introuvable dans le navigateur malgré un code correct)** | `canvasCount` toujours à 0 malgré de nombreux rebuilds. Écarté un par un : erreur JS (aucune, `window.onerror` propre), race condition DOMContentLoaded (corrigée par précaution mais pas la cause), isolation de contexte JS de l'outil de navigateur (non pertinent, confirmé via marqueurs DOM). Cause réelle trouvée via `fetch(url)` sans `no-store` : **le serveur PHP intégré ne renvoie aucun header Cache-Control/ETag**, donc le navigateur appliquait un cache heuristique et servait un `app.js` vieux d'un jour malgré des dizaines de rebuilds. |
| Correctif : `webpack.config.js`, `.enableVersioning(true)` au lieu de `.enableVersioning(Encore.isProduction())` | Force un hash dans le nom de fichier (`app.78d75e72.js`) à chaque build, donc une URL différente = jamais de cache périmé en dev local. Vérifié : carte fonctionne immédiatement après ce correctif (canvas, tuiles, marqueurs, bulles au survol tous présents). |
| `php bin/phpunit` (134 tests), `npx jest` (36 tests), vérification production (carte + trajet, comptes de test temporaires) | Tout passe, déployé et vérifié en ligne. |

## Session du 2026-08-14 (suite 13) — Plans de secteur (entité `Plan`, `Station::plan`)

Téléchargement des 73 PDF de `plans-de-secteur.csv` (72 réussis, plan 50 indisponible côté IDFM),
puis nouvelle entité `Plan` + FK `Station::plan`, CRUD complet, commande d'import avec assignation
automatique quand le département de la Station ne correspond qu'à un seul Plan.

| Commande | Objectif |
|---|---|
| `curl -sL --max-time 60 -o plan_${numero}.pdf "$url"` (boucle bash, tâche en arrière-plan) | Téléchargement des 73 PDF officiels IDFM dans `documentation/IDFM-gtfs/plan secteur/` (337 Mo, non commité). 72/73 réussis, plan 50 "En cours de réalisation" indisponible côté IDFM. |
| `php bin/console doctrine:migrations:diff` puis `doctrine:migrations:sync-metadata-storage` | Échec persistant "metadata storage not up to date" — root cause : 3 migrations déjà appliquées localement via `doctrine:schema:update --force` lors de sessions précédentes mais jamais enregistrées dans `doctrine_migration_versions`. |
| `doctrine:schema:update --dump-sql` puis exécution manuelle des seules lignes pertinentes (table `plan` + `station.plan_id`) via PDO direct | Le dump complet contenait aussi du bruit de drift pré-existant (differences `int`/`int(11)` MySQL 8) sans rapport avec ce changement — appliqué seulement ce qui concerne `Plan`/`Station::plan`, écrit la migration `Version20260814120000.php` à la main pour correspondre exactement. |
| `INSERT INTO doctrine_migration_versions ...` (PDO direct) | Marque les 3 anciennes migrations + la nouvelle comme exécutées, sans rejouer leur SQL (déjà appliqué). |
| `php documentation/scripts/extraire_communes_departements.php` | Extrait juste `Nom_commune`/`Code_departement` de `communes-par-contrat.csv` (55 Mo, jamais commité) vers un petit CSV commit-able (1310 paires) utilisé pour déduire le département d'une Station à partir de `Station::ville`. |
| Script d'analyse ad hoc (PHP inline) | Vérifié la fiabilité de commune→département (4 communes ambiguës sur 1310, ex: Blandy 91/77) et le taux de correspondance `Station::ville` → commune (1086/1161 villes distinctes, le reste étant surtout des arrondissements parisiens gérés à part et des gares hors Île-de-France). |
| Script d'analyse ad hoc (PHP inline) sur `plans-de-secteur.csv` | Confirmé que **seul le département 75 est couvert par un seul Plan** — tous les départements de grande couronne sont scindés (le 77 en compte 24) : l'assignation automatique de `Station::plan` ne peut donc concerner que Paris, le reste doit être assigné à la main (comportement voulu, validé avec l'utilisateur). |
| `php bin/console app:importer-plans-secteur` (x2, vérif idempotence) | 73 Plan créés, 878 Stations parisiennes assignées automatiquement ; second passage : 0 création, 0 nouvelle assignation (les Station déjà assignées ne sont jamais écrasées). |
| Création d'un utilisateur admin de test local (`security:hash-password` + INSERT SQL direct) | Vérification navigateur de `/plan` (liste, 73 lignes), `/plan/1` (Secteur Paris, 878 stations listées), `/station/{id}` (lien "Plan de secteur" affiché) et `/station/{id}/edit` (champ `plan` éditable, 73 options + "-- Aucun --"). Utilisateur supprimé après vérification. |
| `php bin/phpunit` (134 tests), `npx jest` (36 tests) | Tout passe après ajout de l'entité `Plan`. |
| `git push origin main` + `gh run watch` | CI et déploiement verts (build assets, PHPUnit, Jest, rsync, migration, symlink). |
| `ssh -i ~/.ssh/deploy-metroratp/id_ed25519 -p 65002 ... "ls documentation/scripts/donnees-extraites/"` | Vérifié que `documentation/` (dont les CSV dérivés) est bien synchronisé en prod malgré le `--exclude='documentation'` du workflow — en pratique rien de gros n'est jamais exclu puisque `documentation/IDFM-gtfs/` (55 Mo+) est gitignore et n'existe donc jamais dans le checkout source. Pas besoin d'upload manuel supplémentaire. |
| `ssh ... php bin/console app:importer-plans-secteur --env=prod` | 73 Plan créés, 878 Stations assignées — résultats identiques au local. |
| Compte de test admin temporaire (`test_verif_plan`, méthode base64+SQL habituelle) + `curl` authentifié | Vérifié `/plan` (73 lignes), `/plan/1` (878 stations), `/station/{id}` (lien Plan affiché) et `/station/{id}/edit` (74 options, la bonne présélectionnée) en production. Compte supprimé après vérification. |

## Session du 2026-08-14 (suite 14) — Carte du réseau : bulle interactive + filtre par mode

Sur `/carte`, chaque ligne de la bulle au survol d'une station devient interactive (survol =
aperçu du tracé, clic = surbrillance fixée) et 5 cases à cocher (Métro/RER/Tram/Bus/Autres)
filtrent les stations affichées.

| Commande | Objectif |
|---|---|
| `npx encore dev` | Rebuild des assets front après modification de `carte-reseau.js`/`carte-tooltip.js`/`app.js` (versionné, voir piège de cache déjà documenté). |
| `npx jest` (49 tests, dont les nouveaux `carte-reseau.test.js`/`carte-tooltip.test.js`) et `php bin/phpunit` (134 tests) | Tout passe. |
| Compte de test admin local (`test_carte2`) + connexion via `form.submit()` en JS (le clic direct sur le bouton via l'outil de navigation a de nouveau échoué silencieusement, cohérent avec le souci déjà documenté) | Vérification de la carte : bulle interactive ouverte via `dispatchEvent(MouseEvent('mousemove'/'mouseover'))` sur le canvas Leaflet (hit-testing interne, pas d'éléments DOM par marqueur) ; survol d'une ligne dans la bulle déclenche bien `GET /ligne/{id}/trace` (vérifié via `read_network_requests`) ; clic réutilise le cache (pas de second appel réseau) ; décocher "Bus" fait disparaître le marqueur bus-only "Hôtel de Ville" (plus de bulle au survol du même point), le recocher le fait réapparaître. Aucune erreur console à aucune étape. Compte supprimé après vérification. |

## Session du 2026-08-14 (suite 15) — Pôles d'échange (entité `PoleEchange`)

10 pôles d'échange IDFM (grandes gares/aéroports), sans clé de rattachement dans le dataset
source vers les Station : rattachement construit à la main après avoir écarté un matching flou
(trop de faux positifs sur les vraies données).

| Commande | Objectif |
|---|---|
| Script PHP inline (`SELECT ... WHERE label LIKE '%terme%'`) sur des termes comme "Roissy", "Charles de Gaulle", "Saint-Michel" | Confirmé le risque de faux positifs d'un matching flou : des dizaines de résultats sans rapport avec le pôle recherché (arrêts de bus homonymes dans des communes éloignées). Décision : ne pas automatiser, curer la liste à la main. |
| Script PHP inline (`SELECT ... WHERE label = ?`, exact) pour chaque candidat plausible par pôle | Construit la liste vérifiée `STATIONS_PAR_POLE` (32 couples label+ville) de `ImporterPolesEchangeCommand`, en excluant explicitement les faux positifs identifiés (ex: "Châtelet" à Montereau-Fault-Yonne, "Saint-Michel" à Étampes/Moissy-Cramayel, "La Muette" à Chesnay-Rocquencourt). |
| `php bin/console app:importer-poles-echange` (x2, vérif idempotence) | 10 PoleEchange créés, 32/32 Stations assignées (0 candidat introuvable) — confirme la liste manuelle contre la vraie base. Second passage : 0 création, 32 mises à jour de Pole (pas de doublon Station). |
| Compte de test admin local (`test_pole`) + connexion via `form.submit()` en JS | Vérifié `/pole-echange` (10 lignes), `/pole-echange/7` (Châtelet - Les Halles, 4 stations correctes), `/station/336` (lien Pôle affiché) et `/station/336/edit` (11 options, la bonne présélectionnée). Compte supprimé après vérification. |
| `php bin/phpunit` (134 tests), `npx jest` (49 tests) | Tout passe après ajout de l'entité `PoleEchange`. |
| `git push origin main` + `gh run watch` + `gh run rerun --failed` | CI verte (le blip SSH transitoire connu a de nouveau frappé sur ce push, corrigé par un rerun). |
| `ssh ... php bin/console app:importer-poles-echange --env=prod` | 10 PoleEchange créés, 32/32 Stations assignées — résultats identiques au local. |
| Compte de test admin temporaire (méthode base64+SQL) + `curl` authentifié | Vérifié `/pole-echange` (10 pôles, mêmes effectifs qu'en local) et `/station/334` (lien Pôle affiché) en production. Compte supprimé après vérification. |
| Compte de test admin temporaire supplémentaire + `curl` authentifié | Vérifié aussi la carte interactive (commit précédent, pas encore testé en prod) : `/carte` contient bien les 5 cases à cocher et l'attribut `data-trace-url`, `GET /ligne/1/trace` renvoie le JSON attendu (trace + couleur). Compte supprimé après vérification. |

## Session du 2026-08-14 (suite 16) — Mini-carte des accès (équivalent "plan de quartier")

Recherche sur le portail open-data IDFM (aucun dataset ne fournit le visuel "plan de quartier"
affiché sur les quais), puis construction d'une mini-carte "maison" par Station.

| Commande | Objectif |
|---|---|
| Extension de `documentation/scripts/extraire_acces_entrees.php` (colonnes `lat`/`lon` depuis `stop_lat`/`stop_lon` de stops.txt, déjà itéré pour les accès) + réexécution | Régénère `acces_entrees.csv` avec les coordonnées de chaque accès (2515 lignes). |
| `php bin/console app:construire-acces-sorties` (reconstruction complète, comme d'habitude) puis `app:construire-positions-rame` (dépendance documentée : la purge d'Acces entraîne celle de PositionRame) | 2513 Acces recréés avec coordonnées, 4671 PositionRame reconstruites. |
| `/c/xampp/mysql_start.bat` | MySQL local s'était arrêté entre deux sessions, redémarré. |
| `npx encore dev` | Build après ajout de `carte-acces.js`/modification `app.scss`/`app.js`. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests, dont le nouveau `carte-acces.test.js`) | Tout passe. |
| Compte de test admin local (`test_acces`) + connexion via `form.submit()` en JS | Vérifié `/station/20129` (Gare Saint-Lazare, 14 sorties) : carte Leaflet initialisée (15 tuiles CARTO Positron chargées, `complete:true`), 14 bandeaux `.carte-acces-sortie` avec le texte attendu ("Sortie 3 — passage du Havre" etc.), aucune erreur console. Compte supprimé après vérification. |

## Session du 2026-08-14 (suite 17) — Refonte page Carte (onglets réseau/secteurs, modal)

Renommage "Carte du réseau" → "Carte", ajout d'un choix d'onglet Carte du réseau / Carte des
secteurs, cases de filtre décochées par défaut + bouton "Afficher" ouvrant la carte dans un modal
(comme le modal déjà existant sur `/trajet`), et un onglet secteurs avec sélection + `<object>`
PDF dans un second modal.

| Commande | Objectif |
|---|---|
| Réécriture de `carte-reseau.js` (calcul de `actifsInitial` avant la boucle de construction des marqueurs, au lieu d'ajouter tout puis filtrer) | Nécessaire car les cases partent maintenant décochées par défaut : sans ce changement, tous les marqueurs auraient été ajoutés puis retirés au premier `change`, au lieu de ne jamais être ajoutés. |
| `npx encore dev`, `npx jest` (51 tests), `php bin/phpunit` (134 tests) | Tout passe (aucun changement de schéma dans cette suite). |
| Compte de test admin local + connexion JS, vérifications via `dispatchEvent`/`querySelector` (pas de screenshot, pane non affiché) | Confirmé : cases décochées par défaut ; cocher "Métro" seul puis cliquer "Afficher" ouvre le modal avec uniquement les stations métro visibles (un point bus-only connu ne remonte plus de bulle) ; cocher "Bus" en direct pendant que le modal est ouvert le fait réapparaître (filtre toujours réactif) ; l'onglet "Carte des secteurs" affiche les 73 options, sélectionner "Secteur Paris" + "Afficher" ouvre le second modal avec `<object data="...plan03.pdf">` et le lien de secours pointant vers la bonne URL. Aucune erreur console. Compte supprimé après vérification. |

## Session du 2026-08-15 — Accessibilité PMR par gare

Fouille du dossier IDFM-gtfs à la demande de l'utilisateur ("je veux que le site soit une bible
des transports") : plusieurs fichiers jamais exploités identifiés, l'utilisateur a choisi de
commencer par l'accessibilité PMR.

| Commande | Objectif |
|---|---|
| Script PHP inline (jointure `stop_point_id` (sans/avec préfixe `stop_point:`) → `stops.txt` → `parent_station`) | Vérifié le taux de résolution vers une ZdC avant de coder l'import : 0/459 avec le préfixe complet, 455/459 après l'avoir retiré (l'écart venait juste du préfixe `stop_point:` absent de `stops.txt`). |
| `php documentation/scripts/extraire_accessibilite_gares.php` | Génère `accessibilite_gares.csv` (455 lignes, colonnes zdc/niveau/commentaire), commité. |
| `php bin/console app:importer-accessibilite-gares` | 455/455 Stations mises à jour (0 ignorée), rattachement via `trouverIdCanoniqueParZdc()` comme les autres imports par ZdC. |
| `php bin/phpunit` (134 tests) | Tout passe (pas de changement JS dans cette fonctionnalité). |
| Compte de test admin local + connexion JS | Vérifié `/station/1` (La Défense) : ligne "Accessibilité PMR" affichée avec le commentaire détaillé complet. Compte supprimé après vérification. |

## Session du 2026-08-15 (suite) — Plans régionaux (branche feature/plans-regionaux)

| Commande | Objectif |
|---|---|
| `php bin/console app:importer-plans-region` (x2, vérif idempotence) | 20 PlanRegion créés (dataset complet, pas de rattachement complexe nécessaire ici). Second passage : 0 création, 20 mises à jour. |
| Compte de test admin local + connexion JS | Vérifié `/plan-region` (liste complète) et `/carte` (onglet "Carte des secteurs" : le select contient bien 2 `<optgroup>`, "Plans régionaux" avec 20 options et "Secteurs" avec 73). Compte supprimé après vérification. |

## Session du 2026-08-15 (suite) — PDF affichés directement sur le site (branche feature/plans-regionaux)

| Commande | Objectif |
|---|---|
| `php bin/phpunit` (134 tests) | Tout passe (pas de changement de schéma). |
| Vérification manuelle du rendu Twig (`visionneuse_pdf.html.twig` inclus dans `plan_region/show.html.twig`) | Cohérent avec le même composant déjà vérifié sur `main` (`plan/show.html.twig`). |

*(Entrées suivantes ajoutées au fil des prochaines commandes/sessions.)*
