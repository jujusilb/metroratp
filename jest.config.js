// Config Jest dediee, independante de la config babel du build Encore/webpack (assets/ n'a pas
// de babel.config.json a la racine expres : babel-loader le detecterait aussi et remplacerait
// la configuration interne d'Encore pour le build de production).
module.exports = {
    testEnvironment: 'jsdom',
    testMatch: ['**/assets/**/*.test.js'],
    transform: {
        '^.+\\.js$': [
            'babel-jest',
            { presets: [['@babel/preset-env', { targets: { node: 'current' } }]] },
        ],
    },
};
