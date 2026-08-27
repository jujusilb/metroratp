<?php

namespace App\Tests\Controller;

use App\Entity\Acces;
use App\Entity\Correspondance;
use App\Entity\Desserte;
use App\Entity\Direction;
use App\Entity\Gestionnaire;
use App\Entity\Ligne;
use App\Entity\Materiel;
use App\Entity\MaterielLigne;
use App\Entity\Mission;
use App\Entity\PeriodeOuverture;
use App\Entity\Raison;
use App\Entity\Service;
use App\Entity\Sortie;
use App\Entity\Station;
use App\Entity\StyleEcriture;
use App\Entity\StyleStation;
use App\Entity\Troncon;
use App\Entity\TronconDesserte;
use App\Entity\TypeDesserte;
use App\Entity\TypeMateriel;
use App\Entity\TypeTransport;
use App\Entity\TypeTroncon;
use App\Entity\Utilisateur;
use App\Entity\Ville;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Chaque test controleur ne nettoie que "sa" table dans son propre setUp(), mais les entites
 * de ce projet sont fortement liees entre elles (Desserte -> Ligne/Station, Mission -> Service/
 * TronconDesserte, etc.). Si une classe de test laisse des fixtures en base et qu'une autre classe
 * tente ensuite de vider une table referencee par une contrainte de cle etrangere, la suppression
 * echoue. Cette classe de base videe TOUTES les tables, dans l'ordre enfants -> parents, avant
 * chaque test, pour garantir un etat propre quel que soit l'ordre d'execution des classes.
 */
abstract class DatabaseTestCase extends WebTestCase
{
    protected function resetDatabase(EntityManagerInterface $manager): void
    {
        $entityClasses = [
            Utilisateur::class,
            Raison::class,
            Mission::class,
            TronconDesserte::class,
            MaterielLigne::class,
            Sortie::class,
            PeriodeOuverture::class,
            Correspondance::class,
            Direction::class,
            Desserte::class,
            Materiel::class,
            Troncon::class,
            Acces::class,
            Service::class,
            TypeDesserte::class,
            Station::class,
            Ville::class,
            Ligne::class,
            TypeTransport::class,
            Gestionnaire::class,
            StyleStation::class,
            StyleEcriture::class,
            TypeMateriel::class,
            TypeTroncon::class,
        ];

        foreach ($entityClasses as $entityClass) {
            foreach ($manager->getRepository($entityClass)->findAll() as $object) {
                $manager->remove($object);
            }
        }

        $manager->flush();
    }

    /**
     * Le site est verrouille derriere une connexion (voir security.yaml : tout /new, /edit et
     * les requetes POST necessitent ROLE_ADMIN, le reste ROLE_USER). Se connecter en admin
     * couvre les deux cas et suffit pour les tests fonctionnels existants.
     */
    protected function connecterEnAdmin(KernelBrowser $client, EntityManagerInterface $manager): Utilisateur
    {
        $admin = new Utilisateur();
        $admin->setUsername('admin_test');
        $admin->setEmail('admin_test@example.test');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($admin, 'peu-importe'));

        $manager->persist($admin);
        $manager->flush();

        $client->loginUser($admin);

        return $admin;
    }
}
