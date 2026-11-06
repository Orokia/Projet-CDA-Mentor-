<?php

namespace App\Tests\Support;

use App\Entity\Disponibilite;
use App\Entity\Formateur;
use App\Entity\Formation;
use App\Entity\Paiement;
use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Création d'entités persistées pour les tests Integration et Functional.
 *
 * Chaque test est enveloppé dans une transaction DAMA annulée à la fin :
 * les données créées ici ne fuient pas d'un test à l'autre.
 */
trait EntitesDeTestTrait
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Paiement effectué (persisté) : une réservation n'est confirmée que sur un paiement payé.
     */
    private function paiementEffectue(): Paiement
    {
        $paiement = (new Paiement())
            ->setMontant('100.00')
            ->setMode(Paiement::MODE_STRIPE_CHECKOUT)
            ->setStatut(Paiement::STATUT_PAYE)
            ->setDatePaiement(new \DateTimeImmutable());

        $this->em()->persist($paiement);

        return $paiement;
    }

    private function creerFormation(string $titre = 'Symfony'): Formation
    {
        $formation = (new Formation())
            ->setTitre($titre)
            ->setDescription('Description de la formation')
            ->setDuree(20)
            ->setPrix('199.99')
            ->setImage('formation-test.jpg');

        $this->em()->persist($formation);
        $this->em()->flush();

        return $formation;
    }

    private function creerFormateur(
        string $email = 'aminata.diallo@example.test'
    ): Formateur {
        $formateur = (new Formateur())
            ->setNom('Diallo')
            ->setPrenom('Aminata')
            ->setEmail($email)
            ->setExperience('5 ans')
            ->setLangue('Français')
            ->setLocalisation('Paris')
            ->setVideoPresentation('presentation.mp4');

        $this->em()->persist($formateur);
        $this->em()->flush();

        return $formateur;
    }

    /**
     * Disponibilité par défaut : du lundi 19 au lundi 26 octobre 2026,
     * soit deux lundis.
     *
     * @param array<string, string> $jours jour => statut
     */
    private function creerDisponibilite(
        Formateur $formateur,
        Formation $formation,
        array $jours = ['lundi' => Disponibilite::STATUT_DISPONIBLE],
        string $dateDebut = '2026-10-19',
        string $dateFin = '2026-10-26'
    ): Disponibilite {
        $disponibilite = new Disponibilite();
        // addDisponibilite() synchronise aussi la collection inverse,
        // utilisée par la page profil du formateur
        $formateur->addDisponibilite($disponibilite);
        $disponibilite->setFormation($formation);
        $disponibilite->setDateDebut(new \DateTime($dateDebut));
        $disponibilite->setDateFin(new \DateTime($dateFin));
        $disponibilite->setHeureDebut(new \DateTime('09:00:00'));
        $disponibilite->setHeureFin(new \DateTime('10:00:00'));
        $disponibilite->setJoursSemaine($jours);

        $this->em()->persist($disponibilite);
        $this->em()->flush();

        return $disponibilite;
    }

    /**
     * Crée un compte vérifié, avec un profil étudiant si demandé.
     *
     * @param list<string> $roles
     */
    private function creerUtilisateur(
        string $email = 'amadou@example.test',
        array $roles = ['ROLE_USER'],
        bool $avecProfilEtudiant = true,
        bool $verifie = true
    ): User {
        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles)
            ->setPassword('hash-non-utilise')
            ->setNomUtilisateur('Diallo')
            ->setPrenomUtilisateur('Amadou');
        $user->setIsVerified($verifie);

        if ($avecProfilEtudiant) {
            $student = (new Student())
                ->setNom('Diallo')
                ->setPrenom('Amadou')
                ->setEmail($email);

            $student->setUser($user);
            $user->setStudent($student);
            $this->em()->persist($student);
        }

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }
}
