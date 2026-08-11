# Rapport de bugs — Metro RATP

Scan du site effectué le 2026-08-10/11 : lecture du code (contrôleurs, formulaires, templates,
JS) + tests en conditions réelles sur le site de production (https://julien-silberstein.fr) et sur
une copie locale remise en état pour l'occasion. Chaque bug ci-dessous est vérifié dans le code
(fichier + ligne), pas juste supposé.

Coché = corrigé. Remplissez au fur et à mesure.

Légende gravité : 🔴 Élevée · 🟠 Moyenne · 🟡 Faible / confort

---

## 1. Bugs que tu avais déjà repérés

### [ ] 🔴 Inscription : aucune vérification/confirmation du mot de passe saisi

**Fichiers** : [UtilisateurType.php](../src/Form/UtilisateurType.php) (le formulaire est partagé
entre inscription et édition de profil), utilisé par
[InscriptionController.php](../src/Controller/InscriptionController.php).

Le formulaire d'inscription n'a **qu'un seul champ mot de passe** (`plainPassword`,
`UtilisateurType.php:36-46`). Il n'existe pas de deuxième champ « confirmer le mot de passe », donc
rien ne détecte une faute de frappe : l'utilisateur peut créer un compte avec un mot de passe qu'il
n'a jamais réellement tapé (ex: "Azerty123" tapé "Azerty1234" par erreur), et ne le découvre qu'au
moment de se reconnecter — sans aucun moyen de deviner ce qu'il a réellement tapé.

Il y a bien une validation serveur sur la longueur (`NotBlank` + `Length(min: 8, max: 4096)`,
`UtilisateurType.php:43-45`), donc un mot de passe trop court est refusé — mais rien n'indique cette
règle des 8 caractères minimum *avant* de soumettre : le champ n'a pas de texte d'aide pour
l'inscription (`UtilisateurType.php:40-42` : le `help` n'est affiché que pour l'édition de profil,
pas pour la création de compte) ni d'attribut HTML `minlength` pour un retour immédiat du
navigateur.

**Pistes de correction** :
- Ajouter un second champ « Confirmer le mot de passe » (contrainte `EqualTo` sur le premier champ,
  ou une constraint `Callback`/`IsTrue` au niveau du formulaire).
- Afficher la règle des 8 caractères minimum en aide de champ, même à l'inscription.

### [x] 🟠 Calculateur de trajet : impossible de voir le mode de transport d'une station dans les suggestions — **corrigé le 2026-08-11**

**Fichiers** : [trajet-autocomplete.js](../assets/js/trajet-autocomplete.js),
[app.scss](../assets/styles/app.scss).

Avant : chaque suggestion principale n'affichait que le **nom** de la station — aucune couleur de
ligne, icône ou libellé de mode (Métro/Bus/RER...). Le mode n'apparaissait qu'en sous-option
(« → RER uniquement »), et seulement si la station était desservie par plusieurs modes.

**Corrigé** : chaque ligne de suggestion affiche maintenant le mode à gauche (pastille "MÉTRO" /
"RER" / "BUS RATP" / "TOUS"...) et le nom de la station (+ ville) à droite, séparés par un filet
vertical. Une station à un seul mode (parmi ceux cochés) a une seule ligne étiquetée avec ce mode ;
une station à plusieurs modes a une ligne par mode plus une ligne "Tous". Testé avec 1, 3 et 5 cases
"Modes de transport" cochées : le filtrage continue de fonctionner correctement dans tous les cas.
Tests Jest mis à jour en conséquence.

### [x] 🔴 Bus : sélectionnables dans le calculateur mais aucun trajet n'était réellement possible — **partiellement corrigé le 2026-08-11**

**Fichiers** : [ConstruireTopologieBusCommand.php](../src/Command/ConstruireTopologieBusCommand.php),
[ConstruireTopologieBusAutresOperateursCommand.php](../src/Command/ConstruireTopologieBusAutresOperateursCommand.php).

Avant : aucun tronçon construit pour aucune ligne de bus, donc tout calcul de trajet en bus
renvoyait systématiquement « Aucun trajet trouvé », sans que l'utilisateur sache si c'est normal ou
si la fonctionnalité n'est juste pas implémentée.

**Corrigé pour les lignes numérotées 20 à 299** : 20-100 toutes compagnies confondues (RATP +
Keolis Roissy/Argenteuil + Transdev Boucle des Lys/Vallée du Loing/Nord Seine-Saint-Denis/Côteaux
de la Marne + Keolis Nord Val d'Oise), 101-299 uniquement RATP/filiales "RATP Cap ..." — soit
212 lignes au total (~5250 tronçons). Tronçons extraits du GTFS IDFM et construits en base, testé
de bout en bout avec `TrajetFinder` sur plusieurs lignes. **Pas encore fait** : les lignes non-RATP
de 101-299 (ATM Croix du Sud, Keolis Grand Paris Vallée de la Marne/Argenteuil/Ouest Val-de-Marne),
et le reste du réseau bus (~1300 lignes hors 20-299) — voir `documentation/TODO.md`, la méthode
est réutilisable pour étendre à d'autres plages plus tard.

---

## 2. Bugs supplémentaires trouvés pendant le scan

### [ ] 🔴 Aucune pagination sur les pages de liste d'administration — certaines chargent des dizaines de milliers de lignes d'un coup

**Fichiers** : tous les contrôleurs CRUD (`StationController.php:21`, `DesserteController.php:21`,
`TronconController.php:21`, `CorrespondanceController.php:21`, `AccesController.php:21`,
`SortieController.php:21`, `LigneController.php:21`, `UtilisateurController.php:24`, etc.).

Chaque page `/xxx` (liste) charge **toute la table** en une seule requête (`findAll()` ou
`findAllWithDetails()`) et l'affiche dans un grand tableau HTML sans aucune pagination. Sur les
grosses tables, ça représente :

| Page | Nombre de lignes chargées d'un coup |
|---|---|
| `/desserte` | **31 787** |
| `/station` | **14 244** |
| `/troncon` | 2 806 |
| `/ligne` | 1 459 |
| `/acces`, `/sortie` | 1 068 chacune |
| `/correspondance` | 577 |

`/desserte` et `/station` en particulier sont concrètement inutilisables tels quels (requête SQL +
hydratation Doctrine + rendu HTML de dizaines de milliers de lignes à chaque chargement de page —
lent, et le tableau lui-même est impossible à parcourir sans un simple Ctrl+F du navigateur).

Point notable : **`KnpPaginatorBundle` est installé et activé** (`config/bundles.php`,
`composer.json`) mais n'est utilisé **nulle part** dans le code (aucune occurrence de
`PaginatorInterface` ou de `knp_paginator` dans `src/`) — la brique existe déjà, elle n'est juste
pas branchée.

