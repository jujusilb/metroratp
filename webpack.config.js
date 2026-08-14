const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
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