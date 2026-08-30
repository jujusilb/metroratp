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
        'station' => ['Réseau', 'app_station_index', 'Stations'],
        'pole_echange' => ['Réseau', 'app_pole_echange_index', "Pôles d'échange"],
        'desserte' => ['Réseau', 'app_desserte_index', 'Dessertes'],
        'periode_ouverture' => ['Réseau', 'app_periode_ouverture_index', "Périodes d'ouverture"],
        'correspondance' => ['Réseau', 'app_correspondance_index', 'Correspondances'],
        'troncon' => ['Réseau', 'app_troncon_index', 'Tronçons'],
        'direction' => ['Réseau', 'app_direction_index', 'Directions'],
        'troncon_desserte' => ['Réseau', 'app_troncon_desserte_index', 'Tronçons-dessertes'],
        'type_troncon' => ['Réseau', 'app_type_troncon_index', 'Types de tronçon'],
        'document_ligne' => ['Réseau', 'app_ligne_index', 'Lignes'],

        // Équipement
        'sanitaire' => ['Équipement', 'app_sanitaire_index', 'Toilettes'],
        'sanisette_publique' => ['Équipement', 'app_sanisette_publique_index', 'Sanisettes publiques'],
        'defibrillateur' => ['Équipement', 'app_defibrillateur_index', 'Défibrillateurs'],
        'fontaine_eau' => ['Équipement', 'app_fontaine_eau_index', 'Fontaines à eau'],
        'style_station' => ['Équipement', 'app_style_station_index', 'Styles'],
        'style_acces' => ['Équipement', 'app_style_acces_index', 'Styles'],
        'style_ecriture' => ['Équipement', 'app_style_ecriture_index', 'Styles'],
        'equipement_arret' => ['Équipement', 'app_equipement_arret_index', 'Équipements des arrêts'],

        // Lieux
        'ville' => ['Lieux', 'app_ville_index', 'Villes'],
        'plan' => ['Lieux', 'app_plan_index', 'Plans de secteur'],
        'plan_region' => ['Lieux', 'app_plan_region_index', 'Plans régionaux'],
        'point_de_vente' => ['Lieux', 'app_point_de_vente_index', 'Points de vente'],
        'point_interet' => ['Lieux', 'app_point_interet_index', "Points d'intérêt"],

        // Accès
        'acces' => ['Accès', 'app_acces_index', 'Accès'],
        'position_rame' => ['Accès', 'app_position_rame_index', 'Conseils de position'],

        // Matériel
        'materiel' => ['Matériel', 'app_materiel_index', 'Matériel'],
        'depot' => ['Matériel', 'app_depot_index', 'Dépôts'],
        'type_materiel' => ['Matériel', 'app_type_materiel_index', 'Types de matériel'],

        // Exploitation
        'mission' => ['Exploitation', 'app_mission_index', 'Missions'],
        'service' => ['Exploitation', 'app_service_index', 'Services'],
        'gestionnaire' => ['Exploitation', 'app_gestionnaire_index', 'Gestionnaires'],
        'raison' => ['Exploitation', 'app_raison_index', "Raisons d'inactivité"],
        'type_transport' => ['Exploitation', 'app_type_transport_index', 'Types de transport'],
        'type_desserte' => ['Exploitation', 'app_type_desserte_index', 'Types de desserte'],
        'projet_arret' => ['Exploitation', 'app_projet_arret_index', "Projets d'arrêts"],

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
