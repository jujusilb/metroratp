<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Retient en session la derniere page "de contenu" visitee (avant la page courante), pour
 * afficher un bouton "Retour" universel sur toutes les pages (voir base.html.twig). Base sur la
 * session plutot que sur l'historique du navigateur (history.back()) : survit a un rechargement
 * de page, fonctionne meme en cas d'ouverture dans un nouvel onglet, et ne depend pas de la pile
 * de navigation JS (qui inclurait les redirections post-formulaire, moins utile ici).
 *
 * Exclut volontairement : requetes non-GET (POST de formulaire, jamais une "page" a retenir),
 * routes techniques (_wdt/_profiler, connexion/deconnexion) et sous-requetes (fragments Twig),
 * pour ne stocker que de vraies navigations de page en page.
 */
class DernierePageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->isMethod('GET')) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route');
        if ('' === $route || str_starts_with($route, '_') || \in_array($route, ['app_login', 'app_logout'], true)) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $urlCourante = $event->getRequest()->getRequestUri();
        $dernierePage = $session->get('derniere_page');

        // Ne pas ecraser avec l'URL courante si on est deja dessus (double chargement,
        // rafraichissement) - sinon "Retour" ramenerait sur la page qu'on regarde deja.
        if ($dernierePage !== $urlCourante) {
            $session->set('page_precedente', $dernierePage);
        }
        $session->set('derniere_page', $urlCourante);
    }
}
