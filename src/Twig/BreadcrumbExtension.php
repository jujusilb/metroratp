<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Genere le fil d'Ariane de chaque page a partir du seul nom de route courante (convention
 * "app_<entite>_<action>" respectee partout dans ce projet) - evite de devoir ajouter des
 * donnees de fil d'Ariane a la main sur chacun des ~150 templates existants. Le libelle du
 * dernier maillon (l'element courant sur une page show/edit/new) est repris du bloc Twig
 * "title" deja defini par chaque template (block('title')), lui aussi deja existant partout.
 */
class BreadcrumbExtension extends AbstractExtension
{
    /**
     * [prefixe de route => [section, route d'index, libelle de la liste]].
     * Section vide = entite hors des sous-menus (Utilisateurs, Suivi projet en acces direct).
     */
    private const ENTITES = [
        // Réseau
        'ligne' => ['Réseau', 'app_ligne_index', 'Lignes'],
        'type_transport' => ['Réseau', 'app_type_transport_index', 'Types de transport'],
        'gestionnaire' => ['Réseau', 'app_gestionnaire_index', 'Gestionnaires'],
        'station' => ['Réseau', 'app_station_index', 'Stations'],
        'raison' => ['Réseau', 'app_raison_index', "Raisons d'inactivité"],
        'ville' => ['Réseau', 'app_ville_index', 'Villes'],
        'plan' => ['Réseau', 'app_plan_index', 'Plans de secteur'],
        'plan_region' => ['Réseau', 'app_plan_region_index', 'Plans régionaux'],
        'pole_echange' => ['Réseau', 'app_pole_echange_index', "Pôles d'échange"],
        'point_de_vente' => ['Réseau', 'app_point_de_vente_index', 'Points de vente'],
        'sanitaire' => ['Réseau', 'app_sanitaire_index', 'Toilettes'],
        'sanisette_publique' => ['Réseau', 'app_sanisette_publique_index', 'Sanisettes publiques'],
        'defibrillateur' => ['Réseau', 'app_defibrillateur_index', 'Défibrillateurs'],
        'fontaine_eau' => ['Réseau', 'app_fontaine_eau_index', 'Fontaines à eau'],
        'equipement_arret' => ['Réseau', 'app_equipement_arret_index', 'Équipements des arrêts'],
        'projet_arret' => ['Réseau', 'app_projet_arret_index', "Projets d'arrêts"],
        'desserte' => ['Réseau', 'app_desserte_index', 'Dessertes'],
        'periode_ouverture' => ['Réseau', 'app_periode_ouverture_index', "Périodes d'ouverture"],
        'correspondance' => ['Réseau', 'app_correspondance_index', 'Correspondances'],
        'troncon' => ['Réseau', 'app_troncon_index', 'Tronçons'],
        'type_troncon' => ['Réseau', 'app_type_troncon_index', 'Types de tronçon'],
        'style_station' => ['Réseau', 'app_style_station_index', 'Styles'],
        'style_acces' => ['Réseau', 'app_style_acces_index', 'Styles'],
        'style_ecriture' => ['Réseau', 'app_style_ecriture_index', 'Styles'],
        'document_ligne' => ['Réseau', 'app_ligne_index', 'Lignes'],

        // Accès
        'acces' => ['Accès', 'app_acces_index', 'Accès'],
        'sortie' => ['Accès', 'app_sortie_index', 'Sorties'],
        'position_rame' => ['Accès', 'app_position_rame_index', 'Conseils de position'],

        // Matériel
        'materiel' => ['Matériel', 'app_materiel_index', 'Matériel'],
        'materiel_ligne' => ['Matériel', 'app_materiel_ligne_index', 'Matériel-Ligne'],
        'type_materiel' => ['Matériel', 'app_type_materiel_index', 'Types de matériel'],

        // Exploitation
        'mission' => ['Exploitation', 'app_mission_index', 'Missions'],
        'service' => ['Exploitation', 'app_service_index', 'Services'],

        // Suivi projet
        'tache' => ['Suivi projet', 'app_tache_index', 'Tâches'],
        'statut_tache' => ['Suivi projet', 'app_statut_tache_index', 'Statuts de tâche'],
        'etape' => ['Suivi projet', 'app_tache_index', 'Tâches'],

        // Acces direct (pas de sous-menu)
        'utilisateur' => ['', 'app_utilisateur_index', 'Utilisateurs'],
    ];

    private const ACTIONS_SANS_LIBELLE_PROPRE = ['index'];

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('breadcrumb_items', $this->buildBreadcrumbs(...)),
            new TwigFunction('page_precedente', $this->pagePrecedente(...)),
        ];
    }

    /**
     * URL de la page visitee juste avant la page courante (voir DernierePageSubscriber), pour le
     * bouton "Retour" universel. Null si aucune (premiere page de la session).
     */
    public function pagePrecedente(): ?string
    {
        $session = $this->requestStack->getSession();

        return $session->get('page_precedente');
    }

    /**
     * @return list<array{label: string, route: ?string}>
     */
    public function buildBreadcrumbs(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $items = [['label' => 'Accueil', 'route' => 'app_trajet_index']];

        if (null === $request) {
            return $items;
        }

        $route = (string) $request->attributes->get('_route');
        if (!preg_match('/^app_(.+)_(index|show|new|edit)$/', $route, $m)) {
            return $items;
        }
        [, $prefixe, $action] = $m;

        if (!isset(self::ENTITES[$prefixe])) {
            return $items;
        }
        [$section, $routeIndex, $libelleListe] = self::ENTITES[$prefixe];

        if ('' !== $section) {
            $items[] = ['label' => $section, 'route' => null];
        }

        $estSurLIndex = 'index' === $action;
        $items[] = ['label' => $libelleListe, 'route' => $estSurLIndex ? null : $routeIndex];

        if (!\in_array($action, self::ACTIONS_SANS_LIBELLE_PROPRE, true)) {
            $items[] = ['label' => null, 'route' => null];
        }

        return $items;
    }
}
