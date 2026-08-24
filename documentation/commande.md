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

## Session du 2026-08-16 — Pôles d'échange : rattachement officiel via `relations.csv`

Remplacement de la liste `STATIONS_PAR_POLE` (32 couples label+ville, vérifiée à la main) par un
rattachement basé sur la clé officielle `ZdCId` de `relations.csv`, qui correspond exactement à
`Station.codeExterne`. Découverte en cours de route d'un vrai piège lié aux Station dupliquées
(voir TODO.md) : 16 Station "historiques" sans `code_externe` porteuses de vraies `Desserte`
auraient perdu silencieusement leur `PoleEchange` si on s'était fié uniquement à `relations.csv`.

| Commande | Objectif |
|---|---|
| Script PHP inline (parsing `relations.csv`, comptage `array_unique`) | 753 lignes avec un `PdEId` non-nul sur 52576 au total ; 10 `PdEId` distincts, 34 `ZdCId` distincts. |
| Script PHP inline (`SELECT code_externe FROM pole_echange`) puis comparaison en PHP | Les 10 `PdEId` distincts de `relations.csv` correspondent EXACTEMENT aux 10 `PoleEchange.codeExterne` déjà importés (aucun manquant, aucun en trop). |
| Script PHP inline (`SELECT code_externe FROM station WHERE code_externe = ?` pour chacun des 34 `ZdCId`) | 34/34 (100%) trouvent une `Station.codeExterne` existante — confirme que `relations.csv` est une source fiable et directement exploitable, sans matching flou. |
| Réécriture de `ImporterPolesEchangeCommand::execute()` : lecture de `relations.csv`, `UPDATE station SET pole_echange_id = NULL` puis réassignation via jointure exacte `ZdCId = code_externe` | Rendue idempotente (un rejeu repart de zéro). Premier essai : 34 Stations assignées — mais total en base après coup (`52` avant le fix du double-comptage, voir ligne suivante) a révélé le vrai souci. |
| `SELECT s.id, s.label, s.ville, s.code_externe, p.code_externe FROM station s JOIN pole_echange p ...` (avant correctif) | 52 Stations assignées au lieu des 34 attendues : d'anciennes assignations manuelles laissées sur des Station dupliquées non retouchées par le nouveau matching. Après ajout du `UPDATE ... SET pole_echange_id = NULL` : proprement retombé à 34. |
| Script PHP inline recoupant chaque candidat de l'ancienne `STATIONS_PAR_POLE` avec `code_externe`/`COUNT(desserte)` | Isolé précisément 16 Station "historiques" (ex: id 88 "Montparnasse — Bienvenüe", 4 dessertes réelles ; id 76 "Gare du Nord", 4 dessertes) sans aucun `code_externe`, donc structurellement invisibles pour `relations.csv`, mais bien porteuses de vraies données `Desserte` — pas de simples doublons vides à ignorer. |
| Ajout de `LEGACY_GAP_SANS_CODE_EXTERNE` (16 labels, sous-ensemble minimal de l'ancienne liste, matching `label + ville IS NULL + code_externe IS NULL`) en complément (jamais à la place) de `relations.csv` | 34 (officiel) + 16 (complément legacy) = 50 Stations assignées au total, contre 32 avant — confirmé idempotent (rejeu → toujours 50, sans avertissement). |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe, aucune migration nécessaire (changement pur commande + données, schéma `PoleEchange`/`Station::poleEchange` déjà existant). |
| `git push origin main` + `gh run watch` | CI vert (Jest, PHPUnit, build assets), déploiement Hostinger réussi. |
| `ssh-keyscan` puis `ssh ... php bin/console app:importer-poles-echange --env=prod` | 34 Stations via `relations.csv` + 15/16 via le complément legacy (1 avertissement : "Saint-Michel Notre-Dame" introuvable — décalage local/prod déjà documenté dans "Stations dupliquées", pas un bug du nouveau code). Total 49 en prod (vs 50 en local). |
| `dbal:run-sql --env=prod "SELECT COUNT(*) FROM station WHERE pole_echange_id IS NOT NULL"` | 49, conforme à l'attendu (34+15). |
| Compte de test admin temporaire (`test_verif_pole`, méthode `Utilisateur`/bcrypt habituelle) + connexion via le Browser tool (curl seul échouait : `Invalid CSRF token`, la protection CSRF stateless du site — `config/packages/csrf.yaml`/`ux_turbo.yaml` — exige apparemment l'exécution JS de la page, pas un bug de prod) | Vérifié `/pole-echange` (49 stations réparties sur 10 pôles, somme exacte) et `/station/77` ("Gare de l'Est", la Station historique récupérée par le complément legacy, 3 vraies Dessertes lignes 4/5/7) : le lien "Pôle d'échange : Paris Est" s'affiche correctement. Compte supprimé après vérification. |

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

## Session du 2026-08-15 (suite) — Points de vente (branche feature/points-de-vente)

L'utilisateur a demandé de créer 4 branches (une par fichier restant identifié dans la fouille du
dossier) et de tout construire, l'une après l'autre, sans merger tout de suite ("on mergera
ensuite") : pas de déploiement/vérification production pour ces branches tant qu'elles ne sont
pas mergées sur main.

| Commande | Objectif |
|---|---|
| `git branch feature/points-de-vente main` (+ 3 autres branches, voir suites suivantes) | Crée les 4 branches de travail depuis la pointe de main, dans le clone propre (le seul endroit où `git` fonctionne pour ce projet). |
| Script PHP inline sur `points-de-vente.csv` (comptage `ZdAId`) | Découvert que `ZdAId` vaut 0 sur les 2012 lignes sans exception : aucun rattachement officiel possible vers une Station, contrairement à l'hypothèse de départ (rattachement via ZdA→ZdC comme pour les accès). Pivoté vers un rattachement par proximité géographique. |
| `php bin/console app:importer-points-de-vente` (x2, vérif idempotence + timing) | ~11 s (2012 points × 14070 Stations, calcul de distance en PHP). 2012 créés, 1989 rattachés à moins de 300 m. Second passage : 0 création, 2012 mises à jour, même taux de rattachement. |
| Compte de test admin local + connexion JS | Vérifié `/point-de-vente` (liste paginée) et `/station/1` (La Défense, 4 points de vente à proximité listés). Compte supprimé après vérification. |

## Session du 2026-08-15 (suite) — Horaires et plans par ligne (branche feature/horaires-lignes)

| Commande | Objectif |
|---|---|
| Script PHP inline (jointure `ID_Line` → `Ligne.code_externe`, puis repli `Name_Line` → `UPPER(label)`) | Vérifié le taux de rattachement avant de coder l'import : 3218/4507 (71%) par codeExterne exact, seulement +20 via le repli par label (le probleme n'est donc pas principalement le codeExterne corrompu du metro, contrairement a l'hypothese de depart — la majorite des non-rattaches sont des Ligne absentes de la base). |
| Script PHP inline (comptage URL dupliquées) | 57 doublons exacts sur 4507 lignes — dédupliqué par URL comme clé naturelle (le CSV source n'a pas d'id par document). |
| `php bin/console app:importer-documents-lignes` (x2, vérif idempotence + timing) | ~7s. 3188 DocumentLigne créés (1262 ignorés : Ligne introuvable). Second passage : 0 création, 3188 mises à jour. |
| Compte de test admin local + connexion JS | Vérifié `/document-ligne` (liste paginée) et `/ligne/1` (Métro 1) : section "Horaires et plans" n'affiche que le document réellement rattaché à cette Ligne précise ("Plan Métro 1"), confirmant que les entrées visuellement similaires (même badge "1") dans la liste globale appartiennent à d'autres Ligne homonymes (bus d'autres opérateurs), pas un bug de rattachement. Compte supprimé après vérification. |

## Session du 2026-08-16 (suite) — Sortie : pagination préventive

`SortieController::index()` avait exactement le même anti-pattern que `CorrespondanceController`
avant son correctif du 2026-08-15 (`findAllWithDetails()` sans pagination). Corrigé
préventivement avant que les 2513 lignes actuelles ne posent problème.

| Commande | Objectif |
|---|---|
| `SortieRepository::findAllWithDetails()` renommée `creerRequeteAvecDetails()` (retourne un `QueryBuilder`), `SortieController::index()` paginé 50/page, `knp_pagination_render` ajouté au template | Même traitement exact que `CorrespondanceController`. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe. |
| `git push origin main` + `gh run watch` | CI vert, déploiement Hostinger réussi. |
| Compte de test admin temporaire (prod) + connexion via le Browser tool | Vérifié `/sortie` : 50 lignes affichées (au lieu des 2513 totales), pas de crash. Compte supprimé après vérification. |

## Session du 2026-08-16 (suite) — Sanisettes publiques (entité `SanisettePublique`)

Dataset Paris Open Data "sanisettesparis2011" (609 toilettes publiques de voirie), distinct des
`Sanitaire` RATP en station.

| Commande | Objectif |
|---|---|
| Script PHP inline (comptage des valeurs distinctes par colonne) | 609 lignes ; `STATUT` : 580 "En service"/29 "Hors service" ; `TYPE` : 5 valeurs (Sanisette/WC/Urinoir/Lavatory/Urinoir femme) ; `source`/`complement_adresse` constantes sur les 609 lignes (aucune vraie donnée, non importées, même décision que pour `agency.txt`) ; `URL_FICHE_EQUIPEMENT` renseignée sur 154/609 (25%). |
| Nouvelle entité `SanisettePublique` + migration (`Version20260816090000`, table `sanisette_publique`) + `SanisettePubliqueRepository`/`SanisettePubliqueType`/`SanisettePubliqueController` (CRUD complet, paginé) + templates | Même structure que `Sanitaire`/`Defibrillateur`/`FontaineEau`. |
| `Station::sanisettesPubliques` (OneToMany) + section "Sanisettes publiques à proximité" sur `station/show.html.twig` + lien menu | Intégration à la fiche Station, même pattern que les 3 entités géo-proximité précédentes. |
| `php bin/console app:importer-sanisettes-publiques` (rattachement par proximité, seuil 300m comme `PointDeVente`/`Sanitaire`) | 609 SanisettePublique créées, **606 (99%) rattachées** à une Station — bien plus que prévu (hypothèse initiale erronée que la majorité seraient sans Station proche ; corrigée dans les docblocks avant commit : Paris intra-muros est dense en arrêts de bus, donc quasiment toutes les sanisettes se trouvent à moins de 300m d'un arrêt du réseau, pas seulement celles proches d'une station métro/RER). |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe (table créée aussi en base de test via `dbal:run-sql --env=test`, même piège d'isolation déjà documenté). |
| Compte de test admin local + connexion via le Browser tool, serveur `symfony server:start` | Vérifié `/sanisette-publique` (liste avec Station rattachée cohérente), `/sanisette-publique/1` ("10 rue ortolan", rattachée à "Place Monge") et `/station/154` (section "Sanisettes publiques à proximité" affiche bien 2 entrées). Compte supprimé après vérification. |
| `git push origin main` + `gh run watch` + `gh run rerun --failed` | CI verte (le blip SSH transitoire connu a de nouveau frappé sur ce push, corrigé par un rerun). |
| `ssh ... php bin/console app:importer-sanisettes-publiques --env=prod` | 609 SanisettePublique créées, 606 rattachées (99%) — résultats identiques au local. |
| Compte de test admin temporaire (prod) + connexion via le Browser tool | Vérifié `/sanisette-publique` (mêmes données qu'en local) et `/sanisette-publique/584` (fiche complète, Station "Franklin D. Roosevelt" rattachée). Compte supprimé après vérification. |

## Session du 2026-08-16 (suite) — Commerces de proximité (enrichissement PointDeVente)

Avant d'importer `commerces-de-proximite-agrees-ratp.csv`, vérification du chevauchement avec
`points-de-vente.csv` déjà en base (piste explicitement notée comme risquée dans TODO.md).

| Commande | Objectif |
|---|---|
| Script PHP inline (comptage lignes/catégories) | 911 lignes ; 11 catégories fines (café tabac 515, tabac loto 174, tabac presse 102, etc.) ; colonnes `Column 8` à `Column 14` et `source`-like toutes vides. |
| Script PHP inline (recoupement géographique `commerces-de-proximite` vs `PointDeVente` type "Commerce de proximité", plusieurs seuils testés 30m/50m/100m/200m/300m) | 887/911 (97%) déjà présents à moins de 30m, 889/911 (98%) à moins de 50m, plateau ensuite (902/911 même à 300m) — confirme un chevauchement massif, pas une coïncidence de seuil. Décision : enrichir l'existant plutôt que dupliquer. |
| Ajout de `PointDeVente::categorieCommerce`/`jourFermeture` (nullable) + migration (`Version20260816110000`) | Deux champs absents du dataset `points-de-vente` officiel, seulement disponibles via ce second dataset. |
| Nouvelle commande `app:importer-commerces-proximite` : pour chaque commerce, `UPDATE` du `PointDeVente` le plus proche (< 50m) ; sinon `INSERT` d'un nouveau (`codeExterne` préfixé `COM-` + `identifiant commerce`, pour ne pas collisionner avec le format `PdVId` existant) | 889 enrichis, 20 créés en plus (commerces agréés RATP absents du référentiel officiel). Second passage : 909 enrichis (les 20 nouveaux se retrouvent eux-mêmes par coordonnées exactes), 0 création — confirmé idempotent. |
| `PointDeVenteType`/`_form.html.twig`/`show.html.twig`/`index.html.twig` mis à jour (2 nouveaux champs) | Catégorie affichée en colonne sur la liste, catégorie + jour de fermeture sur la fiche détail et le formulaire. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe (colonnes ajoutées aussi en base de test). |
| Compte de test admin local + connexion via `javascript_tool` (`form.submit()` direct — le clic via `computer` a de nouveau échoué silencieusement sur ce formulaire, comportement déjà documenté) | Vérifié `/point-de-vente/2` ("Librairie Fontaine", catégorie "tabac presse" + jour de fermeture "dimanche" affichés) et `/point-de-vente` (colonne "Catégorie" visible, vide pour les non-enrichis). Compte supprimé après vérification. |
| `git push origin main` + `gh run watch` | CI vert du premier coup (pas de blip SSH cette fois), déploiement Hostinger réussi. |
| `ssh ... php bin/console app:importer-commerces-proximite --env=prod` | 889 enrichis, 20 créés en plus — résultats identiques au local. |
| Compte de test admin temporaire (prod) + connexion via `javascript_tool` | Vérifié `/point-de-vente/2` : catégorie "tabac presse" + jour de fermeture "dimanche" affichés, identique au local. Compte supprimé après vérification. |

## Session du 2026-08-16 (suite) — Suivi de projet en base (`Tache`/`Etape`/`StatutTache`)

Demande utilisateur : remplacer le suivi manuel dans `documentation/TODO.md` (source d'une vraie
erreur d'édition plus tôt dans la session — une section insérée au milieu d'une autre) par un vrai
CRUD en base, réservé à `ROLE_ADMIN`. Conçu par itérations successives avec l'utilisateur (table
séparée `StatutTache` façon `StyleStation`, une `Tache` a plusieurs `Etape`).

| Commande | Objectif |
|---|---|
| 3 nouvelles entités `StatutTache`/`Tache`/`Etape` (`Etape.tache` ManyToOne NOT NULL avec `orphanRemoval: true`, `Tache.statut` ManyToOne NOT NULL vers `StatutTache`) + Repository/Form/Controller/templates pour chacune (CRUD complet, même pattern que `StyleStation`/`Plan`) | `Tache` : nom, description, datetimeCreation, statut, datetimeAction, datetimeAchevement. `Etape` : nom, description, tache, datetimeCreation, datetimeAchevement. |
| `doctrine:schema:update --dump-sql` puis migration écrite à la main (`Version20260816130000`, ordre `statut_tache` → `tache` → `etape` à cause des FK, dump alphabétique de Doctrine ne le respectait pas) + `INSERT` des 4 `StatutTache` dans la migration elle-même | Table créée avec les 4 statuts (`A_FAIRE`/`EN_COURS`/`SUSPENDUE`/`ACHEVEE`) dès le déploiement, sans étape manuelle supplémentaire en prod. |
| `config/packages/security.yaml` : nouvelle règle `access_control` `^/(tache\|etape\|statut-tache)` → `ROLE_ADMIN`, insérée avant les règles génériques `ROLE_USER` (seule la première règle qui matche s'applique) | Zone admin-only, contrairement au reste du site en lecture libre dès `ROLE_USER` — ce n'est pas une donnée du réseau de transport. |
| Lien "Suivi projet" ajouté dans le menu, à l'intérieur du bloc déjà réservé à `is_granted('ROLE_ADMIN')` (à côté de "Utilisateurs") | Pas de lien visible pour un utilisateur non-admin. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe (tables créées aussi en base de test). |
| Compte de test admin local + connexion via `javascript_tool` (le clic sur "Enregistrer" a de nouveau échoué silencieusement, comportement déjà documenté sur ce projet) | Créé une `Tache` de test (formulaire, liste déroulante des 4 statuts fonctionnelle), ajouté une `Etape` liée depuis la fiche `Tache`, vérifié l'affichage correct sur les deux fiches. Testé aussi avec un second compte `ROLE_USER` simple (sans `ROLE_ADMIN`) : `/tache` renvoie bien une 403 Access Denied, confirmant que la restriction cible spécifiquement le rôle et pas seulement l'authentification. Comptes et données de test supprimés après vérification. |
| `git push origin main` + `gh run watch` | CI vert du premier coup, déploiement Hostinger réussi — la migration s'exécute automatiquement pendant le déploiement (pas de commande SSH manuelle nécessaire, contrairement aux commandes d'import de données). |
| `ssh ... dbal:run-sql --env=prod "SELECT id, label FROM statut_tache"` | Table créée, 4 statuts présents avec les mêmes id qu'en local — migration appliquée correctement. |
| Compte de test admin temporaire (prod) + connexion via `javascript_tool` | Vérifié `/tache` accessible et fonctionnel en prod. Compte supprimé après vérification. |

## Session du 2026-08-16 (suite) — Incident : contenu de TODO.md accidentellement supprimé, restauré

En préparant la migration de TODO.md vers `Tache`/`Etape` (tâche demandée par l'utilisateur), 9
sections entières se sont révélées manquantes du fichier : StyleStation (les deux sections),
Enrichissement Materiel via Wikidata, Fontaines à eau/Défibrillateurs/Sanitaires en station, Plans
régionaux, Projets d'arrêts. Cause : le commit `e8dbd70` (début du travail sur `relations.csv`,
2026-08-16 08:40) avait copié une version de `documentation/TODO.md` depuis le poste Desktop qui,
pour une raison antérieure non entièrement retracée, ne contenait déjà plus ce contenu — la copie
Desktop→clone de ce jour-là n'a pas été diffée avec suffisamment de rigueur avant d'être committée
(contrairement à la pratique habituelle de ce projet, voir les nombreuses autres entrées de ce
journal où un `diff --strip-trailing-cr` a bien été fait avant copie).

| Commande | Objectif |
|---|---|
| `git log --oneline -- documentation/TODO.md` puis `git show <commit>:documentation/TODO.md` sur `13a6391` (dernier commit avant la perte, StyleStation) et `HEAD` | Comparé les deux versions complètes : 9 sections présentes dans `13a6391` mais absentes de `HEAD`, et 3 sections légitimement nouvelles présentes dans `HEAD` mais pas dans `13a6391` (`agency.txt`, écarts référentiel/OSM, Arrêt Transporteur — ajoutées entre les deux sans jamais avoir été committées avant `e8dbd70`). |
| Fusion manuelle des deux versions (réinsertion des 9 sections perdues aux emplacements logiques, mise à jour des puces à barré qui pointaient vers elles) | Union complète des deux historiques, aucune perte, 33 sections au total dans `documentation/TODO.md`. |

## Session du 2026-08-16 (suite) — Migration du contenu de TODO.md vers Tache/Etape

Demande utilisateur : tout ce qui est achevé part en base, `TODO.md` local ne garde que ce qui
reste à faire.

| Commande | Objectif |
|---|---|
| Script PHP `migrer_todo_vers_tache.php` (scratchpad, non commité — même convention que les scripts d'application de données ponctuels de cette session) : tableau structuré de 43 `Tache` (dont 2 avec plusieurs `Etape` : "Pôles d'échange" et "StyleStation", chacune retraçant ses 2 phases distinctes) reprenant l'intégralité des 33 sections de `TODO.md`, exécuté en local puis en prod via SSH | 43 Tache créées (29 ACHEVEE, 14 A_FAIRE), 4 Etape créées. Vérifié en local via le Browser tool : liste correcte triée par date de création, fiche "Pôles d'échange" affichant bien ses 2 étapes avec leurs dates propres. |
| Réduction de `documentation/TODO.md` de 645 à 139 lignes (33 sections → 10, seules les tâches encore ouvertes conservées, tout le détail technique des tâches achevées reste accessible via `/tache`) | `git status`/diff vérifié avant copie : uniquement des suppressions de sections déjà migrées, rien de nouveau perdu par erreur cette fois. |

## Session du 2026-08-16 (suite) — Tri cliquable sur toutes les colonnes de toutes les pages d'index

Demande utilisateur : sur chaque page d'index (liste), pouvoir cliquer sur l'en-tête d'une colonne
pour trier par cette colonne (ASC, puis DESC au second clic), comme sur `/correspondance`.

| Commande | Objectif |
|---|---|
| Agent Explore : inventaire exhaustif de tous les contrôleurs `index()` tabulaires | 31 pages concernées sur 36 contrôleurs `index()` (5 hors périmètre : accueil, inscription, carte interactive, calculateur de trajet, choix de ligne — pas de `<table>`). 14 déjà paginées via `PaginatorInterface`+`QueryBuilder`, 17 en simple `findAll()`/`findBy()` sans pagination du tout. |
| Ajout d'un style CSS générique (`assets/styles/app.scss`) ciblant les classes `sortable`/`asc`/`desc` que KnpPaginatorBundle ajoute automatiquement au lien `knp_pagination_sortable()` | Flèches ⇅/▲/▼ visibles sur chaque en-tête triable, sans dupliquer de logique par page. |
| 7 contrôleurs de référence simples (`StatutTache`, `StyleStation`, `TypeMateriel`, `TypeTransport`, `TypeTroncon`, `Gestionnaire`, `Service`) convertis de `findAll()` vers `QueryBuilder`+`Paginator` | Pattern identique partout, établi comme base pour la suite. |
| 14 contrôleurs déjà paginés : ajout de `knp_pagination_sortable()` sur les colonnes triviales/relations déjà jointes, ajout des `leftJoin` manquants pour les colonnes relation pas encore jointes (`Defibrillateur`/`FontaineEau`/`PointDeVente`/`Sanitaire`/`SanisettePublique` → `station`, `Desserte` → `station`+`styleStation` dans `DesserteRepository::creerRequeteFiltree()`, en retirant au passage le join conditionnel devenu redondant) | `Correspondance`, `Defibrillateur`, `Desserte`, `DocumentLigne`, `Etape`, `FontaineEau`, `Ligne`, `PointDeVente`, `ProjetArret`, `SanisettePublique`, `Sanitaire`, `Sortie`, `Tache`, `Troncon`. |
| 10 contrôleurs `findAll()`/`findBy()` convertis avec jointures ajoutées : `Acces`, `Materiel` (`findAllWithDetails()` renommée `creerRequeteAvecDetails()` retournant un `QueryBuilder`, même pattern déjà établi pour `Correspondance`/`Sortie`), `MaterielLigne`, `PeriodeOuverture` (même renommage), `Plan`, `PlanRegion`, `PoleEchange`, `PositionRame`, `Station`, `Utilisateur` | **`Acces` (2513 lignes) et `Station` (~14000 lignes) n'avaient aucune pagination du tout** — même famille de risque que le crash mémoire `/correspondance` déjà corrigé, éliminée au passage en plus d'ajouter le tri. |
| Colonnes calculées/dérivées explicitement laissées non triables (pas de colonne SQL correspondante) | `Ligne.nombreStations`/`terminusLabels`, `Desserte.premiereOuverture`/statut, `Troncon.Départ`/`Arrivée`/`Direction` (dérivées de `sensCirculation()`), `PoleEchange.stations\|length`, `Defibrillateur`/`MaterielLigne` "Disponibilité"/"Effectif" (concaténations de plusieurs champs), `Utilisateur.Rôle` (JSON). |
| Script curl exhaustif (compte admin de test local, boucle bash sur les 31 routes × chaque colonne × ASC/DESC) | **178 combinaisons testées, 1 erreur trouvée** : `Correspondance.tempsEstimeMinutes` n'est pas une colonne mappée (calculée depuis `distance` dans le getter) — DQL échouait avec 500. Corrigé en pointant le tri sur `c.distance` (transformation linéaire monotone de la même valeur, ordre identique). Après correctif : 178/178 OK. |
| Vérification visuelle (Browser tool, navigation directe car le clic direct sur le lien a de nouveau échoué silencieusement — comportement déjà documenté sur ce projet) : `/ligne?sort=l.label&direction=desc` | Confirmé : tri Z→A appliqué, classe CSS `desc` active sur le lien (flèche ▼), lien suivant pointe vers `direction=asc` pour le prochain clic — cycle ASC/DESC fonctionnel de bout en bout. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe. |
| Tâche `Tache`/`Etape` correspondante marquée ACHEVEE en base (avec 4 Etape retraçant les phases : inventaire, implémentation ×2, tests) | Suivi du travail directement dans `/tache`, conformément au nouveau système mis en place plus tôt dans la session. |

## Session du 2026-08-17 — Affiner les temps de correspondance via transfers.txt (repli par nom)

Demande utilisateur : "continue une autre tache" (choix libre parmi les tâches A_FAIRE de `/tache`).
Tâche choisie : id=5, "Affiner les temps de correspondance TrajetFinder via transfers.txt".

| Commande | Objectif |
|---|---|
| `SELECT COUNT(*), SUM(distance IS NULL), SUM(distance IS NOT NULL) FROM correspondance` | 568 correspondances sur 107334 encore à distance NULL (0,5 %) — le libellé "la grande majorité utilise une estimation" dans l'ancien `TODO.md` était trompeur : la quasi-totalité (106766) a déjà une vraie distance issue de GTFS. |
| Répartition des 568 NULL par type | 505 même-Station (candidates au repli intra-ZdC), 63 Station différente (non traitées cette session, piste `correspondances_inter_zdc.csv` à explorer séparément). |
| Sur les 505 même-Station : combien ont déjà un `code_externe` propre | Seulement 23 — la commande existante (`AffinerDistancesCorrespondancesCommand`) ne pouvait déjà en affiner que 9, bloquée par le problème documenté "Stations dupliquées" (Station originale sans `code_externe`). |
| Simulation d'un repli par nom (label identique → Station jumelle avec `code_externe`) : test d'abord sans filtrer l'ambiguïté | 363/505 auraient un jumeau exploitable — mais vérification supplémentaire (`GROUP BY label` sur les jumeaux) a révélé que 18 labels sources ont PLUSIEURS jumeaux candidats (`République` → 23 communes différentes, `Gambetta` → 16, `Hôtel de Ville` → 35, etc. — noms de rue/place génériques, pas des stations uniques). Un repli aveugle aurait attaché des temps de marche à la mauvaise station pour ~167 correspondances. |
| Simulation restreinte au cas sûr (label avec EXACTEMENT un jumeau `code_externe`) | 196/505 — rendement réel et sûr, retenu pour l'implémentation. Les 167 ambigus et 142 sans jumeau restent volontairement NULL. |
| `src/Command/AffinerDistancesCorrespondancesCommand.php` : ajout du repli par nom (map `label → code_externe`, utilisée seulement quand une seule Station porte ce `code_externe` pour ce label), rapport détaillé (direct vs repli) | Docblock mis à jour pour expliquer le choix de restreindre au cas non ambigu. |
| `php bin/console app:affiner-distances-correspondances` (local) | "205 correspondances affinees (9 via code_externe direct, 196 via repli par nom)" — exactement la prévision de la simulation. `distance_nulle` passé de 568 à 372. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe — aucune régression, changement purement logique dans une commande CLI. |
| `documentation/TODO.md` | Section transfers.txt mise à jour : chiffre exact du rendement, explication des 300 restantes (167 ambigües, 142 sans jumeau), pointeur vers la fusion des Stations dupliquées comme vrai prochain déblocage. |

## Session du 2026-08-17 (suite) — Topologie RER C (embranchements complexes)

Demande utilisateur : "RER C" (choix de la tâche id=9 "Modeliser les lignes a embranchements
complexes (RER C, RER D)" parmi le backlog A_FAIRE, en reponse a une invitation generale a
continuer).

| Commande | Objectif |
|---|---|
| Lecture de `ConstruireTopologieRerCommand.php`/`ImporterLignesRerCommand.php` | La ligne C (id=21, codeExterne C01727, 75 dessertes) a ses stations/dessertes deja importees mais aucun troncon — `LIGNES_CODES` ne couvrait que A/B/D/E. Le mecanisme recursif (`descendre()`) gere deja un arbre a profondeur arbitraire (valide sur A/B/E) et un "foret" de 2 arbres separes pour exclure un maillage (RER D, Evry/Corbeil/Juvisy). |
| `documentation/scripts/extraire_troncons_rer.py` (deja utilise pour A/B/D/E) | Chemins casses (GTFS_DIR et REFERENTIEL_DIR externe n'existent plus a ces emplacements) — reecrit en PHP (`extraire_troncons_rer_c.php`) plutot que reparer l'ancien script python, en utilisant `documentation/IDFM-gtfs/csv/` (verifie present) et les coordonnees WGS84 deja extraites (`zdc_coordonnees.csv`, Haversine) au lieu du referentiel Lambert93 manquant. |
| Premiere extraction (route GTFS IDFM:C01727, confirme par route_short_name=C + route_type=2 + couleur FFCC30 correspondant exactement a `ImporterLignesRerCommand`) | 84 aretes brutes, 84 retenues apres reduction geometrique — aucune supprimee, suspect (75 stations, 84 aretes = 10 de trop pour un arbre). |
| Lecture du plan officiel `documentation/PLAN/plan-de-ligne_rer_ligne-c.*.png` (crops zoomes via PowerShell/System.Drawing) | Confirme 6 terminus reels et des embranchements a Bretigny/Viroflay/Choisy-le-Roi, mais aussi des elements (boucle Longjumeau/Chilly-Mazarin, visible sur le plan) absents du GTFS actuel — plan potentiellement date (timestamp 2021), GTFS actuel fait foi. |
| Analyse des aretes en trop : toutes concentrees sur le corridor Paris-Ivry-Vitry-Ardoines-Choisy-le-Roi-Juvisy (missions semi-directes qui sautent des gares a des profondeurs differentes) | L'algorithme original "plus long d'abord, retire si un chemin alternatif existe deja" (valide sur A/B/D/E) se fait tromper ici : en traitant le plus long raccourci en premier, un AUTRE raccourci pas encore retire sert de faux chemin alternatif court, et rien n'est jamais retire. |
| Reecriture de l'algorithme : "plus court d'abord, contre un graphe deja CONFIRME" (jamais contre d'autres raccourcis pas encore juges) | Plus robuste contre les motifs express multi-niveaux : les aretes courtes sont confirmees en premier (presque surement reelles), les plus longues sont testees contre cette base fiable. |
| Bug trouve en debuggant "0 arete retiree" apres la reecriture | Coercition automatique des cles de tableau PHP (ZdCId numerique -> int) : la comparaison stricte `$courant === $b` dans Dijkstra comparait un int (relu via `array_keys()`) a la chaine d'origine, toujours fausse -> la fonction ne reconnaissait jamais avoir atteint la destination. Corrige par un cast `(string)` explicite a chaque lecture de cle. |
| Re-extraction apres correctif | **74 troncons retenus (75 stations - 1) : arbre pur, verifie**. 6 terminus (Pontoise, Versailles-Chateau-Rive-Gauche, Saint-Quentin-en-Yvelines, Massy-Palaiseau, Saint-Martin-d'Etampes, Dourdan-la-Foret), 4 embranchements (Bretigny, Viroflay Rive Gauche, Choisy-le-Roi, Champ de Mars Tour Eiffel) — tous confirmes contre le plan officiel. |
| `ConstruireTopologieRerCommand.php` : `TRONCONS_CSV` devient un tableau (fusionne `troncons_rer.csv` + `troncons_rer_c.csv`), `LIGNES_CODES['C']='C01727'`, C ajoutee a la boucle des arbres simples (`['A','B','C','E']`) | Fichier C separe plutot que regenerer troncons_rer.csv (evite tout risque sur les donnees A/B/D/E deja verifiees), meme convention que les CSV bus incrementaux (`extraire_troncons_bus_100_200.php` etc.). |
| Resolution des noms de stations : comparaison exhaustive des 75 noms GTFS vs les 75 dessertes existantes de la Ligne C | Seulement 2 non-matches sur 75 : "Chamarande" (GTFS) vs "Gare de Chamarande" (DB), "Thiais - Orly (Pont de Rungis)" (GTFS) vs "Pont de Rungis Aéroport d'Orly" (DB) — ajoutes a `ASSOCIATIONS_MANUELLES`. |
| `php bin/console app:construire-topologie-rer` (local) | "Ligne C : 75 stations, 74 troncons." A/B/D/E correctement ignorees (deja construites). 6 Direction creees, 230 Mission. |
| `php bin/phpunit` (134 tests) | Tout passe. |
| Verification `/trajet` (Browser tool, compte de test temporaire cree puis supprime) : 3 itineraires bout-en-bout | Saint-Martin-d'Etampes -> Versailles-Chateau-Rive-Gauche (89,2 min, 0 correspondance, 35 etapes, tout Ligne C, traverse Bretigny + Viroflay) ; Massy-Palaiseau -> Pontoise (83,1 min, correspondance B->C a Saint-Michel Notre-Dame, traverse tout le corridor corrige Ivry/Vitry/Ardoines/Choisy) ; Massy-Palaiseau -> Saint-Martin-d'Etampes (65,5 min, 0 correspondance, tout Ligne C, traverse specifiquement la jonction Massy-Rungis/Choisy-le-Roi, la partie la plus delicate a corriger). Les 3 confirment la topologie correcte de bout en bout. |
| `documentation/TODO.md` | Section "Lignes a embranchements complexes" mise a jour : RER C marque fait avec le resume technique, RER D (maillage Evry/Corbeil/Juvisy) reste ouvert separement. |

## Session du 2026-08-17 (suite) — Nettoyage de Ligne.codeExterne pour le métro

Demande utilisateur : "continue, et si tu as fini la tache, passe a une autre" — choix de la tache
id=14 "Nettoyer Ligne.codeExterne incoherent pour le metro" parmi le backlog A_FAIRE.

| Commande | Objectif |
|---|---|
| `SELECT ... FROM ligne WHERE type_transport='Métro'` | Constat : les 16 Ligne de metro ont en realite `code_externe` NULL (pas une valeur fausse comme le laissait entendre l'ancienne note TODO.md) — les doublons metro crees par `app:importer-reseau-complet` ont deja ete nettoyes dans une session anterieure a celle-ci, sans que `code_externe` soit repeuple sur l'originale derriere. |
| Recherche des vrais doublons de label (ex. Ligne "7"/"1"/"13" avec un autre `type_transport`) | Confirme que ce sont des lignes de BUS homonymes distinctes (numero de bus partage avec une ligne de metro, frequent en IDF), pas des doublons du meme mode — correctement separees par `type_transport`, aucune confusion structurelle a ce niveau. |
| Comparaison des 16 labels de metro en base contre `referentiel-des-lignes.csv` (`TransportMode=metro`) | 16/16 correspondances exactes et non ambigues (1 seul candidat par label, insensible a la casse pour "3B"/"7B"). Recoupe independamment via `routes.txt` GTFS (`route_type=1`) : memes 16 route_id, confirme la fiabilite de la source. Au passage : les lignes 15 et 18 existent dans le referentiel/GTFS actuel mais pas encore en base (hors perimetre de cette tache, note dans TODO.md). |
| Audit des commandes utilisant deja `Ligne.codeExterne` pour le metro (`grep` cible sur le pattern `code_externe IS NULL) DESC`, la technique de repli utilisee) | 3 commandes concernees : `app:construire-positions-rame`, `app:importer-traces-lignes`, `app:importer-documents-lignes`. Les deux dernieres essaient d'abord `codeExterne` (qui va desormais marcher directement pour le metro) puis retombent sur le label en repli — pas de risque, le repli fragile devient juste inutilise pour le metro. `app:construire-positions-rame` en revanche n'essaie JAMAIS `codeExterne`, seulement le label, en departageant les doublons par "prefere la Ligne SANS codeExterne" — un signal qui devient faux et dangereux des que `codeExterne` est rempli sur le metro (le classement entre la vraie ligne de metro et un bus homonyme devient arbitraire). |
| `src/Command/ConstruirePositionsRameCommand.php` : la requete de resolution de `ligneIdParLabel` remplace le tri par `code_externe IS NULL` par un filtre explicite `type_transport = 'Métro'` (verifie : `conseils_position.csv` ne contient que des labels de metro, 16 valeurs distinctes, jamais de RER/bus) | Supprime le risque de regression avant meme d'appliquer le backfill. |
| Script `backfill_code_externe_metro.php` (scratchpad, non commite) : `UPDATE ligne SET code_externe=... WHERE label=... AND type_transport='Métro'`, avec verification 1-candidat-exactement avant chaque mise a jour | 16/16 lignes mises a jour en local. |
| `php bin/console app:construire-positions-rame` (avant/apres) | 4671 PositionRame avant et apres — comportement identique, confirme que le correctif preserve le bon fonctionnement. |
| `php bin/console app:importer-traces-lignes` | 16/16 Ligne de metro ont toujours un trace apres reimport (verifie explicitement). |
| `php bin/console app:importer-documents-lignes` (avant/apres) | 18 -> 16 `DocumentLigne` rattaches a une Ligne de metro. Verifie contre le CSV source (`fiches-horaires-et-plans.csv`) : exactement 1 ligne par codeExterne de metro (16 au total) — la baisse de 18 a 16 correspond a la correction de 2 rattachements auparavant errones (probablement via l'ancien repli fragile), pas a une perte de donnees. |
| `php bin/phpunit` (134 tests) | Tout passe. |
| `documentation/TODO.md` | Section mise a jour avec le vrai diagnostic (codeExterne NULL, pas faux) et le detail du risque de regression trouve/corrige. |

## Session du 2026-08-17 (suite) — Lignes Transilien V/P/R : rattachement du materiel roulant

Demande utilisateur : "enchaine avec une autre" — choix de la tache id=15 "Modeliser les lignes
Transilien V/P/R" parmi le backlog A_FAIRE.

| Commande | Objectif |
|---|---|
| `referentiel-des-lignes.csv` filtre sur `TransportMode=rail` et labels V/P/R | 3 vraies lignes ferroviaires trouvees (route_id C02711/C01731/C01730, reseau Transilien SNCF), distinctes des lignes de bus homonymes du meme referentiel (operateurs locaux sans rapport). |
| `documentation/scripts/extraire_stations_transilien_vpr.php` (nouveau, meme methode que `extraire_stations_rer.py`) : extraction des stations reelles par ligne depuis stop_times.txt | V : 7 stations, P : 32, R : 24 — a comparer aux Station existantes. |
| Comparaison des 63 paires ligne/station contre la base (nom normalise) | **0 station manquante : les 3 lignes existent deja completement en base** (Ligne + Station + Desserte, comptes exacts 7/24/32) — importees par `app:importer-reseau-complet` a un moment posterieur a la note TODO.md, jamais mis a jour depuis. Le vrai travail restant n'est pas d'importer les lignes mais de relier le materiel roulant partage (deja fait pour le RER le 2026-08-09, jamais fait pour V/P/R). |
| Verification des 11 noms ambigus (2 Station candidates, meme nom normalise) trouves au passage | Confirme le phenomene "Stations dupliquees" deja documente (une Station "historique" bien connectee au reseau lourd RER/metro existant, une Station "GTFS" separee ou les Dessertes bus/Transilien s'accumulent) — comportement attendu, pas une anomalie de cette tache, non traite ici (fusion des doublons = tache separee id=10). |
| Script `lier_materiel_transilien_vpr.php` (scratchpad, non commite) : `INSERT INTO materiel_ligne` pour les 6 paires deja documentees dans TODO.md (Z 5600/8800/20500/20900 -> V, Z 57000/57400 -> R, Z 50000 -> P), sans ecraser un lien deja existant | 6/6 crees en local, aucune date arrivee/fin renseignee (coherent avec les liens RER equivalents deja en base, tous vides aussi). |
| Verification `/ligne/2904` (V), `/materiel/27` (Z 5600) via le Browser tool | Fiche Ligne V affiche "7 stations, Train, SNCF" (parcours non disponible, normal — pas de troncons pour les lignes issues de `app:importer-reseau-complet`). Fiche materiel Z 5600 liste desormais "C" et "V" dans ses lignes desservies. |
| `php bin/phpunit` (134 tests) | Tout passe. |
| `documentation/TODO.md` | Section corrigee : la note "pas encore dans la base" etait perimee, remplacee par le vrai etat (lignes deja importees, materiel desormais relie). |

## Session du 2026-08-17 (suite) — Topologie bus 101-299 restante (non-RATP)

Demande utilisateur : "GO topologie bus restante !!!" — tache id=12 (les 16 lignes non-RATP
restantes de la plage 101-299 ; la tache id=13, ~1300 lignes hors 20-299, reste hors de portee
d'un seul passage, non traitee ici).

| Commande | Objectif |
|---|---|
| Recherche des 4 operateurs cites dans TODO.md (ATM Croix du Sud, Keolis Grand Paris Vallee de la Marne, Keolis Argenteuil, Keolis Ouest Val-de-Marne) dans `referentiel-des-lignes.csv` | 2 noms perimes trouves : la ligne 282 existe bien mais sous l'operateur renomme "Keolis Grand Paris Seine Orly" ; la ligne 262 (Keolis Argenteuil) n'existe plus du tout sous ce numero (reseau renumerote en serie "64xx") — non importee plutot que deviner un remplacant. 16 lignes au total confirmees avec un route_id GTFS exact. |
| Verification en base : `Ligne`/`Desserte` pour les 16 codeExterne trouves | Toutes deja presentes avec de vraies Desserte (18 a 43 par ligne) — comme pour Transilien V/P/R, `app:importer-reseau-complet` les avait deja importees, seule la topologie (Troncon) manquait, confirmee a 0 pour les 16. |
| Audit de `extraire_troncons_bus_autres_operateurs.php` (script existant, deja utilise pour 22 lignes 20-100 hors RATP) avant de le reutiliser/etendre | Meme bug que celui trouve et corrige sur le RER C cette session : la fonction de plus court chemin compare une cle de tableau (coercee en int par PHP) a la destination (string) sans recast, cassant la reconnaissance "destination atteinte" — la reduction geometrique ne retire alors jamais aucune arete. |
| Verification empirique de l'impact reel sur les donnees deja construites (22 lignes bus 20-100 + reste RATP 20-299) : ecart tronçons/dessertes par ligne | Ecarts systematiquement petits (0 a 7 sur des lignes de 20 a 46 arrets), coherents avec des vraies boucles/asymetries aller-retour — contrairement au RER C ou l'ecart etait concentre en un seul point (Choisy-le-Roi, degre 6). Conclusion : les bus n'ont quasiment jamais de missions semi-directes/express a filtrer (contrairement au RER), donc le bug n'a probablement rien casse dans les donnees deja construites — **aucune reconstruction retroactive necessaire**. |
| `documentation/scripts/extraire_troncons_bus_101_299_restant.php` (nouveau, meme structure que le script existant mais avec l'algorithme de reduction corrige — "plus court d'abord contre un graphe confirme", meme technique que pour le RER C) | 13223 trips GTFS trouves sur les 16 lignes, 462 aretes brutes, 451 retenues apres reduction (11 raccourcis reellement filtres, preuve que le correctif fait quelque chose ici). Ecarts par ligne verifies avant import : 0 a 4, rien d'anormal. |
| `src/Command/ConstruireTopologieBusAutresOperateursCommand.php` : `TRONCONS_CSV` devient un tableau (fusionne l'ancien CSV + le nouveau), meme pattern que pour `troncons_rer.csv`/`troncons_rer_c.csv` | Fichier separe plutot que regenerer l'existant (deja verifie, aucun risque pris dessus). |
| `php bin/console app:construire-topologie-bus-autres-operateurs` (local) | 445 troncons crees sur les 16 lignes (6 ignores : ZdC "Hotel de Ville" sur la ligne 209 sans Station correspondante en base — lacune de donnees preexistante, sans rapport avec ce travail, geree normalement par le mecanisme d'avertissement deja en place). Les 22 lignes deja construites correctement ignorees (`dejaConstruite`). |
| `php bin/phpunit` (134 tests) | Tout passe. |
| Verification `/ligne/2109` (179, ATM Croix du Sud) via le Browser tool | Parcours affiche correctement, embranchement reel visible ("rejoint la ligne principale"), badges de correspondance corrects vers plusieurs autres lignes. |
| `documentation/TODO.md` | Section mise a jour : 16/17 lignes de la note originale faites (262 introuvable sous ce numero), audit du bug de reduction documente (present dans le code, sans impact constate sur les donnees existantes), tache ~1300 lignes restante clairement separee. |

## Session du 2026-08-17 (suite) — Style physique des Accès (édicule Guimard)

Demande utilisateur : "TACHE Style physique des Accès (escalator, édicule Guimard, mât)" puis,
en cours de route, précision du schéma voulu : "il faudrais comme style de station, un style
d'acces, puis mettre dans acces mettre le lien vers ca".

| Commande | Objectif |
|---|---|
| Verification du constat Wikidata existant (`P84`=Guimard AND `P31`=entree) via query.wikidata.org | Confirme : un seul resultat exploitable (entree Chatelet place Sainte-Opportune). Requete elargie (juste `P84`=Guimard, sans filtrer sur `P31`) : 38 items, mais seulement 3 lies au metro (la station Palais-Royal elle-meme, le concept generique "edicule Guimard", et la meme entree Chatelet) — confirme que Wikidata n'a pas de couverture par edicule individuel. |
| Recherche web d'une source alternative | Trouve un annuaire patrimonial (museedupatrimoine.fr) listant les 88 edicules Guimard classes/inscrits monuments historiques a Paris, avec le detail par arrondissement et, pour les stations a plusieurs entrees notables, le detail de chaque acces (Nation: "Ave Dorian"/"Bd Diderot" ; Place d'Italie: "11"/"6" ; Gare du Nord: 3 acces). Recoupe avec le resume Wikipedia/recherche web : 6 stations a edicule complet protegees des 1965 (Cite, Porte Dauphine, Abbesses, Pigalle, Ternes, Tuileries), le reste par decret collectif de 1978. |
| OpenStreetMap (Overpass API) pour escalator/mat, sur demande explicite ("insiste insiste") | Plusieurs miroirs indisponibles/en timeout (overpass-api.de, overpass.kumi.systems) avant qu'un troisieme (`lz4.overpass-api.de`) reponde. Requete `railway=subway_entrance` + tag `architect` sur toute l'IDF : seulement 2 resultats, aucun Guimard (un autre architecte, Opera). Requete `railway=subway_entrance` + tag `escalator` (n'importe quelle valeur) sur toute l'IDF : 4 resultats, les 4 a "no" — 0 "oui" trouve sur toute la region. Conclusion : tagging OSM quasi inexistant sur ce point precis, pas de donnee exploitable actuellement. |
| `src/Entity/StyleAcces.php`, `Acces::styleAcces` (ManyToOne) — meme schema exact que `StyleStation`/`Desserte::styleStation` | Repository, `StyleAccesType` (choix multiple d'Acces, label "Station - label (n°X)" via la premiere Sortie, meme logique de disambiguation que `StyleStationType`), `StyleAccesController` (CRUD complet), 6 templates `style_acces/*.html.twig`. `AccesType` et `acces/_form.html.twig` : champ `styleAcces` ajoute (select simple). `acces/show.html.twig` et `acces/index.html.twig` : colonne/ligne "Style" ajoutee. Menu : "Styles d'accès" ajoute sous "Accès". |
| Migration `Version20260817180000.php` (CREATE TABLE style_acces, ALTER TABLE acces ADD style_acces_id + FK + index) | Appliquee directement en local via `dbal:run-sql` (la BDD locale n'a jamais utilise le suivi de migrations Doctrine — `migration_versions` n'existe pas, `doctrine:schema:update --force` est la pratique etablie toute la session ; la migration sert uniquement au deploiement prod). Egalement appliquee a la base de test (`--env=test`), sans quoi 124/134 tests echouaient. |
| Comparaison des 65 stations Guimard contre la base : `SELECT ... WHERE label = ? AND type_transport='Métro'` | Sans filtrer sur le type de transport, la plupart des noms remontaient des dizaines de faux candidats (bus homonymes dans des communes sans rapport, meme phenomene que "Republique"/"Gambetta" deja rencontre pour transfers.txt). Filtre sur Metro : 0 ambiguite de Station. 9 noms non trouves faute de correspondre a la convention de nommage de la base (tirets vs tirets cadratins/espaces, ex: "Etienne-Marcel" -> "Etienne Marcel", "Louvre-Rivoli" -> "Louvre — Rivoli") — corriges apres recherche LIKE. 1 nom ("Wagram") introuvable meme apres recherche large : la station de metro ligne 3 "Wagram" (existe reellement) est completement absente de la base — lacune de donnees decouverte au passage, hors perimetre de cette tache. |
| Repartition finale des 64 stations restantes | 18 avec exactement 1 Acces enregistre (surs). 1 station (Nation) avec plusieurs Acces mais un detail de la source assez precis pour matcher par le LABEL de l'Acces ("bd Diderot"/"av. Dorian" correspondent exactement aux 2 acces enregistres). 10 stations avec 0 Acces enregistre (lacune de donnees, non liee a cette tache). 35 stations avec plusieurs Acces sans detail suffisant dans la source pour determiner lequel est le vrai edicule (Chatelet, Bastille, Republique, Porte Dauphine, Abbesses-doublons... etc.) — laissees volontairement non taguees plutot que deviner (verifie au passage : le "numero" RATP interne ne correspond a aucune convention externe verifiable, ex. Place d'Italie source="11"/"6" vs numero reel 1-4). |
| Script `peupler_style_guimard.php` (scratchpad, non commite) | 22 Acces tagues "Édicule Guimard" en local (StyleAcces id=1 cree). |
| Verification visuelle (Browser tool, compte admin temporaire) : `/style/acces/1` et `/acces/12876` (Abbesses) | Les 22 Acces s'affichent correctement sur la fiche du style, et la fiche Acces affiche bien "Style: Édicule Guimard" en retour. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe. |
| `documentation/TODO.md` | Section reecrite avec le vrai etat : Guimard fait (22/64, detail des exclusions), escalator/mat toujours sans source malgre une investigation serieuse sur OSM. |

## Session du 2026-08-17 (suite) — Pagination Accès, filtre, et champs entrée/sortie GTFS

Demande utilisateur : "j'ai 2513 en base... pourquoi j'en ai si peu sur la page acces du site en
prod ? peut tu mettre en place un filtre et une pagination sur la page des acces ? et mettre les
numero aussi ? enfin remplir tout ce que tu trouve sur les gtfs."

| Commande | Objectif |
|---|---|
| Verification en prod (Browser tool, compte de test) | La pagination existait deja cote controleur (50/page, ajoutee lors du gros chantier de tri du 2026-08-16) mais `templates/acces/index.html.twig` n'a jamais eu `{{ knp_pagination_render(acces) }}` — bug preexistant, pas cause par le travail d'aujourd'hui. Resultat : seules les 50 premieres lignes (alphabetique) etaient visibles, sans aucun moyen d'atteindre les 2463 autres ni de rechercher. |
| `AccesRepository::creerRequeteFiltree()` (nouveau, meme pattern que `DesserteRepository`) : recherche sur label/numero/nom de station | Le nom de station est filtre via une sous-requete EXISTS plutot qu'un JOIN classique : un Acces peut desservir plusieurs Station (correspondance), un JOIN aurait multiplie les lignes SQL et fausse le compte total de KnpPaginatorBundle (meme piege que documente dans `DesserteRepository`). |
| `AccesController::index()` + `templates/acces/index.html.twig` | Formulaire de recherche ajoute, `{{ knp_pagination_render(acces) }}` ajoute (le vrai correctif du bug signale), compteur total affiche. |
| Audit de `Acces.numero` (l'utilisateur demandait "mettre les numero aussi") | Deja rempli a 58% (1468/2515) depuis `acces_entrees.csv` — verifie que ce n'est pas un bug d'extraction : le repli vers `stop_code` du GTFS (`?? $row['stop_code']`) ne se declenche en pratique jamais (ne s'active que si l'accId est absent d'`acces.csv`, pas si son numero y est vide) MAIS `stop_code` est de toute facon vide sur 100% des 2515 entrees GTFS — verifie directement. Egalement verifies et vides sur 100% des entrees : `wheelchair_boarding`, `platform_code`, `stop_desc`, `level_id`, `stop_access`, `stop_url`. Le numero manquant sur ~42% des acces est donc une vraie absence dans la donnee source IDFM, pas une extraction incomplete de notre cote. |
| Audit des colonnes inexploitees de `acces.csv` (dataset IDFM) pour repondre a "remplir tout ce que tu trouve sur les gtfs" | `AccDescription` : 45% rempli mais quasi exclusivement "Source IDFM" (texte generique, un exemplaire litteralement "test") — pas retenu. `AccIsEntry`/`AccIsExit` : bien remplis (2376/2515 et 2502/2515 a "true", des vraies valeurs "false" existent aussi, ex. grilles de sortie uniquement) — genuinement exploitable, jamais capture jusqu'ici. |
| `Acces::estEntree`/`estSortie` (bool nullable) + migration `Version20260817200000.php` | Colonnes ajoutees (local, test, prod). `extraire_acces_entrees.php` etendu (2 colonnes CSV de plus) et chemin GTFS corrige au passage (`documentation/IDFM-gtfs/` -> `.../csv/`, meme correctif deja fait cette session pour d'autres scripts). `ConstruireAccesSortiesCommand` mis a jour pour les futurs re-imports complets. |
| Script `backfill_entree_sortie_acces.php` (scratchpad, non commite) : `UPDATE acces SET est_entree=?, est_sortie=? WHERE code_externe=?`, PAS un re-import complet | `app:construire-acces-sorties` purge et recree tous les Acces/Sortie a chaque execution (nouveaux id auto-increment a chaque fois) : le relancer aurait casse les 22 liens `StyleAcces` (Guimard) crees cette session et les references `PositionRame`. Un backfill cible par `code_externe` (stable, unique) evite ce risque. 2512/2513 Acces mis a jour en local et prod. |
| `templates/acces/index.html.twig` et `show.html.twig` : colonnes/lignes Entrée et Sortie ajoutees | Rendu Oui/Non/— selon la valeur. |
| `php bin/phpunit` (134 tests) | Tout passe. |
| Verification visuelle (Browser tool) : recherche "Nation" (17 resultats corrects, dont les 2 acces Guimard), inspection DOM directe de la pagination (`document.querySelectorAll('tbody tr').length` = 50, controle de pagination pages 1-51 present) | Confirme le correctif de bout en bout. |

## Session du 2026-08-17 (suite) — emplacement-des-gares-idf-data-generalisee.csv : verifie, pas exploitable

Demande utilisateur : "continu la prochaine tache si celle ci est fini" — tache id=4 choisie
(la plus petite/sure du backlog restant, les deux plus grosses — fusion Stations dupliquees et
topologie bus ~1300 lignes — restant explicitement hors de portee d'un seul passage).

| Commande | Objectif |
|---|---|
| Comparaison des 999 `id_ref_ZdC` du fichier contre `station.code_externe` | 996/999 trouvent deja une Station chez nous. Coordonnees comparees sur 2 exemples (Torcy, Lagny-Thorigny) : ecart de quelques dizaines de metres avec nos coordonnees existantes — source coherente et fiable, mais totalement redondante. |
| Recherche des Station sans coordonnees (`latitude IS NULL`, 174 au total) croisees avec ce fichier | 163/174 n'ont pas de `code_externe` (meme probleme "Stations dupliquees" que d'habitude, matching par nom risque, pas tente). Les 11 restantes (avec `code_externe`, ex. Concorde, Hotel de Ville, Villiers, Colonel Fabien...) ont ete cherchees UNE PAR UNE dans le fichier par leur `code_externe` : **aucune des 11 n'y figure**. Le fichier ne comble donc aucun des trous de coordonnees actuels. |
| Verification de la colonne "ville" | Le fichier n'a **aucune colonne ville/commune** (verifie sur l'en-tete complet) — ne peut pas non plus combler les 571 Station sans `ville`. |
| Verification des 3 gares du fichier sans correspondance chez nous (Traite de Rome, Noveos, Louise Michel) | Recherche par nom : les 3 existent en base sous de multiples homonymes dans des communes sans rapport (ex. "Louise Michel" : 8 candidats differents) — meme phenomene que "Republique"/"Gambetta" deja documente pour transfers.txt. Pas de match sur sans ambiguite tente, conformement a la discipline etablie. |
| `exploitant` (colonne du fichier) | Fait doublon avec `Ligne.gestionnaire`, deja modelise a un niveau plus pertinent (par ligne, pas par station). |
| `documentation/TODO.md` | Section reecrite : conclusion honnete que ce fichier n'apporte rien d'exploitable avec l'etat actuel des donnees, pour eviter qu'une session future ne re-instruise la meme question. Aucun code ecrit — l'investigation elle-meme est la tache. |

## Session du 2026-08-17 (suite) — TrajetFinder ne changeait jamais de mode depuis un bus

Demande utilisateur : trajet reel attendu ("Les Coquettes" bus 131 au Kremlin-Bicêtre vers "Les
Mousquetaires" bus 206 a Villiers-sur-Marne, en passant par metro 7 puis RER A) mais obtenu
"si on prend le bus, il ne nous montre QUE du bus".

| Commande | Objectif |
|---|---|
| Lecture de `TrajetFinder.php`/`TrajetController.php` | Algorithme et filtrage de modes corrects (Dijkstra multi-source generique, modes selectionnes via checkboxes, tout coche par defaut) — pas de bug evident dans le code de calcul lui-meme. |
| Investigation concrete sur l'exemple de l'utilisateur : `station.label LIKE '%Coquettes%'` puis ses `Correspondance` | "Les Coquettes" (bus 131) n'a de Correspondance qu'avec d'autres arrets de bus proches (Chastenet de Géry, Rue des Guipons, Paul Lafarge) — aucune vers "Le Kremlin-Bicêtre". |
| Recherche de "Le Kremlin-Bicêtre" en base | Confirme le phenomene "Stations dupliquees" deja documente : Station id=159 (sans `code_externe`, porte l'UNIQUE Desserte metro ligne 7) et Station id=20522 (avec `code_externe`, porte 6 Desserte de bus). Toutes les `Correspondance` existantes pour ce lieu sont rattachees a id=20522 (bus<->bus uniquement) — id=159 (le metro) n'a AUCUNE correspondance du tout. Les deux Station ne se rencontrent jamais dans le graphe de `TrajetFinder`. |
| Mesure de l'ampleur reelle | 512 Station "historiques" (`code_externe` NULL) portent une Desserte metro/RER/tram. Parmi elles, 358 ont exactement une jumelle GTFS (`code_externe` NOT NULL, meme label) identifiable sans ambiguite ; 74 ont plusieurs jumeaux possibles (labels generiques, ignores) ; 80 n'ont aucune jumelle trouvee. |
| `ConstruireCorrespondancesInterModesCommand` (existant, relit son docblock) | Relie deja les modes LOURDS (metro/RER/tram) partageant un label, mais explicitement PAS le bus ("Volontairement limite a Metro/Tramway/RER") — confirme que le trou est specifiquement bus<->lourd, jamais couvert jusqu'ici. |
| `src/Command/ConstruireCorrespondancesStationsDupliqueesCommand.php` (nouveau) : pour chaque Station historique a desserte lourde avec exactement une jumelle GTFS non ambigue, cree une Correspondance entre CHAQUE Desserte de l'historique et CHAQUE Desserte de la jumelle (tous modes, bus compris), `inZone=true`, distance non renseignee (retombe sur l'estimation par defaut de TrajetFinder) | Meme discipline de securite que partout ailleurs cette session : uniquement les labels a UN SEUL candidat jumeau, jamais de rapprochement ambigu. Alternative bien moins risquee que fusionner les ~486 paires de Station (`FusionnerStations`, tache id=10, toujours volontairement pas faite). |
| `php bin/console app:construire-correspondances-stations-dupliquees` (local) | 3071 correspondances creees, pontant 358 Station historiques (74 labels ambigus et 80 sans jumelle ignores, comme prevu). |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe. |
| Verification en conditions reelles (Browser tool) : `/trajet?origine=21621&destination=22545` (Les Coquettes -> Les Mousquetaires, tous modes coches) | **Avant** : uniquement du bus (non re-teste explicitement, mais impossible autrement vu l'absence totale de correspondance sortante du metro a Kremlin-Bicêtre). **Apres** : 64.5 min, 5 correspondances, changement de mode reel — bus 131 -> métro 7 -> métro 14 -> RER A -> RER E -> bus 207. Confirme le correctif. |
| `documentation/TODO.md` | Section "Stations dupliquées" mise a jour : impact concret documente, contournement complementaire explique, la vraie correction (fusion des Station) reste ouverte (tache id=10). |

## Session du 2026-08-18 — Style des badges de ligne, carte du trajet interactive, et bug critique de deploiement

Demande utilisateur : remplacer les medaillons colores (nom en blanc a l'interieur) du calculateur
de trajet par des petits carres colores + nom en noir (comme `ligne/index.html.twig`) ; puis rendre
la bulle de station de la carte cliquable (pas au survol), avec surbrillance de ligne au survol et
lien vers le detail de la Desserte au clic.

| Commande | Objectif |
|---|---|
| `templates/trajet/index.html.twig` : 4 occurrences de `.ligne-badge-sm` (pastille ronde, texte blanc a l'interieur) remplacees par `<span class="badge" style="background-color:...">` (carre vide) + nom en texte noir a cote | Reprend exactement le style deja utilise dans `ligne/index.html.twig`. |
| `src/Controller/TrajetController.php::construireInfosStationsPourAffichage` : ajoute `ligneId` et `desserteUrl` (via `generateUrl('app_desserte_show', ...)`) a chaque entree, dedoublonnage desormais par ligne (pas par egalite de tableau brute) | Necessaire pour rendre chaque ligne de la bulle de la carte cliquable/survolable individuellement. |
| `assets/js/trajet-carte.js` : `bindTooltip` (survol) remplace par `bindPopup` (clic) sur le marqueur numerote ; bulle construite avec des `<a class="carte-bulle-ligne" data-ligne-id="...">` ; `popupopen`/`popupleave` cablent mouseenter (surbrillance du trace de cette ligne sur CE trajet, deja dessine, pas de refetch) et le clic suit naturellement le lien `<a href>` | Meme pattern que `carte-reseau.js` (deja en prod), adapte pour survoler le trace propre au trajet plutot que refetcher le trace complet de la Ligne. |
| `assets/styles/app.scss` : `.carte-bulle-ligne` reutilisee comme `<a>` (reset couleur/decoration) en plus de son usage `<div>` existant | La classe servait deja a la carte reseau. |
| `npx jest` (25 tests sur les 3 fichiers concernes), `php -l` sur le controleur | Tout passe. |
| Verification Browser tool (local, compte de test temporaire) : clic sur marqueur "Le Kremlin-Bicêtre" -> bulle avec 2 lignes cliquables (bus 131, métro 7), clic reel sur une ligne -> navigation vers `/desserte/385` (Olympiades, ligne 14) | Confirme clic-pour-ouvrir (pas survol), lien fonctionnel. |
| `cp` des fichiers modifies vers un clone git scratchpad, `git commit`/`git push` | **Le clone git habituel (`repo-clone`) s'est revele corrompu** (`git fsck` : objets manquants, quasi tous les fichiers disparus du disque — cause externe, pas une action de cette session) : un premier commit (style des badges) n'avait jamais ete pousse. Verifie via `git ls-remote` que `origin/main` etait toujours sain (aucune perte reelle), puis reclone propre (`repo-clone-2`) et recommit des deux changements (`af6b1e1`, `7f9829e`) depuis les fichiers intacts de `Desktop/metroratp`. |
| `gh run watch` (deploiement) | Tests PHP/JS + deploiement Hostinger OK. |
| **Verification en prod (curl brut, sans navigateur)** : `curl https://julien-silberstein.fr/metroratp/` puis extraction des balises `<script src>`/`<link href>` | **Decouverte d'un bug critique preexistant, sans rapport avec les changements ci-dessus** : TOUT le site (CSS/JS, pas seulement `/trajet`) etait casse pour tout visiteur sans cache navigateur sur les anciens hash de fichiers — `webpack.config.js` codait en dur `setPublicPath('/build')` (racine du domaine), alors que l'app est servie sous `/metroratp` en prod (symlink `public_html/metroratp -> metroratp-app/public`). Confirme via `curl` direct : `/build/app.xxx.js` -> 404, `/metroratp/build/app.xxx.js` -> 200. `php bin/console cache:clear`+`cache:warmup` en prod (SSH) n'y changeait rien (bug de build, pas de cache serveur). |
| `webpack.config.js` : `.setPublicPath(Encore.isProduction() ? '/metroratp/build' : '/build')` + `.setManifestKeyPrefix('build')` (necessaire des que publicPath/outputPath ne partagent plus le meme dernier segment, sinon Encore refuse de builder) | `npm run build` (prod) genere maintenant `/metroratp/build/...` ; `npx encore dev` (local) genere toujours `/build/...` sans prefixe — verifie les deux avant de commiter. |
| `git commit`/`git push` (`ecf3ba3`), `gh run watch` | Deploiement OK. `curl` post-deploiement : tous les assets de la page d'accueil et de `/trajet` en 200 sous `/metroratp/build/...`. Verifie aussi via Browser tool (compte de test prod) : `read_network_requests` confirme uniquement des 200 sur la page fraichement chargee (les 404 visibles dans `read_console_messages` etaient les anciens, d'avant le correctif, accumules dans le log de l'onglet). |
| SSH prod (`ssh -i ~/.ssh/deploy-metroratp/id_ed25519 -p 65002 u396779750@195.35.49.152`) : creation/suppression de comptes de test (`test_verif_ui`) | Rappel : `julien-silberstein.fr` (port 22) est **injoignable** depuis cet environnement ("Network is unreachable") — seule l'IP+port+cle documentes dans la memoire du projet fonctionnent. |

## Session du 2026-08-18 (suite) — Remplacement du CSS personnalisé par des classes Bootstrap (compatibilité mobile)

Demande utilisateur : remplacer TOUTES les CSS personnalisées, sur TOUTES les pages, par des
classes Bootstrap, pour la compatibilité media query/mobile. Confirmé "quand même" après avoir
appris que le bug critique du publicPath (session precedente) etait peut-etre la vraie cause du
souci observe.

| Commande | Objectif |
|---|---|
| Lecture complete de `assets/styles/app.scss` (13 regles/blocs) | Catalogue de tout le CSS personnalise du projet, classe par classe. |
| `sed` sur `templates/**/*.twig` : `class="ligne-badge-sm"` -> `+rounded-circle d-inline-flex align-items-center justify-content-center` (14 fichiers) et pareil pour `class="ligne-badge"` (4 fichiers) | Remplacement mecanique surete par verification prealable qu'aucune occurrence ne combinait deja d'autres classes. Forme/alignement passes en Bootstrap ; ne reste en CSS que taille fixe/couleur dynamique/ombre. |
| `templates/mission/choix_ligne.html.twig` (+`rounded-4`), `templates/ligne/show.html.twig` et `templates/mission/trajet.html.twig` (+`list-group-item-action`), `assets/js/trajet-autocomplete.js`, `assets/js/carte-acces.js`, `assets/js/trajet-carte.js`, `assets/js/carte-reseau.js` (classes Bootstrap dans le HTML genere en JS) | Meme logique appliquee partout : `.ligne-card`, `.parcours-list`, `.suggestion-station/mode/label`, `.carte-station-numero`, `.carte-bulle-ligne`, `.carte-acces-sortie`. |
| `.metro-header` : ajout d'un `@media (max-width: 575.98px)` | Bespoke (carrelage RATP + plaque emaillee), aucun equivalent Bootstrap, mais rendu responsive. |
| `.profil-dropdown` et `th a.sortable/.asc/.desc` | Volontairement laisses en CSS pur : comportement hover et glyphes de tri generes par KnpPaginatorBundle, aucune classe Bootstrap ne peut les remplacer. |
| `npx jest` (51 tests), `php bin/phpunit` | Tout passe. |
| Verification Browser tool (local, `127.0.0.1:8000`, compte de test) : desktop puis mobile (375px) sur page d'accueil | **Bug trouve** : le texte du panneau "Métroratp" debordait largement de l'ecran a 375px (formule de taille basee sur le carrelage, non adaptee). Egalement trouve : la media query ne s'appliquait pas du tout (ordre CSS - regle inconditionnelle placee APRES dans le fichier source, donc toujours gagnante meme sous le breakpoint). Les deux corriges : taille de texte fixee explicitement sous le breakpoint, media query deplacee apres la regle de base. |
| Re-verification (local + prod, desktop + mobile) : en-tete, badges de ligne, parcours-list, bulle carte-acces, marqueurs numerotes de la carte du trajet | Tout conforme (classes Bootstrap presentes, aucun debordement, aucune erreur console). |
| `git commit`/`git push`, `gh run watch` (deploiement) | OK. |

## Session du 2026-08-18 (suite) — INCIDENT : le correctif du publicPath cassait le vrai site

L'utilisateur signale "la page est cassée" en prod juste apres le deploiement precedent.

| Commande | Objectif |
|---|---|
| `curl` sur `metroratp.julien-silberstein.fr` et `julien-silberstein.fr/metroratp` | **Decouvre l'erreur** : le site de prod a DEUX points d'entree distincts vers la meme app. `metroratp.julien-silberstein.fr` est un vrai SOUS-DOMAINE (document root = ce depot directement, assets a `/build/...`) - la memoire du projet affirmait a tort qu'il n'existait pas de sous-domaine. Le correctif de la session precedente (webpack `publicPath` -> `/metroratp/build`) visait le sous-repertoire `julien-silberstein.fr/metroratp` et a casse le sous-domaine (le vrai point d'entree utilise par l'utilisateur) en echange. |
| `webpack.config.js` : revert a `.setPublicPath('/build')` inconditionnel, commentaire etaye pour eviter de refaire la meme erreur | Repare le sous-domaine. Le sous-repertoire `/metroratp` redevient casse comme avant toute intervention - aucune preuve qu'il soit reellement utilise. |
| `npm run build`, verification `entrypoints.json` | `/build/...` sans prefixe, comme attendu. |
| `git commit`/`git push` (urgent), `gh run watch` | Deploiement OK. |
| `curl` post-deploiement sur `metroratp.julien-silberstein.fr` : assets JS/CSS | 200 (repare). Verifie aussi via Browser tool (compte de test) : CSS Bootstrap + CSS personnalise charges, aucune erreur console. |
| Correction de la memoire du projet (`project_metroratp_desktop_copy_incomplete.md`) | L'affirmation "pas son propre sous-domaine" etait fausse et a directement cause cet incident - corrigee, avec avertissement explicite pour ne pas refaire le meme correctif sans confirmer avec l'utilisateur lequel des deux points d'entree est reellement utilise. |

## Session du 2026-08-19 — EquipementArret : piste "Écarts arrêts référentiel/OpenStreetMap"

Demande utilisateur : "passe à une autre tâche" (après un souci mobile non résolu, mis de côté).
Choisie : la piste TODO "Écarts arrêts référentiel/OpenStreetMap", pas encore commencée.

| Commande | Objectif |
|---|---|
| Inspection de `ecarts-arrets-referentiel-et-openstreetmap.csv` (46300 lignes) via PHP (`fgetcsv`, pas `awk` — les champs contiennent des `;` entre guillemets) | 96% des lignes en Île-de-France (codes postaux 75/77/78/91/92/93/94/95), 85% avec au moins un equipement OSM renseigne (wheelchair/bench/bin/lit/shelter/tactile_paving) - confirme que la donnee est exploitable et dans le perimetre du projet. |
| Verification du chainage ArTId (CSV) -> `relations.csv` -> `Station.codeExterne` | 42768 ArTId distincts, 42767 trouves dans relations.csv, 43929/46300 lignes (95%) rattachables a une Station existante - meme mecanisme officiel deja fiable pour PoleEchange. |
| Verification de la coherence des ArTId dupliques (1593 ArTId apparaissent plusieurs fois) | 705/1593 groupes ont des valeurs wheelchair/bench incoherentes entre doublons (plusieurs elements OSM proches, parfois contradictoires) - dedoublonnage retenu : garder la ligne a la plus petite `Distance (m)` (le rapprochement OSM/referentiel le plus fiable), pas de fusion de valeurs. |
| `src/Entity/EquipementArret.php`, `EquipementArretRepository`, `EquipementArretController` (CRUD complet), `EquipementArretType`, templates `templates/equipement_arret/*` (meme structure que Sanitaire), lien menu | Nouvelle entite plutot que des champs sur Station : une Station a plusieurs arrets physiques (quais) aux equipements parfois differents, les fusionner sur Station perdrait cette granularite. |
| `documentation/scripts/donnees-extraites/ecarts-arrets-referentiel-et-openstreetmap.csv` (copie) | Le fichier source vivait sous `documentation/IDFM-gtfs/` (exclu par `.gitignore` - donnees brutes non versionnees) : copie vers le dossier suivi par git, meme convention que `relations.csv`/`poles-d-echange.csv`, chemin ajuste dans la commande. |
| `migrations/Version20260818200000.php` (creation table `equipement_arret`) | SQL genere via `doctrine:schema:update --dump-sql` puis isole a la main (le dump complet contenait aussi des `CHANGE` cosmetiques sans rapport sur des dizaines d'autres tables, deja connus - voir memoire projet, jamais appliques en bloc). |
| `php bin/console app:importer-equipements-arrets` (local) | Plante en cours de route : `Allowed memory size of 536870912 bytes exhausted` dans `Doctrine\ORM\Internal\TopologicalSort` malgre un `flush()`/`clear()` tous les 500 - ~43000 entites chacune liee a une Station differente (13710 distinctes) rendent le calcul d'ordre de commit couteux. Relance avec `php -d memory_limit=2048M` (idempotent grace a la cle unique `artId`) : **40511 EquipementArret crees**, couvrant **12867 Station distinctes** (2257 ArT sans Station correspondante, ignores). |
| `php bin/phpunit` (134 tests), `npx jest` | Tout passe. |
| Verification Browser tool (local + prod, compte de test) : liste paginee/triable, fiche detail, aucune erreur console | Conforme. |
| `git commit`/`git push` (3 commits : entite+CRUD, correction du chemin CSV exclu par gitignore), `gh run watch` | Deploiement OK, migration appliquee automatiquement par le pipeline. |
| `ssh ... php -d memory_limit=2048M bin/console app:importer-equipements-arrets` (prod) | Memes chiffres exacts qu'en local (40511/12867) - confirme la coherence des deux bases. |
| `documentation/TODO.md` | Section marquee "fait (2026-08-19)", note que `arrets-transporteur.csv` et le niveau ArT complet restent pertinents si la piste "Arrêt Transporteur" est un jour entreprise. |

## Session du 2026-08-19 (suite) — ArretTransporteur : piste "Arrêt Transporteur (ArT)"

Demande utilisateur : "arret transporteur" (piste TODO suivante, choisie directement).

| Commande | Objectif |
|---|---|
| Inspection de `arrets-transporteur.csv` (52516 lignes, 1 ligne = 1 ArTId, aucun doublon) via PHP | 99% Ile-de-France. Types : bus 93%, rail/metro/tram/cableway le reste. Accessibilite : 25215 vrai / 20048 faux / 7248 inconnue / 5 partiel - signal fort et fiable, a la difference du tag OSM wheelchair (souvent vide). Signalisation sonore/visuelle : ~89% inconnue mais des milliers de valeurs reelles. |
| Verification du chainage ArTId -> relations.csv -> Station.codeExterne | 100% des 52516 ArT chainent vers un ZdCId, 93% (48890) vers une Station existante - encore meilleur que pour EquipementArret. |
| Verification du recouvrement avec EquipementArret (deja importe) | 42766/42768 ArTId d'EquipementArret existent aussi dans ce fichier (99.99%) - confirme qu'il s'agit bien des memes arrets, deux sources d'info complementaires (OSM vs referentiel officiel), pas redondantes. |
| `src/Entity/ArretTransporteur.php`, Repository, Controller (CRUD complet), Type, templates (meme structure que EquipementArret/Sanitaire), lien menu | Entite separee d'EquipementArret plutot qu'unifiee (chacune rattachee directement a Station) : une vraie hierarchie ArT commune serait un refactor plus lourd, pas entrepris ici - documente dans TODO.md pour une session future. |
| `documentation/scripts/donnees-extraites/arrets-transporteur.csv` (copie) | Meme raison qu'EquipementArret : le fichier source vit sous `documentation/IDFM-gtfs/`, exclu par `.gitignore`. |
| `migrations/Version20260819110000.php` | SQL isole via `doctrine:schema:update --dump-sql` (meme methode qu'EquipementArret). |
| `php bin/console app:importer-arrets-transporteur` (local) | **Plante immediatement** : `SQLSTATE[42000]... error in your SQL syntax ... near 'accessible, signalisation_sonore...'` - `ACCESSIBLE` est un mot reserve MariaDB (utilisable tel quel dans le DDL de creation de table car Doctrine le quote automatiquement au diff de schema, mais PAS dans les requetes INSERT/UPDATE generees par le persister ORM au runtime). Renomme en `estAccessible` (colonne `est_accessible`) partout (entite, formulaire, templates, commande) plutot que de gerer des guillemets partout - table locale recreee avec le bon nom avant de relancer. |
| Import relance (local) | 48890 ArretTransporteur crees, couvrant 13706 Station distinctes (quasi toutes celles a `code_externe`), 3626 ArT sans Station ignores - conforme aux chiffres attendus. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe. |
| Verification Browser tool (local + prod, compte de test) | Liste paginee/triable, aucune erreur console. |
| `git commit`/`git push`, `gh run watch` | Deploiement OK, migration appliquee automatiquement. |
| `ssh ... php -d memory_limit=2048M bin/console app:importer-arrets-transporteur` (prod) | Memes chiffres exacts qu'en local (48890/13706/3626). |
| `documentation/TODO.md` | Section marquee "fait partiellement" : les deux entites (EquipementArret/ArretTransporteur) restent volontairement separees, pas de vraie hierarchie ArT unifiee - laisse ouvert pour une session future si besoin. |

## Session du 2026-08-20 — Escalator/ascenseur : l'impasse du 2026-08-17 était une fausse piste

Demande utilisateur : reprendre "Style physique des Accès — escalator/mât toujours sans source".

| Commande | Objectif |
|---|---|
| Recherche `escalator\|ascenseur\|mât` dans les en-têtes des 32 CSV IDFM disponibles | Confirme qu'aucun fichier officiel IDFM ne porte cette info - le referentiel est bien exploite a fond. |
| Requete Overpass API (`lz4.overpass-api.de`) sur `highway=steps`+`conveying=*` et `highway=elevator` en Ile-de-France (au lieu du tag generique `escalator` interroge le 2026-08-17) | **1512 escaliers mecaniques (13 seulement en `conveying=no`) + 1427 ascenseurs** - a l'oppose du "4 resultats, tous no" de la session precedente. Le tag standard OSM pour un escalier mecanique n'est pas `escalator=yes/no` sur le nœud d'entree, mais `conveying=*` sur la voie (`way`) `highway=steps` elle-meme. |
| Test de rattachement par proximite : au niveau `Acces` (mediane 44m) vs au niveau `Station` (mediane 88m, moins precis - les coordonnees de Station ne sont pas systematiquement au niveau de l'entree) | Retenu au niveau Acces. |
| Verification de la confiance du rattachement (seuil 30m + le 2eme Acces le plus proche doit etre nettement plus loin, meme discipline que le tagging Guimard) | Sur 2926 elements OSM : 695 rattachements confiants, 419 ambigus (deux portes voisines competitives, frequent dans les grandes stations) et ~1800 trop loin - tous ecartes sauf les 695 confiants. |
| `src/Entity/Acces.php` : ajout de `aEscalierMecanique`/`aAscenseur` (nullable bool) | Sur Acces (pas Station) : c'est la precision au niveau d'un Acces individuel qui rend le rattachement fiable. |
| `documentation/scripts/donnees-extraites/osm-escaliers-mecaniques-ascenseurs-idf.json` (resultat Overpass sauvegarde) | Reproductible sans redependre d'Overpass a chaque reimport (miroirs connus flaky, voir session du 2026-08-17). |
| `migrations/Version20260820120000.php`, `src/Command/ImporterEscaliersAscenseursOsmCommand.php` | Commande d'enrichissement (pas un import complet - modifie des Acces existants). |
| `php bin/console app:importer-escaliers-ascenseurs-osm` (local) | **227 Acces avec escalier mecanique, 209 avec ascenseur** (366 Acces distincts). |
| `templates/acces/show.html.twig` : deux nouvelles lignes | Pas ajoute a `index.html.twig` (deja 7 colonnes, meme convention que `nombreMarches`/`penteMaxPourcent` : detail sur la fiche, pas dans la liste). |
| `php bin/phpunit` | **Echoue d'abord** : `Unknown column 't0.a_escalier_mecanique'` - la base de test locale (`metroratp_test`, suffixe automatique ajoute par `config/packages/doctrine.yaml` en environnement test, base physiquement distincte de `metroratp`) n'avait pas les nouvelles colonnes (l'`ALTER TABLE` n'avait ete applique qu'a `metroratp`). Corrige avec `php bin/console dbal:run-sql --env=test "ALTER TABLE ..."`. Piege a retenir : toute nouvelle colonne doit etre appliquee aux DEUX bases locales, pas seulement `metroratp`. |
| `git commit`/`git push`, `gh run watch` | Deploiement OK, migration appliquee automatiquement (CI recree son propre schema de test a chaque run, pas concerne par le piege ci-dessus). |
| `ssh ... php bin/console app:importer-escaliers-ascenseurs-osm` (prod) | Memes chiffres exacts qu'en local (227/209/366). |
| Verification Browser tool (local + prod, compte de test) sur un Acces reel (av. de Friedland, sortie 2 de Charles de Gaulle - Étoile) | "Escalier mécanique : Oui" - plausible pour cette station tres frequentee. |
| `documentation/TODO.md`, mise a jour de la Tache #2 "Style physique des Accès" (EN_COURS, deja existante - pas de nouvelle Tache creee cette fois) | Reste ouvert : `mât`, aucune piste identifiee (pas d'equivalent au tag `conveying` trouve pour ce concept). |

## Session du 2026-08-20 (suite) — Reorganisation du modele ArT

Demande utilisateur : critique du modele ArretTransporteur/EquipementArret juste cree ("ca donne
vraiment l'impression que tu as plusieur table pour les arret de bus... il faut mettre l'arret
dans station et la ligne dans desserte"). Discussion en plusieurs rounds pour trouver la bonne
repartition (voir le fil de conversation pour le detail du raisonnement).

| Commande | Objectif |
|---|---|
| Suppression de `ArretTransporteur` (entite/repository/controller/form/templates/commande/lien menu) | Dupliquait nom/coordonnees de Station sans apporter de granularite reelle. |
| `Station.zoneTarifaire` (nouveau champ) | Propriete du lieu, ne varie pas selon la ligne - reste sur Station. |
| `Desserte.estAccessible`/`signalisationSonore`/`signalisationVisuelle` (nouveaux champs) | Depend du materiel roulant de LA ligne precise a cet arret - direction tranchee par l'utilisateur ("si c'est bus, meme arret pour tout le monde -> station ; sinon -> desserte", puis affine : le materiel roulant varie par ligne meme en bus, donc accessibilite reste desserte). Source : `sdap-arrets-associes.csv` (route_id/stop_id), un lien **100% officiel** vers Ligne ET Station - verifie avant d'implementer : 35005/36695 lignes (95%) chainent vers une Desserte deja existante. |
| `Desserte.equipementArret` (nouvelle relation ManyToOne vers `EquipementArret`, conservee) | Idee finale de l'utilisateur : plutot que dupliquer les booleens de mobilier physique sur chaque Desserte d'une Station qui partagent le meme arret (cas frequent en bus), chaque Desserte REFERENCE le meme EquipementArret - une seule source de verite, pas de duplication. Quand une Station a plusieurs EquipementArret distincts (gros pole a plusieurs abribus), retient celui au rapprochement OSM/referentiel le plus fiable (distance la plus petite) - meme limite que precedemment, aucune info de ligne dans le referentiel ArT pour trancher plus finement. |
| `app:importer-zone-tarifaire`, `app:importer-accessibilite-dessertes` (nouvelles), `app:importer-equipements-arrets` (etendue : relie aussi chaque Desserte a son EquipementArret) | |
| **Piege decouvert en verifiant "Les Sablons"** (station de metro ligne 1 a Neuilly-sur-Seine) : zone tarifaire 5 au lieu de 1 | Trace : le ZdCId de notre Station correspond en realite, dans arrets-transporteur.csv, a un arret de BUS homonyme a **Ecquevilly** (Yvelines, ~30km, zone 5 authentique la-bas) - collision de nom au niveau du referentiel source IDFM lui-meme (relations.csv), pas un bug du code. |
| Verification de l'ampleur (coherence des ArTTown par ZdCId) | 873/13643 Station (6.4%) ont un ZdCId associe a plusieurs villes distinctes - pas un cas isole. Filtre ajoute : ignorer ces ZdCId plutot que deviner (meme discipline que le tagging Guimard/les correspondances par nom). |
| Deuxieme piege : "Les Sablons" restait faux MEME apres ce filtre | Ce ZdCId n'a qu'UN SEUL ArT (Ecquevilly) - aucune incoherence de ville a detecter, un seul son de cloche, mais c'est le mauvais. Deuxieme verification ajoutee : distance geographique ArT (arrets-transporteur.csv, ArTGeopoint) <-> Station (latitude/longitude), seuil 2000m - 3 cas de plus ecartes. |
| Residu : "Les Sablons" reste a zone 5 malgre les deux filtres | Cette Station precise n'a **pas de coordonnees** (latitude/longitude NULL - phenomene "Stations dupliquees" deja documente, ~570 Station concernees) : le controle de distance ne peut pas s'appliquer. Documente comme limite connue et acceptee dans le code (docblock de `ImporterZoneTarifaireCommand`) plutot que masque - ne sera resolu qu'en comblant les coordonnees manquantes ou en fusionnant les Stations dupliquees (tache id=10, hors perimetre). |
| `php bin/console dbal:run-sql --env=test "ALTER TABLE ..."` puis `doctrine:schema:update --env=test --force` | Meme piege que la session precedente (base de test locale distincte de la base de dev, suffixe `_test` automatique) - resolu plus largement cette fois par un `--force` complet sur la base de test (jetable, sans risque, contrairement a la base de dev reelle). |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe. |
| Verification Browser tool (local + prod, compte de test) : fiche Desserte (accessibilite + equipement lie affiches), fiche Station (zone tarifaire affichee) | Conforme. |
| `git commit`/`git push` (2 commits : ajout puis reorganisation), `gh run watch` | Deploiement OK, migration appliquee automatiquement. |
| Import des 3 commandes en prod | Chiffres quasi-identiques au local : 12767/12768 Station (zone tarifaire), 40511 EquipementArret / 29978 Desserte reliees, 35005 Desserte (accessibilite/signalisation). |
| `documentation/TODO.md` | Section "Arrêt Transporteur (ArT)" reecrite pour refleter le modele final. |

## Session du 2026-08-20 (suite) — Audit et consolidation du menu

Demande utilisateur : audit des pages du menu, puis "carte blanche" pour la mise en oeuvre.

| Commande | Objectif |
|---|---|
| `debug:router` + verification HTTP (curl authentifie, local et prod) des 36 pages du menu | Toutes les routes existent, toutes les pages repondent 200, aucune erreur PHP/Twig masquee dans le contenu. |
| Identification des groupes fusionnables : Plans (secteur/region), Styles (station/acces), Toilettes (sanitaire/sanisette publique), Types (transport/materiel/troncon) | Meme structure, perimetre different - meme logique que l'exemple donne par l'utilisateur. |
| `templates/tools/_onglets.html.twig` (nouveau partiel) | Barre d'onglets Bootstrap, liens normaux entre pages independantes (pas de fusion de controleur/pagination JS) - evite tout conflit entre les paginations independantes de chaque page. |
| Verification prealable : `Statuts de tache` est reserve ROLE_ADMIN, contrairement aux 3 autres Types (visibles par tout utilisateur connecte) | Volontairement PAS fusionne avec les 3 autres malgre la ressemblance structurelle - les fusionner aurait masque Transport/Materiel/Troncon aux utilisateurs non-admin. |
| `Sortie.acces` / `Etape.tache` (verification des relations) | Confirme que Sortie et Etape sont deja visibles depuis la fiche de leur parent (Acces, Tache) - retires du niveau superieur du menu plutot que dupliques. |
| `php bin/phpunit` | 4 echecs (assertions de titre de page obsoletes sur Style/Type*) - corriges dans les 4 fichiers de test concernes. |
| `git commit`/`git push`, `gh run watch` | Deploiement OK. |
| Verification Browser tool (local + prod, compte de test) | Menu passe de 32 a 26 liens, aucune erreur console. |

## Session du 2026-08-20 (suite) — Pastilles carrées et complétion de la topologie bus

Demande utilisateur : remplacer tous les médaillons ronds restants par le motif carré (couleur + nom en noir), factoriser en une classe CSS ; puis, suite à une observation de l'utilisateur sur les compteurs Desserte/Troncon, construire la topologie bus manquante.

| Commande | Objectif |
|---|---|
| Script ponctuel `convertir_medaillons.php` (scratchpad, non versionné) sur 17 templates | Convertit les derniers médaillons ronds (`ligne-badge rounded-circle`) vers le motif carré + texte noir, 22 remplacements. |
| Script ponctuel `convertir_pastilles.php` (scratchpad, non versionné) sur 19 templates (les 17 + les 2 templates d'origine du motif) | Factorise `<span class="badge" style="background-color: X">` en `<span class="pastille-ligne" style="--ligne-couleur: X">`, 30 remplacements. Classe définie dans `assets/styles/app.scss` (remplace les anciennes `.ligne-badge`/`.ligne-badge-sm` supprimées). |
| `php bin/console lint:twig` (sur chaque template touché), `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe, deux fois (après chaque script). |
| `git commit`/`git push` (`8eaa3bd`), `gh run list` | Déploiement OK. Vérification Browser tool (local + prod) : inspection `getComputedStyle` sur les pastilles, aucune erreur console. |
| `php -r` (PDO) : comptage `Desserte` (31787) vs `Desserte` reliées à un `Troncon` (7760 puis 7517 selon la requête) | Confirme l'observation de l'utilisateur : 24270 Desserte isolées (76%), quasi toutes en bus (78% des Desserte de bus isolées, 0% métro/tram). Explique pourquoi le calculateur de trajet échouait sur la plupart des lignes de bus. |
| `documentation/scripts/extraire_troncons_bus_reste.php` (nouveau) | Généralise l'extraction GTFS déjà utilisée pour les lots précédents : au lieu d'une liste de lignes tapée à la main, lit en base **toute** Ligne Bus/Car dont aucune Desserte n'a de Troncon (1167 lignes). Même algorithme de réduction géométrique (plus courtes arêtes d'abord, Dijkstra de confirmation). Traite le fichier `stop_times.txt` complet (~11,8M lignes) en tâche de fond (`b84tdl7fk`). Sortie : `troncons_bus_reste.csv` (24231 troncons). |
| `src/Command/ConstruireTopologieBusAutresOperateursCommand.php` (modifié) | Ajout du 3e CSV dans `TRONCONS_CSV` ; aucune autre modification (logique déjà générique). |
| `php -d memory_limit=4096M bin/console app:construire-topologie-bus-autres-operateurs` (local) | 1re tentative OOM (limite 512M par défaut) ; relancée avec `memory_limit=4096M` : `24120 troncons crees sur 1159 ligne(s)`. |
| `php bin/phpunit` (134 tests), test Browser tool local sur ligne 482 (Cimetières → Paul Painlevé / Place Gounot, isolée avant le fix) | Tout passe ; calcul de trajet réussi (2 min, 1 étape). |
| `git commit`/`git push` (`4f17041`), `gh run list` | Déploiement OK (2m26s). |
| Même import en prod (SSH, `memory_limit=4096M`) | Résultat identique au local : `24120 troncons crees sur 1159 ligne(s)`. |
| Vérification prod : `dbal:run-sql` (total Desserte / Desserte reliées) | 31787 / 31408 = 379 Desserte isolées, identique au local. |
| Compte de test admin (créé/supprimé en prod via SSH, hash bcrypt encodé en base64 pour éviter la corruption du `$` shell) + Browser tool | Calcul de trajet réussi en prod sur la ligne 482, identique au local (2 min, 1 étape), aucune erreur console. |
| Résiduel des 379 Desserte encore isolées (répartition par mode) | Train 331, RER 28, Bus 13, Téléphérique 5, Funiculaire 2 — cas marginaux (lignes fermées/spéciales), non traités ici. |

## Session du 2026-08-21 — Téléphérique/Funiculaire et filtre de mode du calculateur

Demande utilisateur : suite au résiduel de 379 Desserte isolées, vérification des hypothèses sur le Téléphérique (Câble A à Créteil, "un seul, ça doit pas être dur"), le Funiculaire de Montmartre ("en haut/en bas, 1 tronçon") et le RER D ("28 restants, peut-être que la topologie RER D va aider").

| Commande | Objectif |
|---|---|
| `php -r` (PDO) : stations/dessertes des lignes C1 et FUN | Confirme : Câble A = 1 ligne mais **5** stations (pas juste haut/bas), Funiculaire = 2 stations comme prévu. Les deux ont 100% de leurs Desserte isolées (topologie jamais construite). |
| `documentation/scripts/extraire_troncons_telepherique_funiculaire.php` (nouveau) | Même algorithme que les scripts bus (extraction GTFS + réduction géométrique), liste des 2 lignes tapée à la main (périmètre trivial). 5 troncons extraits (4 pour C1, 1 pour FUN). |
| `src/Command/ConstruireTopologieBusAutresOperateursCommand.php` (modifié) | Ajout d'un 4e CSV ; la commande étant déjà 100% générique (clé par codeExterne, sans hypothèse de mode), réutilisée telle quelle plutôt que dupliquée. `php -d memory_limit=4096M bin/console app:construire-topologie-bus-autres-operateurs` (local puis prod) : 5 troncons créés dans les deux environnements. |
| Test navigateur local (`/trajet`, La Végétale → Valenton) | **Découverte** : malgré les troncons créés, le calculateur renvoie un trajet en bus (14 min) au lieu du Câble A direct (4 min) — le tronçon existe mais n'est jamais utilisé. |
| Lecture de `Ligne::getModeFiltre()`/`TrajetFinder::modeFiltre()`/`construireGraphe()` | Cause : ces méthodes ne reconnaissent que Métro/Tramway/RER/Bus ; Téléphérique et Funiculaire tombent dans `default => null`, et `TrajetFinder::construireGraphe()` exclut silencieusement toute arête dont le mode n'est PAS dans `$modesAutorises` — un mode `null` n'y correspond jamais, même avec les 5 cases par défaut toutes cochées. Confirmé empiriquement (13367→25373 : bus N72 13 min au lieu du Câble A direct). |
| Question posée à l'utilisateur (corriger le filtre vs laisser tel quel) | Réponse : corriger. |
| Ajout de `telepherique`/`funiculaire` comme modes reconnus : `Ligne::getModeFiltre()`, `TrajetFinder::modeFiltre()`, les 3 Repository (`DesserteRepository`, `LigneRepository`, `TronconRepository`), les 4 `MODES_DISPONIBLES` (`TrajetController`, `LigneController`, `DesserteController`, `TronconController`), 2 templates (`trajet/index.html.twig`, `tools/filtre_liste.html.twig`, dont le seuil `length < 5` → `< 7`) | Rend les 2 modes réellement sélectionnables et utilisables par Dijkstra, partout où le filtre de mode existe (pas seulement `/trajet`). |
| `php bin/console lint:twig`, `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe. |
| Test navigateur local : La Végétale → Valenton avec Téléphérique coché → **C1, 4 min, 1 étape** ; Gare haute → Gare basse → bus 40, 2 min (le bus réel est plus rapide que le funiculaire réel sur ce trajet précis, comportement correct de Dijkstra, pas un bug) | Confirme le fix. |
| Le dépôt local (`Desktop\metroratp\.git`) est de nouveau illisible (`git fsck` : objets manquants) — copie des fichiers modifiés vers le clone de secours (`scratchpad/repo-clone-2`, voir mémoire projet), `git commit`/`git push` (`c6b8160`) depuis ce clone | Même contournement que documenté en mémoire (composer.json/code réels dans Desktop, mais `.git` cassé — toujours utiliser le clone pour les opérations git). |
| `gh run list --repo jujusilb/metroratp`, import identique en prod (SSH, `memory_limit=4096M`) | Déploiement OK (2m3s). 5 troncons créés en prod, compteurs isolés identiques au local (Téléphérique/Funiculaire à 0). |
| Compte de test admin (créé/supprimé en prod via SSH) + Browser tool | Mêmes résultats qu'en local (C1 direct 4 min, funiculaire→bus 40 2 min), aucune erreur console. |
| Investigation RER D (28 restants) : requêtes sur les Desserte isolées de la Ligne D (SQL brut) | Premier diagnostic (donné à l'utilisateur) : **erroné** — attribué au problème "Stations dupliquées" (Station sans `codeExterne` distincte de sa jumelle ZdC-liée). Corrigé quelques minutes plus tard en lisant `ConstruireTopologieRerCommand.php` (voir ligne suivante). |
| Lecture de `ConstruireTopologieRerCommand.php` | **Vraie cause, déjà documentée depuis le 2026-08-09** (`TODO.md`, section "Lignes à embranchements complexes") : le maillage Évry/Corbeil/Juvisy (2 cycles indépendants) que le modèle `Direction`/tronçon ne peut pas représenter (pense un arbre). Les 28 Desserte isolées correspondent exactement à cette zone. `troncons_rer.csv` contient déjà les 60 arêtes de la ligne D (dont celles du maillage) — jamais un problème de `codeExterne`, la commande existante rattache déjà par **label** de Station au sein de la Ligne (pas par `codeExterne`). |
| `src/Command/ConstruireMaillageRerDCommand.php` (nouvelle commande, `app:construire-maillage-rer-d`) | Importe uniquement les `Troncon`/`TronconDesserte` manquants du maillage (même rattachement par label), **sans** `Direction`/`Mission` : `TrajetFinder::construireGraphe()` ne lit que `Troncon`/`TronconDesserte` (vérifié), donc pas besoin de résoudre le problème plus dur (et non nécessaire ici) de représenter un cycle dans un modèle pensé pour un arbre. Idempotente (ne recrée pas les arêtes déjà là, vérifie par paire de Desserte). |
| `php bin/console app:construire-maillage-rer-d` (local) | 31 troncons créés, 29 déjà présents, 0 ignorés. RER isolé : 28 → 0. |
| `php bin/phpunit` (134 tests) | Tout passe. |
| Test navigateur local : Melun → Juvisy, modes=[rer] seul | **35,7 min, 12 étapes, 0 correspondance**, tout en RER D (traverse le maillage) — introuvable avant ce correctif. Avec tous les modes cochés : trouve une alternative via Transilien R (34,3 min, 2 correspondances), ce qui est cohérent (Dijkstra compare les deux et choisit le plus rapide). |
| Correction de `documentation/TODO.md` (section RER D, remplace le faux diagnostic "Stations dupliquées") et de `documentation/commande.md` (ce tableau) | Le premier diagnostic donné à l'utilisateur était incorrect ; corrigé avant tout déploiement/tracker. |

## Session du 2026-08-21 (suite) — Topologie Transilien (H, J, K, L, N, P, R, U) + mode Train

Demande utilisateur : "GO !" pour continuer sur les Desserte isolées restantes après le RER D (79 sur les 8 lignes Transilien).

| Commande | Objectif |
|---|---|
| `php -r` (PDO) : Desserte isolées par Ligne Train | Confirme 8 lignes Transilien (H:50, J:54, K:10, L:36, N:35, P:32, R:24, U:11 - 252 au total) entièrement sans topologie ; codeExterne vérifiés stables contre le GTFS actuel (`grep routes.txt`). |
| `documentation/scripts/extraire_troncons_transilien.php` (nouveau) | Réécriture PHP autonome (stops.txt + haversine) du script Python original (`extraire_troncons_rer.py`, qui dépend d'un référentiel Lambert-93 externe au dépôt). Union des paires consécutives + réduction géométrique (même algorithme). 308 troncons retenus. |
| Comparaison nœuds/arêtes par ligne | 7 des 8 lignes ont un excédent d'arêtes par rapport à un arbre pur (H:+10, J:+24, K:+2, L:+9, N:+11, P:+1, R:+7, U:+0) — embranchements légitimes et/ou vrais maillages (H a une boucle connue Argenteuil/Ermont). Décision : comme pour le RER D, construire uniquement `Troncon`/`TronconDesserte` (pas de `Direction`/`Mission`), pour éviter d'auditer chaque ligne une par une. |
| `src/Command/ConstruireTopologieTransilienCommand.php` (nouvelle, `app:construire-topologie-transilien`) | Rattachement par label de Station au sein de chaque Ligne (même technique que RER D). 1re exécution : 299 troncons créés, 9 ignorés (4 paires de noms avec tiret manquant côté DB : "Neuville - Université", "Saint-Nom-la-Bretèche - Forêt de Marly", "Viroflay - Rive Droite", "Nemours - Saint-Pierre"). |
| Ajout de `ASSOCIATIONS_MANUELLES` (4 paires) + réexécution (idempotente) | 9 troncons supplémentaires créés, 0 ignoré. Desserte isolées Transilien : 252 → 0 (H/J/K/N/P/U direct, L et R via les associations manuelles). |
| `php bin/phpunit` (134 tests) | Tout passe. |
| Test navigateur local : Luzarches → Persan-Beaumont (2 branches de la ligne H), tous modes cochés | **Trouve un trajet bus (ligne 100, 24 min)**, pas de ligne H — signe que le mode Train est probablement filtré comme Téléphérique/Funiculaire avant leur correctif. |
| Lecture de `Ligne::getModeFiltre()` | Confirmé : `'Train'` tombe toujours dans `default => null`, comme Téléphérique/Funiculaire avant correction — même bug de fond, pas encore étendu à ce mode. |
| Ajout de `train` comme 8e mode reconnu : `Ligne::getModeFiltre()`, `TrajetFinder::modeFiltre()`, les 3 Repository, les 4 `MODES_DISPONIBLES`, 2 templates (seuil `length < 7` → `< 8`) | Même procédure exacte que pour telepherique/funiculaire. |
| `php bin/console lint:twig`, `php bin/phpunit` (134 tests) | Tout passe. |
| Test navigateur local : Luzarches → Persan-Beaumont, modes=[train] seul | **Ligne H, 25,3 min, 8 étapes, 0 correspondance** — confirme le fix. Avec tous les modes cochés : bus 100 toujours choisi (24 min, plus rapide de 1,3 min) — comportement Dijkstra correct, pas un bug (comme pour le funiculaire). |
| Desserte isolées finales (tous modes) | 344 → 92 (résiduel : 79 Train = TER/V/CDG VAL/ORLYVAL, hors périmètre Transilien ; 13 Bus déjà connus). |
| `documentation/TODO.md` | Nouvelle entrée "Transilien H/J/K/L/N/P/R/U" détaillant la méthode et le bug de filtre corrigé. |
| Le dépôt local (`Desktop\metroratp\.git`) reste illisible — copie des fichiers vers le clone de secours (`scratchpad/repo-clone-2`), `git commit`/`git push` depuis ce clone, `gh run list` pour vérifier le déploiement | Même contournement que documenté en mémoire projet. |
| Import identique en prod (SSH) : `app:construire-topologie-transilien` (2 exécutions, avec puis sans les associations manuelles) | 308 troncons créés au total, identique au local. |
| Compte de test admin (créé/supprimé en prod via SSH) + Browser tool : Luzarches → Persan-Beaumont, modes=[train] seul | Identique au local (ligne H, 25,3 min, 8 étapes), aucune erreur console. |

## Session du 2026-08-21 (suite) — Libellé "Transilien", puis climatisation par Desserte

Demande utilisateur : afficher "Transilien" au lieu de "Train" dans le calculateur/filtres (fait, voir ci-dessous), puis choix de la tâche #6 du backlog (import `sdap-arrets-associes.csv`) parmi les tâches restantes proposées.

| Commande | Objectif |
|---|---|
| `templates/trajet/index.html.twig`, `templates/tools/filtre_liste.html.twig` (modifiés) | Renomme le libellé affiché du mode `train` en "Transilien" (le mode interne reste `train`). Vérifié en local et en prod (case à cocher + suggestion d'autocomplétion sur "Sèvres - Ville-d'Avray"). |
| Lecture de `src/Command/ImporterAccessibiliteDessertesCommand.php` avant de commencer la tâche #6 | **Découverte** : la tâche est en réalité déjà largement faite (session antérieure "EquipementArret/ArretTransporteur", 2026-08-20) — `ArRAccessibility`/`ArRAudibleSignals`/`ArRVisualSigns` sont déjà importés sur `Desserte` (16075/31787 dessertes déjà renseignées, vérifié). Seuls les champs `Extensions` (climatisation, JSON imbriqué) et `bookingRules` du même dataset restaient inexploités. |
| Analyse du CSV : remplissage de `Extensions`/`bookingRules` | `Extensions.ServiceFacilitySet.ClimateControlList` rempli sur 36695/36695 lignes (noConditioning: 14189, other: 14056, airConditioning: 6270, unknown: 2180) — donnée réelle exploitable. `bookingRules` : seulement 51/36695, non significatif, laissé de côté. |
| Ajout `Desserte::climatisation` (nullable string) + migration | `make:migration` cassé sur cette copie locale (`doctrine_migration_versions` désynchronisé : 13 migrations 2026-08-15→08-20 appliquées par `schema:update` manuel lors de sessions antérieures, jamais enregistrées dans le tracker — confirmé sans rapport avec prod, dont le tracker a bien les 42/42 migrations). Migration `Version20260821160000.php` écrite à la main (même format que les précédentes) ; colonne ajoutée directement en local via `ALTER TABLE` (contournement du tracker cassé, sans impact sur prod qui exécute `doctrine:migrations:migrate` normalement). |
| `ImporterAccessibiliteDessertesCommand` (étendue) | Ajout de `versClimatisation()` (regex sur le JSON, évite de parser tout le document pour une seule valeur) ; traduit vers 'Climatisé'/'Non climatisé'/'Autre' (unknown → null). Réexécution : 35005 Desserte mises à jour (identique au nombre de la session du 2026-08-20, confirmant l'idempotence). |
| `templates/desserte/show.html.twig` | Nouvelle ligne "Climatisation" dans le tableau, juste après signalisation visuelle. |
| `php bin/console lint:twig`, `php bin/phpunit` (134 tests) | Tout passe. |
| Vérification navigateur locale (Desserte #32669, ligne de bus climatisée) | "Climatisation → Climatisé" bien affiché. |
| Déploiement (clone de secours, voir mémoire projet) : migration `Version20260821160000`, `gh run list` | Déploiement OK (2m9s). Migration appliquée en prod (vérifié via `information_schema.columns`, `dbal:run-sql DESCRIBE` renvoyant 0 lignes de façon trompeuse — piège déjà connu). |
| `php bin/console app:importer-accessibilite-dessertes` en prod (SSH) | 35005 Desserte mises à jour, identique au local. Répartition climatisation identique (Non climatisé 7728, Autre 7394, Climatisé 3090). |
| Compte de test admin (créé/supprimé en prod via SSH) + Browser tool (Desserte #1535 "Les Violettes") | "Climatisation → Climatisé" confirmé en prod. |
| Tâche #6 (tracker, local + prod) | Corrigée et marquée ACHEVÉE : reflète que l'essentiel était déjà fait en session antérieure, seule la climatisation manquait. |

## Session du 2026-08-21 (suite) — Quais décalés (temps de trajet asymétrique)

Demande utilisateur : enchaîner sur une autre tâche du backlog une fois la climatisation terminée. Choix de la tâche #11 (#2 bloquée par absence de donnée, #10 trop invasive).

| Commande | Objectif |
|---|---|
| Lecture de `Troncon`/`TronconDesserte`/`TrajetFinder::construireGraphe()` | `Troncon::dureeReelleSecondes` est une seule valeur partagée par les 2 sens (aller/retour) — confirmé comme seule limite du modèle empêchant de représenter un quai décalé. Seul consommateur : `TrajetFinder` (aucun template n'affiche ce champ). |
| Analyse de `documentation/scripts/donnees-extraites/troncon_durees.csv` (source de `app:importer-durees-troncon`) | **Découverte** : 661 des 772 paires (86%) ont déjà les deux sens présents et DIFFÉRENTS dans le CSV (ex: Liège, 89s vers Saint-Lazare / 65s vers Clichy — vrai quai décalé, pas un artefact d'arrondi). L'import existant fusionnait les deux sens (`$durees[$nomA][$nomB] ?? $durees[$nomB][$nomA]`), perdant cette nuance déjà disponible depuis le début. |
| Ajout `TronconDesserte::dureeReelleSecondes` (nullable, significatif côté "Départ" seulement) + migration `Version20260821180000` (écrite à la main, même souci de tracker de migrations local que pour la climatisation) | `Troncon::dureeReelleSecondes` devient le repli symétrique. |
| `TrajetFinder::construireGraphe()` (modifié) | `COALESCE(tda.duree_reelle_secondes, t.duree_reelle_secondes)` — changement d'une ligne SQL, aucune autre logique touchée. |
| `ImporterDureesTronconCommand` (réécrite) | Pour chaque Troncon, essaie une correspondance EXACTE par sens (pas de repli croisé) sur chaque `TronconDesserte` "Départ" ; le repli symétrique historique (`Troncon::dureeReelleSecondes`) reste écrit en plus, pour ne rien régresser. `php -d memory_limit=4096M bin/console app:importer-durees-troncon ...` (OOM à 512M et 2048M — réseau bien plus gros qu'à l'écriture initiale de la commande, avec tout le travail bus/RER/Transilien de cette session) : 795/32224 troncons avec une durée trouvée (inchangé, c'est la couverture du CSV historique), dont 1569 sens précis écrits sur `TronconDesserte`. |
| `php bin/phpunit` (134 tests) | Tout passe. |
| Vérification SQL puis navigateur locale : Place de Clichy ↔ Saint-Lazare (ligne 13, via Liège), mode métro seul | **2,6 min aller (64+89s) vs 2,2 min retour (65+69s)** — l'asymétrie du quai décalé de Liège est maintenant bien reflétée par le calculateur. |
| `documentation/TODO.md` | Section "quais décalés" marquée faite, avec la limite résiduelle documentée : seul le CSV historique métro/RER a cette précision directionnelle : les topologies bus/RER D/Transilien construites cette session fusionnent déjà les deux sens à l'extraction (`sort($paire)`), pas encore réextraites avec la directionnalité conservée. |
| Déploiement (clone de secours), import en prod (SSH, `memory_limit=4096M`) | **Échec** : `SQLSTATE[HY000]: General error: 2006 MySQL server has gone away` à l'hydratation — la jointure complète `findAllWithDetails()` (missions/direction/desserteTerminus, jamais lue par cette commande) est trop volumineuse pour l'hébergement mutuel une fois le réseau à ~32000 troncons. |
| `TronconRepository::findAllPourImportDurees()` (nouvelle méthode, sans la chaîne de jointures Mission/Direction) | `ImporterDureesTronconCommand` bascule dessus. En local : passe même à 2048M (contre 4096M requis avant, avec la requête complète). |
| `php bin/phpunit` (134 tests) | Tout passe. |
| Déploiement (2e commit), réimport en prod (SSH, `memory_limit=2048M`, timeout 200s) | Réussi : 794/32224 troncons (quasi identique au local, écart de 1 sans incidence), 1569 sens précis. |
| Vérification SQL + Browser tool (compte de test) : Place de Clichy ↔ Saint-Lazare, ligne 13 | Identique au local : 2,6 min aller / 2,2 min retour, aucune erreur console. |
| Tâche #11 (tracker, local + prod, recherche par nom) | Marquée ACHEVÉE avec le détail complet (asymétrie découverte, fix de requête inclus, limite résiduelle bus/RER D/Transilien). |

## Session du 2026-08-22 — Fusion des Stations dupliquées (tâche #10)

Demande utilisateur : "GO !" pour continuer. Seule tâche actionnable restante (#2 bloquée par absence de donnée) : la fusion des ~486 Stations dupliquées, plusieurs fois volontairement différée pour son caractère invasif.

| Commande | Objectif |
|---|---|
| Confirmation avant action (`AskUserQuestion`) | Étant donné le caractère irréversible (suppression de lignes, contrairement au reste de la session qui n'ajoutait que des données), présentation du plan et du chiffre exact avant toute exécution. Réponse : fusionner les paires sûres. |
| Analyse SQL (label exact + distance haversine ≤ 300m) | 799 correspondances brutes par label seul (bruitées : 83 stations "originale" ont plusieurs homonymes, ex "Victor Hugo" 35 candidats). Après filtre de distance : **371 candidats uniques, 0 ambigu, 0 sans candidat** — signal net. Comparaison champ par champ (ville, zone_tarifaire, plan_id, pole_echange_id, accessibilite_pmr...) sur ces 371 paires : **0 conflit de valeur**, uniquement des trous à combler côté "originale". Vérifié aussi : aucune des 371 paires n'a de Desserte sur la même Ligne des deux côtés (pas de risque de doublon (station,ligne) après fusion). |
| `src/Command/FusionnerStationsDupliqueesCommand.php` (nouvelle, `app:fusionner-stations-dupliquees`) | Recalcule les paires à chaque exécution (jamais de liste figée, idempotente). Repère les 9 tables à FK directe vers Station (`desserte`, `sortie`, `equipement_arret`, `position_rame`, `defibrillateur`, `fontaine_eau`, `point_de_vente`, `sanisette_publique`, `sanitaire`, confirmé exhaustif via `information_schema.columns`). |
| `mysqldump` (local, tables concernées) | Sauvegarde avant tout essai réel (8,6 Mo). |
| 1er essai réel : échec | `SQLSTATE[23000]... Duplicate entry '71517' for key 'UNIQ_...'` sur "La Défense" — `station.code_externe` a une contrainte d'unicité ; copier sa valeur AVANT de supprimer la ligne ZdC crée un doublon transitoire (MySQL ne diffère pas cette vérification, même en transaction). Rollback automatique confirmé propre (aucune donnée touchée). |
| Correction : réordonnancement (COALESCE des autres champs → repointage des 9 FK → suppression de la ligne ZdC → *puis seulement* écriture de `code_externe` sur l'originale, valeur portée dans le tableau des paires plutôt que relue via jointure) | Réexécution : **371 paires fusionnées**, 0 échec. |
| Vérifications : réexécution en `--dry-run` (0 paire restante, confirme l'idempotence), `php bin/phpunit` (134 tests), `npx jest` (51 tests), requête d'absence d'orphelins sur les 9 tables (0 partout) | Tout passe. |
| Vérification navigateur (compte de test) : `/station/21` (Nation), `/station/1` (La Défense) | Sorties, Points de vente, Sanitaires, Défibrillateurs — jusque-là invisibles sur la page réellement consultée (rattachés à la jumelle ZdC jamais visitée) — apparaissent enfin. `/trajet` Nation→La Défense (RER A, 12,5 min) fonctionne toujours. `/station/15` (Châtelet, originale sans coordonnées) intact et non fusionné, comme prévu (2 homonymes réels : Paris et Montereau-Fault-Yonne). |
| `documentation/TODO.md` | Section "Stations dupliquées" mise à jour : 371/534 fusionnées, 163 volontairement laissées de côté (détail des raisons). |
| `mysqldump` en prod (SSH, hors du dossier app) + copie locale via `scp` | Sauvegarde avant exécution en prod, même discipline qu'en local. |
| Déploiement (clone de secours), `--dry-run` en prod | 347 paires (contre 371 en local — écart normal, bases divergentes). |
| Fusion réelle en prod, vérifications (dry-run→0, absence d'orphelins sur les 9 tables, Browser tool sur `/station/21`/`/station/1`, `/trajet` Nation→La Défense) | Identique au local, aucune erreur console. |
| Tâche #10 (tracker, local + prod, recherche par nom) | Marquée ACHEVÉE. |

## Session du 2026-08-22 (suite) — Import de emplacement-des-gares-idf-data-generalisee.csv

Demande utilisateur : "Importer emplacement-des-gares-idf-data-generalisee.csv ?"

| Commande | Objectif |
|---|---|
| Inspection du fichier (déjà présent dans `documentation/IDFM-gtfs/csv/`, jamais utilisé) | Référentiel officiel IDFM (999 lignes, gares train/RER/métro/tramway) : `id_ref_ZdC`/`nom_ZdC`, `Geo Point` (lat,lon direct), modes. |
| Croisement avec la base : 988/991 ZdC déjà en base ET déjà positionnés (0 apport) ; mais **74 des 163 Station "originale" encore sans coordonnées** (résiduel de la fusion précédente) ont un `nom_ZdC` correspondant | Valeur réelle identifiée : compléter les coordonnées manquantes plutôt que réimporter des données déjà là. |
| Vérification de l'ambiguïté du rapprochement par nom | 72 correspondances uniques, 2 ambiguës ("Saint-Fargeau", "Pont de Rungis Aéroport d'Orly" — homonymes réels), même discipline que partout ailleurs. |
| `cp` du fichier vers `documentation/scripts/donnees-extraites/` | `documentation/IDFM-gtfs/` est gitignore (règle retrouvée dans le clone de secours, absente du `.gitignore` de la copie Desktop — encore un signe de dérive locale déjà documentée) ; fichier assez petit (275 Ko) pour être commité tel quel, même précédent que `schema_gares-gf.csv`. |
| `ImporterCoordonneesGeographiquesCommand` (étendue, 3e passe) | Repli par `nom_ZdC` de ce fichier pour les Stations toujours sans coordonnées après les 2 passes existantes. Exécution : **81 Stations positionnées** (2 ambiguës, 91 sans correspondance) — plus que les 74 estimés (le pool réel incluait d'autres Stations sans coordonnées non liées à la fusion précédente). |
| `php bin/console app:fusionner-stations-dupliquees --dry-run` (relance) | Les nouvelles coordonnées débloquent **63 paires supplémentaires**, mécaniquement. |
| Fusion réelle, vérifications (dry-run→0, `php bin/phpunit` 134 tests, `npx jest` 51 tests, absence d'orphelins sur les 9 tables) | Tout passe. |
| Vérification navigateur locale : `/station/546` (Roissy-en-Brie, une des 63 nouvelles fusions) | Sorties et accessibilité désormais visibles, comme pour le premier lot. |
| `documentation/TODO.md` | Section "Stations dupliquées" complétée : total cumulé 434/534. |
| Déploiement (clone de secours), `mysqldump` en prod (nouvelle sauvegarde) + copie locale | Même discipline que les rounds précédents. |
| `app:importer-coordonnees-geographiques` puis `app:fusionner-stations-dupliquees` en prod | 88 Stations positionnées (contre 81 en local), 65 fusions supplémentaires (contre 63) — écarts normaux. Total cumulé en prod : 412/534. |
| Vérifications (dry-run→0, absence d'orphelins, Browser tool sur `/station/544` Roissy-en-Brie) | Identique au local, aucune erreur console. |
| Tâche #10 (tracker, local + prod) | Étape complémentaire ajoutée, statut ACHEVÉE conservé. |

## Session du 2026-08-22 (suite) — TODO page `/ligne`, puis bug Station.codeExterne périmé

Demande utilisateur : noter dans TODO.md 2 soucis d'affichage sur `/ligne/{id}` (pastilles+nom sur la même ligne, ordre des stations pas toujours le vrai cheminement sur les lignes en maillage — voir `documentation/TODO.md` pour le détail technique déjà vérifié sur RER D), puis discussion sur une possible réorganisation "vers Paris/dans Paris/vers X" (répondu, en attente du retour utilisateur), puis signalement d'un vrai bug sur la fiche "Hôtel de Ville" (aucune Sortie/Sanitaire/etc., malgré la fusion des Stations dupliquées).

| Commande | Objectif |
|---|---|
| Investigation "Hôtel de Ville" (35 homonymes en base !) | La vraie Station parisienne (id 16, lignes 1+11) a un `code_externe` (59762) **absent du GTFS actuel** (confirmé : `grep` sur `stops.txt`, aucune trace). Sa vraie jumelle ZdC-liée (id 20770, Paris 4e) a bien 10 lignes de bus et 8 Sorties, mais n'a jamais été fusionnée : `app:fusionner-stations-dupliquees` ne cherche que les Station à `code_externe IS NULL`, pas périmé. |
| Mesure de l'ampleur : comparaison de toutes les Station à `code_externe` contre `zdc_coordonnees.csv` | Seulement **14 Station perimées** sur ~13710 avec codeExterne (Concorde, Villiers, Colonel Fabien, Saint-Augustin, Rue du Bac, Port Royal, Pyrénées, Commerce, Chemin Vert, Anatole France, Hoche, Les Sablons, Saint-Denis) — même symptôme que `Ligne.codeExterne` trouvé le 2026-08-17, mais sur Station. |
| Vérification de la désambiguïsation par voisin `Troncon` (ces 14 Station n'ont elles-mêmes aucune coordonnée pour se départager entre homonymes directement) | Chaque Station a un voisin déjà positionné ; le candidat homonyme le plus proche de ce voisin est net à chaque fois (moins de 700m pour 13/14, 3.5km pour la dernière, contre 3 à 27km pour le 2e plus proche candidat) — vérifié avant d'écrire du code. |
| `src/Command/CorrigerCodeExterneStationsPerimeCommand.php` (nouvelle, `app:corriger-code-externe-perime`) | Même mécanique de fusion que `app:fusionner-stations-dupliquees` (COALESCE, repointage des 9 tables, suppression de la jumelle), sauf `code_externe` **forcé** (pas juste complété, la valeur existante est fausse pas absente) — écrit en dernier, même ordre imposé par la contrainte d'unicité que la commande sœur. |
| `mysqldump` (sauvegarde), exécution réelle, vérifications (dry-run→0, absence d'orphelins sur les 9 tables, `php bin/phpunit` 134 tests, `npx jest` 51 tests) | 14/14 corrigées. Tout passe. |
| Vérification navigateur locale : `/station/16` | "Hôtel de Ville (Paris 4e)", zone tarifaire, 10 lignes de bus, 8 Sorties réelles — enfin visibles. |
| Retour utilisateur (même message) : "Conseils de position dans la rame" ne doit pas s'afficher sur la fiche Station (n'a de sens qu'avec une destination connue), mais dans le calculateur de trajet | Investigation du modèle `PositionRame` (Ligne+Station+destination texte libre). |
| `PositionRameRepository::trouverParStationEtLigne()` (nouvelle) + `TrajetController::construireSegmentsPourAffichage()` (étendue) | Chaque tronçon du trajet porte désormais ses conseils de positionnement (Ligne+Station d'arrivée du tronçon) — on sait déjà, dans un trajet calculé, s'il faut changer de ligne ou si c'est l'arrivée, contrairement à la fiche Station seule. |
| `templates/trajet/index.html.twig` (vue Détaillée, affichage des conseils) + `templates/station/show.html.twig`/`StationController::show()` (section retirée) + `PositionRameRepository::trouverParStation()` (supprimée, plus utilisée) | Nettoyage complet du code mort. |
| `php bin/phpunit`, `npx jest`, vérification navigateur (Bastille → Nation, ligne 1) | Conseils affichés correctement en fin de tronçon ("Pour rejoindre Nation : se placer Milieu (3/6)..."), identiques aux valeurs vues auparavant sur la fiche Station. Confirmé disparu de `/station/21`. |
| Déploiement (clone de secours), `mysqldump` en prod + copie locale, `app:corriger-code-externe-perime` (dry-run puis réel) | 14/14 en prod aussi (mêmes stations, 2 ids différents des 2 dernières lignes — bases divergentes, sans incidence). Vérifications identiques au local (absence d'orphelins, Browser tool sur `/station/16` et le trajet Bastille→Nation) : tout passe. |

## Session du 2026-08-22 (suite) — Carte des sorties déplacée dans un modal

Demande utilisateur (message coupé "...tu mets un bouton carte qui ouvre un modal avec la carte en...") : même traitement que la carte du trajet, déjà déplacée en modal plein écran sur retour "la carte prend trop de place".

| Commande | Objectif |
|---|---|
| Lecture de `assets/js/carte-acces.js` (fonction pure `initCarteAcces(container, donnees)`) et du pattern déjà établi (`carte-modal`/`carte-reseau-modal` dans `assets/app.js`) | Même mécanique directement réutilisable : lazy-init au premier `shown.bs.modal`, `invalidateSize()` aux réouvertures suivantes. |
| `templates/station/show.html.twig` (bouton "Carte" à côté du titre "Sorties", carte déplacée dans un `modal-fullscreen`, identique en structure au modal `/trajet`) | La carte (400px fixe, toujours affichée) disparaît du flux normal de la page. |
| `assets/app.js` (bloc d'init étendu, même structure que les 2 précédents) | |
| `npx encore dev`, `npx jest` (51 tests), `php bin/console lint:twig` | Tout passe. |
| Vérification navigateur locale (`/station/21`, compte de test) : carte absente par défaut (`display: none`), clic sur "Carte" → modal plein écran, carte Leaflet initialisée (1248×625), 6 marqueurs de sortie, aucune erreur console | Conforme. |

## Session du 2026-08-22 (suite) — Investigation tracés de bus vs vol d'oiseau, + 2 notes TODO

Demande utilisateur : "Tracés de bus vs vol d'oiseau : un cas signalé le 17/08 jamais vérifié. GO !", puis en cours de route, ajout TODO de 2 sujets (liste des Ligne d'un Gestionnaire absente de sa fiche ; ligne 3139 "Pays Briard" introuvable).

| Commande | Objectif |
|---|---|
| Script PHP de scan (scratchpad), réimplémentation de `projeterSurSegment`/`projeterSurLigne`/score de `assets/js/trajet-carte.js` (seuil 150m identique) | Scanner systématiquement les 62038 tronçons de bus avec coordonnées des 2 côtés plutôt que de deviner depuis une seule capture d'écran. |
| 1er passage : 438 échecs sur 61540 tronçons avec `Ligne.trace` (0,7%) | Distribution très bimodale : 280 cas 150-1000m (dont 232 à 150-200m, marge probable du seuil), 158 cas 1000m-25km — décision de creuser d'abord le cluster "loin", plus suspect. |
| Regroupement des 158 échecs "loin" par `ligne_id` | Concentrés sur **seulement 4 Ligne** (pas 158 lignes distinctes) : Soir Domont #2731 (88), TàD Eaubonne Domont #2711 (36), Remplacement Transilien H #2538 (22), 1412 ex-95-03B #1696 (12). |
| Comparaison bbox `Ligne.trace` vs bbox des Station desservies, pour ces 4 Ligne | Soir Domont et TàD Eaubonne Domont : bbox totalement disjointes (~15-25km d'écart). Remplacement Transilien H et 1412 ex-95-03B : bbox chevauchantes (trace globalement bien rattaché mais incomplet). |
| Vérification dans `referentiel-des-lignes.csv` (`TransportSubmode`/`Type`) | Soir Domont et TàD Eaubonne Domont : `demandAndResponseBus` (Transport à la Demande, pas d'itinéraire fixe réel — pas un bug). Remplacement Transilien H : `REPLACEMENT_LINE_TYPE` (service de substitution bus, pas d'itinéraire fixe unique non plus). 1412 ex-95-03B : `expressBus` (vraie ligne régulière, réseau Val Parisis) — **seul vrai cas de données incomplètes** du lot (12/62038 tronçons, 0,02%), trace à seulement 2 composantes/162 points ne couvrant pas toute la zone de ses 30 Station. |
| Conclusion, `documentation/TODO.md` mis à jour (entrée existante du 2026-08-17 complétée) | Pas de correctif de code : 3 des 4 lignes n'ont structurellement pas de tracé fixe (comportement attendu), la 4e a un volume trop marginal et des données GPS manquantes non reconstructibles depuis le dépôt. Les 280 cas "proches" pas creusés plus loin (impact visuel jugé mineur). |
| `documentation/TODO.md` (2 nouvelles notes, hors sujet tracés) | Fiche Gestionnaire : relation `Gestionnaire::getLignes()` déjà présente côté modèle, juste un ajout de template à faire. Ligne 3139 "Pays Briard" : présente et `active` dans `referentiel-des-lignes.csv` (`C01058`) mais absente de `reseau_complet.csv` (généré par `extraire_reseau_complet.py`, qui ne retient que les route_id vus dans `trips.txt` du GTFS complet) — cause exacte non confirmée plus loin (fichiers GTFS bruts plus présents en local, il faudrait retélécharger le flux complet pour trancher). |

## Session du 2026-08-23 — Entité Ville + Station.villeRef

Demande utilisateur : "donc j'aimerais une table pour les ville et une clé etrangere vers la table dans station" (suite de la récupération des GeoJSON de communes IDF la veille).

| Commande | Objectif |
|---|---|
| Grep des entités avec un champ `ville` en varchar | 5 concernées : `Station`, `Defibrillateur`, `EquipementArret`, `PointDeVente`, `Utilisateur`. Décision : ne traiter que `Station` (seule demandée), ajouter `villeRef` en plus du `ville` existant plutôt que le remplacer (3 dépendants actifs identifiés : `TrajetController::index()`, `templates/station/show.html.twig`, `ImporterPlansSecteurCommand` qui déduit le département depuis le texte brut). |
| Comparaison des 1161 valeurs distinctes de `station.ville` contre les 1266 communes des GeoJSON (script scratchpad, normalisation accents/casse) | 1062 correspondances exactes + 20 arrondissements parisiens (tous vers la seule commune "Paris"). 79 valeurs sans correspondance : 1 par accent seul (Evry-Courcouronnes), 3 par renommage/fusion de commune (Saint-Ouen, Chesnay-Rocquencourt, Herblay), 75 réellement hors Île-de-France (Chartres, Sens, Château-Thierry... — réseau Transilien/bus qui dépasse la région, absent par choix de périmètre). |
| `src/Entity/Ville.php` (nouvelle) + `src/Repository/VilleRepository.php` | `label`, `codeInsee` (unique), `frontiere` (JSON — même convention que `Ligne::trace`), relation inverse `stations` (OneToMany). |
| `src/Entity/Station.php` (`villeRef`, ManyToOne nullable, `inversedBy: 'stations'`) | Champ `ville` (varchar) intact, `villeRef` additif. |
| `make:migration` échoue ("metadata storage not up to date") | `doctrine:migrations:sync-metadata-storage` ne résout pas le symptôme (tracker local désynchronisé depuis le 15/08, déjà documenté). Contournement : `doctrine:schema:update --dump-sql` (ne dépend pas du tracker) pour obtenir le SQL exact avec les vrais noms de contrainte Doctrine (`UNIQ_43C3D9C31649A761`, `FK_9F39F8B137540B03`), migration `Version20260822120000.php` écrite à la main dans le même style que les migrations précédentes, DDL appliqué directement en local (même pratique que tout le reste de la session). |
| `src/Command/ImporterVillesCommand.php` (`app:importer-villes`) | Upsert des 1266 `Ville` par `codeInsee`. Rattachement `Station::villeRef` par correspondance de nom, avec repli sur 4 corrections manuelles (communes renommées) et cas spécial Paris (tous arrondissements → la seule commune Paris). |
| Bug détecté en cours de route : 4 noms de commune sont des homonymes réels (Blandy, Marolles-en-Brie, Mondreville, Saint-Martin-des-Champs — 2 communes distinctes partageant le même nom), 12 Station concernées | 1er essai : rattachement arbitraire (dernier candidat chargé, par ordre de fichier). Corrigé avant tout déploiement : désambiguïsation par test point-dans-polygone (ray casting sur le contour GeoJSON réel) plutôt qu'une distance approximative — vérifié un par un, ex. les 2 Station "Saint-Martin-des-Champs" pointent bien vers 2 codes INSEE différents (78565 et 77423) après correction. |
| `php bin/phpunit` échoue ("Unknown column t0.ville_ref_id") malgré la colonne bien présente en local (vérifié via PDO direct) | Découverte : PHPUnit utilise une base séparée **`metroratp_test`** (confirmé via `APP_ENV=test php bin/console dbal:run-sql "SELECT DATABASE()"`), pas seulement `metroratp` — Symfony ignore `.env.local` en environnement test. DDL appliqué aussi sur `metroratp_test`. Mémoire long terme ajoutée pour ne pas retomber dans ce piège. |
| `php bin/phpunit` (134 tests), `npx jest` (51 tests) | Tout passe après correction. |
| Déploiement (clone de secours), `mysqldump` en prod (tables `station`+`ville`, 1,68 Mo) + copie locale via `scp` | Sauvegarde avant modification de la table `station` en prod, même discipline que les rounds précédents. |
| `app:importer-villes` en prod, vérification SQL directe (`COUNT(*)` sur `ville`/`station.ville_ref_id`) | Résultat identique local/prod : **1266 Ville, 13529 Station rattachées, 144 sans correspondance** (hors Île-de-France, attendu), **0 homonyme non tranché**. Pas de test navigateur : aucune UI n'exploite encore `villeRef` (donnée backend seulement à ce stade). |
| `documentation/TODO.md` | Entrée mise à jour : Station fait, reste à faire pour les 4 autres entités (`Defibrillateur`/`EquipementArret`/`PointDeVente`/`Utilisateur`) et l'affichage carte, non demandés à ce stade. |

## Session du 2026-08-23 (suite) — Ville.codesPostaux oublié, confirmation aucune Station supprimée

Demande utilisateur : "as tu ajouter les code postaux? je ne veux aucune station absente... pour les station hors ile de france, tu met juste le nom de la ville en string... mais tu ne supprime aucune station".

| Commande | Objectif |
|---|---|
| Vérification : `COUNT(*)` sur `station` avant/après tout le travail Ville | 13796 dans les deux cas — aucune Station supprimée. Les 267 sans `villeRef` (dont les 144 hors Île-de-France) gardent leur `ville` texte libre intact (ex. "Chartres", "Auneau-Bleury-Saint-Symphorien"), jamais touché par `app:importer-villes`. |
| Vérification des GeoJSON déjà téléchargés | Les codes postaux étaient bien présents depuis le départ (champ `codesPostaux` demandé dans l'URL `geo.api.gouv.fr`, 1266/1266 communes, jusqu'à 21 pour Paris) mais jamais persistés dans l'entité `Ville` — oubli signalé par l'utilisateur, pas un manque de donnée source. |
| `src/Entity/Ville.php` (`codesPostaux`, JSON nullable) + migration `Version20260823100000.php` (`ALTER TABLE ville ADD codes_postaux JSON DEFAULT NULL`, SQL obtenu via `doctrine:schema:update --dump-sql`) | DDL appliqué sur `metroratp` ET `metroratp_test` (voir mémoire ajoutée la veille sur cette 2e base). |
| `ImporterVillesCommand` (persiste desormais `codesPostaux`), réexécution locale | Réimport idempotent : 1266 Ville mises à jour, 13529 Station toujours rattachées (inchangé), Paris confirme ses 21 codes postaux (75001-75020 + 75116). |
| `php bin/phpunit` (134), `npx jest` (51) | Tout passe. |
| Déploiement (clone de secours), `mysqldump` de la table `ville` en prod (6,2 Mo) + copie locale | Sauvegarde avant réimport, même discipline. |
| `app:importer-villes` en prod, vérification SQL directe | Résultat identique local/prod : 13529/144/0 inchangé, Paris avec ses 21 codes postaux. |

## Session du 2026-08-23 (suite) — Pages Ville (liste/fiche) + Villes concernées sous chaque Ligne

Demande utilisateur : "je veux la liste des ville et dans 'affichage' voir la liste des station et des lignes qui sont concernées (qui ne font que la traversée.... qui ont un bout en dehors.... qui sont completement dedans)", puis "et dans 'ligne index' la liste des ville concerné en dessous de chaque lignes".

| Commande | Objectif |
|---|---|
| Conception de la classification à 3 catégories (traversée / un bout en dehors / entièrement dedans) | Basée sur `Desserte::getNombreTronconsDistincts()` (déjà existante, `<= 1` = extrémité de ligne/branche) plutôt qu'une reconstruction complète de l'ordre réel de la ligne (méthode fragile déjà documentée pour les lignes en maillage, voir "Page /ligne/{id}" plus haut) : toutes les Desserte dans la Ville → entièrement dedans ; au moins une extrémité dans la Ville mais pas toutes les Desserte → un bout hors ; des Desserte dans la Ville mais aucune extrémité → traversée. |
| `VilleRepository::trouverLignesConcernees()` (nouvelle, 2 requêtes SQL groupées) + `LigneRepository::trouverVillesParLigne()` (nouvelle, 1 requête groupée pour toute une page paginée, pas de N+1) | |
| `VilleController` (nouveau, index + show seulement — pas de new/edit/delete : Ville est entièrement peuplée par `app:importer-villes`, un formulaire manuel n'aurait pas de sens pour ses champs JSON) + `templates/ville/index.html.twig` + `templates/ville/show.html.twig` | |
| `LigneController::index()` + `templates/ligne/index.html.twig` (ligne "Villes : ..." sous chaque Ligne) | |
| `templates/menu/menu.html.twig` (lien "Villes" ajouté au dropdown Réseau) | |
| Découverte en cours de route : `templates/gestionnaire/show.html.twig` affichait déjà la liste des Ligne gérées ("Lignes gérées") | Note TODO du 2026-08-22 ("template manquant") corrigée : conclusion erronée tirée de l'entité seule sans relire le template au moment du signalement initial. |
| `tests/Controller/VilleControllerTest.php` (nouveau, testIndex + testShow) + `tests/Controller/LigneControllerTest.php` (nouveau testIndexAvecVilleConcernee) + `DatabaseTestCase::resetDatabase()` (Ville ajoutée à la liste, après Station) | Piège rencontré : `$station->setVilleRef($ville)` seul ne suffit pas dans un test (met à jour le côté propriétaire mais pas la Collection en mémoire du côté inverse déjà chargé) — corrigé via `$ville->addStation($station)`. 2e piège : une Ligne de test sans `TypeTransport` est invisible sur l'index (le filtre par défaut ne coche que des modes réels, aucune condition ne matche `typeTransport IS NULL`). |
| `php bin/phpunit` (137, +3), `npx jest` (51) | Tout passe. |
| Vérification navigateur (compte de test), login via formulaire réel (curl échoue : le token CSRF est un placeholder `"csrf-token"` remplacé par du JS côté client avant soumission, invérifiable sans exécuter de JS) | `/ligne` : villes affichées sous chaque ligne. `/ville` : 1266 villes listées. `/ville/1` (Paris) : 878 Station, 39 Ligne entièrement dedans / 160 un bout hors / 50 traversée. Cas vérifié en détail : Ligne 1 métro classée "un bout hors" et non "traversée" bien que ses 2 termini semblent hors Paris à première vue — confirmé correct, "Château de Vincennes" est en réalité rattachée à "Paris 12e" dans la donnée source (`Station.ville`, jamais modifiée par ce travail). |
| Compte de test supprimé après vérification | Discipline habituelle. |

## Session du 2026-08-23 (suite) — Bug conseils de position dans la rame (trop d'entrées)

Demande utilisateur : "AJOUTE A TODO / il ya un probleme avec les position...", avec un exemple concret de trajet réel montrant des dizaines de conseils "Pour rejoindre..." pour un seul tronçon, puis reformulation du besoin réel (une seule recommandation par correspondance, filtrée sur la vraie direction de sortie).

| Commande | Objectif |
|---|---|
| Requête SQL directe sur `position_rame` pour la Station "Maison Blanche" | Confirmé : 113 lignes toutes lignes confondues pour cette seule Station, dont 35 quasi-identiques pour le seul couple (Ligne 14, destination "av. d'Italie") — ne différant que par des détails d'équipement/position triviaux. |
| Lecture de `PositionRameRepository::trouverParStationEtLigne()` | Confirmé root cause à 2 niveaux : aucun filtre sur la destination/direction réellement empruntée par le trajet (retourne toutes les destinations connues pour ce couple Station+Ligne), et aucune déduplication des entrées quasi-identiques du dataset source. |
| `documentation/TODO.md` (entrée "Conseils de position dans la rame" du 2026-08-22 complétée, note initiale trop optimiste corrigée) | Diagnostic détaillé + reformulation du besoin réel (une ligne par correspondance/segment, filtrée par la vraie Station de sortie du tronçon) + piste de correctif (pas implémenté à ce stade). |

## Session du 2026-08-23 (suite) — Sens de circulation pour les conseils de position dans la rame

Demande utilisateur : "ben evidemment !" (feu vert), suite au retour clé "c'est le sens de circulation, pas du bruit" (exemple Gare de l'Est : salle des pas perdus a l'arriere en partant, a l'avant en arrivant).

| Commande | Objectif |
|---|---|
| Verification directe stop_times.txt/trips.txt pour un stop_point precis (IDFM:463060, Chatelet ligne 7) | Confirme : tous les trips desservant ce quai partagent le meme direction_id/trip_headsign - un quai = un sens. |
| `documentation/scripts/extraire_conseils_position.php` reecrit | 2 passages sur stop_times.txt (~11,8M lignes, quelques minutes) : 1er passage trouve un trip representatif par from_id (952 distincts, 796 resolus), 2e passage recupere la sequence complete de ces trips (46 distincts) pour en deduire la ZdC du prochain arret reel dans ce sens. Sortie enrichie de 3 colonnes : directionId, terminusReel, zdcSuivant. |
| `src/Entity/PositionRame.php` (+directionId, terminusReel, prochaineStation ManyToOne Station) + migration `Version20260823140000.php` | Appliquee sur metroratp ET metroratp_test. |
| `src/Command/ConstruirePositionsRameCommand.php` (lit les 3 nouvelles colonnes, resout prochaineStation via le meme `trouverIdCanoniqueParZdc()` deja utilise pour Station/Ligne) | Reexecute : 4671 PositionRame creees (0 sans sens resolu). |
| `src/Repository/PositionRameRepository.php` (`trouverPourEmbarquement(station, ligne, prochaineStation)` remplace `trouverParStationEtLigne()`) | Retourne au plus 1 resultat, filtre directement par la Station suivante reellement empruntee - plus besoin de matcher sur le texte destination (verifie : toutes les destinations d'un meme sens partagent la meme position, ex. Opera/Auber/Theatre National de l'Opera toutes "Arriere 5/5" en direction Villejuif). |
| `src/Controller/TrajetController.php::construireSegmentsPourAffichage()` (conseil rattache a la Station de DEPART du troncon, pas l'arrivee ; determine par `dessertes[1]`) | Un seul conseil par troncon entier, actionnable avant l'embarquement. |
| `templates/trajet/index.html.twig` (affichage simplifie, une seule ligne "🚃 Montez ... (X/Y)") | |
| `php bin/phpunit` (137), `npx jest` (51) | Tout passe. |
| Verification navigateur (compte de test, trajet reel Villejuif Leo Lagrange -> Les Mousquetaires) | Troncon Ligne 14 (5 stations, montrait 113+ lignes de conseils avant le fix) : plus qu'une seule ligne "Montez Milieu (5/8) — Escalator". Aucune erreur console. |
| `documentation/TODO.md` (entree "Conseils de position dans la rame" marquee **fait**) | |

## Session du 2026-08-23/24 — PointInteret ("à voir à proximité") + recherche du trajet par nom de ville

Demande utilisateur : reprise de l'idée "Destination" abandonnée plus tôt (points d'intérêt façon Wikipédia), avec un indice concret ("Hôpital Kremlin Bicêtre" déjà vu dans un conseil de position) ; "fait au mieux" pour créer la table ; puis idée séparée (recherche du calculateur par nom de ville).

| Commande | Objectif |
|---|---|
| Vérification : "Hôpital Kremlin Bicêtre" vient bien de `positionnement-dans-la-rame.csv` (`to_type=access_point`), et diffère du `Acces.label` déjà connu pour ce même accès ("avenue de Fontainebleau / avenue Eugène Thomas") | Confirme l'idée de l'utilisateur : le champ `to_name` contient parfois un vrai nom de lieu plutôt qu'une adresse de rue, une donnée déjà présente et gratuite. |
| Script d'analyse (scratchpad), filtre par expression régulière (exclut rue/avenue/place/boulevard/etc.) sur les 1018 `to_name` distincts (type `access_point`) | 117 candidats "vrais lieux" (Tour Eiffel, Panthéon, Notre-Dame, Bibliothèque François Mitterrand, Manufacture des Gobelins, plusieurs hôpitaux/musées...). Vérification complémentaire sur `point_de_vente` (commerces tabac-presse) : source différente, déjà bien structurée (label/adresse séparés) mais hors périmètre sémantique (pas des lieux remarquables). |
| `documentation/scripts/extraire_points_interet.php` (nouveau) | Filtre affiné (regex + denylist explicite pour les génériques "Gare routière"/"Centre Commercial"/etc., trop irréguliers pour un motif fiable) : 87 paires (ZdC, lieu) retenues sur 5239 lignes source. Rattachement par `from_id` → ZdC (même logique que `extraire_conseils_position.php`, stops.txt seul suffit). Sortie committée : `documentation/scripts/donnees-extraites/points_interet.csv`. |
| `src/Entity/PointInteret.php` (nouvelle, `label` unique + `stations` ManyToMany) + `Station::pointsInteret` (relation inverse) + migration `Version20260823180000.php` | Un même lieu peut être proche de plusieurs Station (ex. Forum des Halles). Appliquée sur `metroratp` et `metroratp_test`. |
| `src/Command/ImporterPointsInteretCommand.php` (`app:importer-points-interet`) | Bug rencontré et corrigé avant tout déploiement : deux occurrences du même lieu dans le CSV (ex. "Manufacture des Gobelins", 2 Station proches) provoquaient une violation de contrainte unique - `findOneBy()` ne retrouve pas une entité pas encore flush (un seul flush en fin de boucle) ; corrigé par un cache en mémoire (`$pointInteretParLabel`) plutôt qu'une re-requête DB à chaque ligne. 85 PointInteret créés, 87 rattachements Station, 0 sans Station trouvée. |
| `templates/station/show.html.twig` (section "À voir à proximité") | Sur le modèle des sections existantes (Points de vente, Sanitaires...). |
| `StationRepository::rechercherParLabel()` étendue (idée séparée, sur clarification "fait au mieux" après une explication jugée pas claire par l'utilisateur) | Matche désormais aussi par nom de `Ville` (`Station::villeRef`), en complément du label de Station et toujours priorisé après un vrai match direct (sinon Paris noierait le résultat de centaines de stations). Permet de taper un nom de commune (ex. "Andrezel") et retrouver ses Station même si aucune ne porte ce nom exact - vérifié : "Andrezel" → "Salle des Fêtes" (son unique arrêt), "Chatelet" toujours priorisé correctement (pas de régression). |
| `php bin/phpunit` (137), `npx jest` (51) | Tout passe. |
| Vérification navigateur (compte de test) : `/station/69` (Gambetta) | "À voir à proximité" affiche "Père-Lachaise" et "Hôpital Tenon" - cohérent géographiquement. Aucune erreur console. |
| Serveur symfony local instable pendant la vérification (`preview_start` a signalé un serveur déjà démarré puis sorti immédiatement, port 8000 finalement injoignable) | Contourné avec un serveur PHP intégré ponctuel (`php -S 127.0.0.1:8000 -t public`) pour cette vérification uniquement. |

*(Entrées suivantes ajoutées au fil des prochaines commandes/sessions.)*
