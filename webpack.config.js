const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    // L'app est servie a la racine en dev local (127.0.0.1:8000) mais sous le sous-repertoire
    // /metroratp en prod (julien-silberstein.fr/metroratp, symlink vers public_html/metroratp) :
    // sans ce prefixe conditionnel, les URLs generees par Encore (entry_link_tags/script_tags)
    // pointent vers https://julien-silberstein.fr/build/... (404, hors du site) au lieu de
    // https://julien-silberstein.fr/metroratp/build/... - repere en prod le 2026-08-18 : le site
    // entier (CSS/JS) etait casse pour tout visiteur sans cache navigateur prealable sur les
    // anciens hash de fichiers.
    .setPublicPath(Encore.isProduction() ? '/metroratp/build' : '/build')
    // Sans ca, Encore ne sait plus deriver le prefixe des cles de manifest.json depuis
    // publicPath/outputPath des que les deux ne partagent plus le meme dernier segment
    // (a cause du prefixe /metroratp ci-dessus) : erreur "Cannot determine how to prefix
    // the keys in manifest.json" au build de prod.
    .setManifestKeyPrefix('build')
    .addEntry('app', './assets/app.js')
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    // Versionne meme en dev (pas seulement Encore.isProduction()) : sans ca, le serveur PHP
    // integre (symfony server:start) ne renvoie aucun header Cache-Control/ETag sur les fichiers
    // statiques, donc le navigateur applique un cache heuristique base sur Last-Modified et peut
    // servir un app.js perime pendant des heures malgre des rebuilds successifs (verifie
    // 2026-08-13 : app.js reste sur une version d'la veille malgre plusieurs `encore dev`).
    .enableVersioning(true)
    .enableSassLoader()
;

module.exports = Encore.getWebpackConfig();