**Pistes de correction** : utiliser `KnpPaginatorBundle` (déjà en dépendance) sur les listes des
grosses tables, au minimum `/desserte`, `/station`, `/troncon`, en priorité.

### ~~🟡 `base.html.twig` inclut le bundle JS de l'app deux fois~~ — faux positif, retiré

**Correction du 2026-08-11 après-coup** : en comparant avec un clone propre de
`github.com/jujusilb/metroratp` (branche `main`), il s'est avéré que `base.html.twig` sur GitHub
n'a **jamais eu** ce doublon ni l'appel `importmap('app')` — un seul `encore_entry_script_tags('app')`,
comme il se doit. La copie locale utilisée pour ce scan (dossier Desktop, distinct du vrai checkout
Git) avait dérivé du dépôt réel sur plusieurs fichiers (`base.html.twig`, `assets/app.js`,
`assets/stimulus_bootstrap.js`, `webpack.config.js`, `package.json` avaient tous des différences
qui n'existent pas sur GitHub). L'avalanche d'erreurs 500 rencontrée le 2026-08-11 venait de cette
dérive locale, pas d'un bug réellement présent dans le code versionné — rien à corriger ici. Voir
`commande.md` pour le détail de la comparaison.

### [ ] 🟡 Case de mode décochée après avoir choisi une station « verrouillée » sur ce mode : incohérence possible

**Fichiers** : [TrajetFinder.php:150-175](../src/Service/TrajetFinder.php#L150-L175),
[trajet-autocomplete.js:64-76](../assets/js/trajet-autocomplete.js#L64-L76)

Quand on choisit une sous-suggestion type « → RER uniquement », le champ caché `origineMode`/
`destinationMode` se fixe à `rer` peu importe l'état des cases « Modes de transport ». Côté serveur,
`dessertesIdsPourStation()` avec un `$modeForce` **ignore complètement** `$modesAutorises`
(`TrajetFinder.php:162-167` : le `continue` sort de la boucle avant même de regarder les modes
cochés). Concrètement : si on choisit "Nation — RER" puis qu'on décoche la case "RER" avant de
lancer le calcul, le trajet peut quand même démarrer/finir en RER à cette station précise, alors que
la case RER est visuellement décochée — un comportement contre-intuitif (même si le commentaire du
code, `TrajetFinder.php:57-63`, indique que c'est voulu). À minima, ça vaudrait le coup de
resynchroniser/effacer le mode forcé si l'utilisateur décoche ensuite la case correspondante, pour
éviter la surprise.

---

### ~~🟠 Turbo peut geler les pages arrivées par redirection~~ — faux positif, retiré

**Correction du 2026-08-11 après-coup** : ce "bug" reposait sur `assets/stimulus_bootstrap.js`
important `@symfony/stimulus-bridge` et sur `assets/app.js` important `./stimulus_bootstrap.js` —
or aucun des deux n'existe dans le vrai dépôt GitHub. `assets/app.js` (le vrai) n'importe pas
`stimulus_bootstrap.js` du tout, et `webpack.config.js` (le vrai) n'appelle jamais
`.enableStimulusBridge()`. Turbo/Stimulus ne sont donc **pas branchés** dans le build réel du site :
l'observation faite pendant cette session venait d'une configuration fabriquée localement (voir
entrée ci-dessus), pas d'un comportement du vrai site. Rien à corriger ici.

## Résumé — par où commencer

Par ordre d'impact utilisateur suggéré :

1. **Pagination des listes** (surtout `/desserte` et `/station`) — le plus bloquant en usage réel.
2. **Confirmation du mot de passe à l'inscription** — risque concret de compte bloqué par une faute
   de frappe.
3. ~~Indication du mode de transport dans l'autocomplétion du trajet~~ — **fait**.
4. **Bus non routables** — gros chantier déjà identifié dans le TODO, à planifier à part plutôt qu'à
   "corriger" rapidement.
5. Incohérence mode forcé/case décochée — faible impact, à faire quand il y a un moment.

*(Scan effectué en lisant le code + navigation réelle sur https://julien-silberstein.fr ; le
calculateur de trajet en production nécessite d'être connecté, donc son comportement exact
(recherche de station, affichage carte) a été vérifié via le code plutôt qu'en conditions réelles —
si un des points ci-dessus ne correspond pas à ce que tu observes une fois connecté, dis-le pour que
je recreuse.)*
