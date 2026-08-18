const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    // ATTENTION avant de retoucher ce chemin : le site de prod a DEUX points d'entree distincts
    // vers la meme app - le sous-domaine metroratp.julien-silberstein.fr (document root = ce
    // depot directement, '/build' correct) ET le sous-repertoire julien-silberstein.fr/metroratp
    // (symlink public_html/metroratp -> metroratp-app/public, aurait besoin de '/metroratp/build').
    // Le 2026-08-18, un premier correctif avait bascule ce chemin en conditionnel
    // ('/metroratp/build' en prod) pour reparer le sous-repertoire - repere ensuite AVEC PERTES
    // (l'utilisateur a signale "la page est cassee" sur le sous-domaine, casse par ce changement) :
    // le sous-domaine est le point d'entree reellement utilise, donc reverti a '/build'
    // inconditionnel. Le sous-repertoire /metroratp reste casse (memes assets 404) mais rien
    // n'indique qu'il soit reellement utilise - voir documentation/commande.md avant de re-tenter
    // un correctif base sur l'hypothese inverse.
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