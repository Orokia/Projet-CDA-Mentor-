<?php

namespace App\Service;

use App\Entity\Paiement;
use App\Entity\Student;
use App\Paiement\DemandeCheckout;
use App\Paiement\Montant;
use App\Paiement\PasserellePaiementInterface;
use App\Repository\PaiementRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tunnel de paiement d'une réservation : réservation → paiement Stripe → confirmation.
 *
 * 1. demarrerPaiement() : vérifie le créneau, enregistre un paiement « en attente »
 *    et crée la session Stripe Checkout (page de paiement hébergée).
 * 2. confirmerPaiement() : au retour de Stripe, relit la session auprès de Stripe,
 *    puis crée les réservations (avec liens Jitsi) et marque le paiement « payé ».
 * 3. annulerPaiement() : retour par le lien « annuler » de Stripe.
 * 4. confirmerDepuisStripe() / expirerDepuisStripe() : webhook Stripe, pour l'étudiant
 *    qui a payé puis fermé l'onglet sans revenir sur Mentor.
 *
 * Les messages des \InvalidArgumentException / \RuntimeException sont affichés à l'étudiant.
 */
class PaiementService
{
    // Durée de vie d'une session Stripe Checkout : Stripe impose au moins 30 minutes
    public const DUREE_SESSION_MINUTES = 31;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaiementRepository $paiementRepository,
        private ReservationService $reservationService,
        private PasserellePaiementInterface $passerelle,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return string URL de la page de paiement Stripe vers laquelle rediriger l'étudiant
     */
    public function demarrerPaiement(Student $student, int $disponibiliteId, string $jourChoisi): string
    {
        $jour = mb_strtolower(trim($jourChoisi));
        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();

        try {
            // Mêmes contrôles qu'à la réservation, avec verrou sur la disponibilité :
            // deux étudiants ne peuvent pas démarrer le paiement du même jour en même temps
            $disponibilite = $this->reservationService->verifierCreneauReservable($student, $disponibiliteId, $jour);

            // Un autre étudiant est déjà sur la page de paiement Stripe pour ce jour
            // (session créée il y a moins de DUREE_SESSION_MINUTES, donc encore payable)
            $maintenant = new \DateTimeImmutable();
            $debutSessionsOuvertes = $maintenant->modify(sprintf('-%d minutes', self::DUREE_SESSION_MINUTES));

            if ($this->paiementRepository->existePaiementEnCoursParUnAutreEtudiant($disponibilite, $jour, $student, $debutSessionsOuvertes)) {
                throw new \RuntimeException('Ce jour est en cours de réservation par un autre étudiant. Veuillez réessayer dans quelques minutes.');
            }

            $formation = $disponibilite->getFormation();

            $paiement = (new Paiement())
                ->setMontant($formation->getPrix())
                ->setDevise('eur')
                ->setMode(Paiement::MODE_STRIPE_CHECKOUT)
                ->setStatut(Paiement::STATUT_EN_ATTENTE)
                ->setStudent($student)
                ->setDisponibilite($disponibilite)
                ->setJour($jour);

            $this->entityManager->persist($paiement);
            // Identifiant nécessaire pour les URL de retour et la référence Stripe
            $this->entityManager->flush();

            $session = $this->passerelle->creerSession(new DemandeCheckout(
                referenceInterne: (string) $paiement->getId(),
                libelle: (string) $formation->getTitre(),
                description: sprintf(
                    'Séances du %s avec %s %s, de %s à %s',
                    $jour,
                    $disponibilite->getFormateur()?->getPrenom(),
                    $disponibilite->getFormateur()?->getNom(),
                    $disponibilite->getHeureDebut()?->format('H:i'),
                    $disponibilite->getHeureFin()?->format('H:i'),
                ),
                montantCentimes: Montant::enCentimes($paiement->getMontant()),
                devise: $paiement->getDevise(),
                emailClient: $student->getUser()?->getEmail() ?? $student->getEmail(),
                urlSucces: $this->urlGenerator->generate('app_paiement_succes', ['id' => $paiement->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                urlAnnulation: $this->urlGenerator->generate('app_paiement_annule', ['id' => $paiement->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                expireA: $maintenant->modify(sprintf('+%d minutes', self::DUREE_SESSION_MINUTES)),
            ));

            $paiement->setSessionCheckoutId($session->id);
            $this->entityManager->flush();
            $connexion->commit();
        } catch (\Throwable $exception) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            throw $exception;
        }

        return $session->url;
    }

    /**
     * Vérifie le paiement auprès de Stripe puis crée les réservations.
     * Sans effet si le paiement est déjà confirmé (page de retour rechargée).
     *
     * @throws \RuntimeException message affichable (non payé, créneau pris et remboursé…)
     */
    public function confirmerPaiement(Student $student, int $paiementId): Paiement
    {
        return $this->confirmer($this->paiementDeLEtudiant($student, $paiementId));
    }

    /**
     * Webhook Stripe (checkout.session.completed) : confirme le paiement même si
     * l'étudiant a fermé l'onglet avant de revenir sur Mentor.
     *
     * @return Paiement|null null si la session ne correspond à aucun paiement Mentor
     *
     * @throws \RuntimeException paiement non validé ou créneau pris (remboursé) : rien à réessayer
     */
    public function confirmerDepuisStripe(string $sessionId): ?Paiement
    {
        $paiement = $this->paiementRepository->findOneBy(['sessionCheckoutId' => $sessionId]);

        if (null === $paiement) {
            $this->logger->warning('Webhook Stripe : session inconnue de Mentor.', ['session' => $sessionId]);

            return null;
        }

        // Déjà traité (retour du navigateur ou événement renvoyé par Stripe)
        if (Paiement::STATUT_REMBOURSE === $paiement->getStatut()) {
            return $paiement;
        }

        return $this->confirmer($paiement);
    }

    /**
     * Webhook Stripe (checkout.session.expired) : la session n'a pas été payée à temps.
     * Le jour redevient réservable par les autres étudiants.
     */
    public function expirerDepuisStripe(string $sessionId): ?Paiement
    {
        $paiement = $this->paiementRepository->findOneBy(['sessionCheckoutId' => $sessionId]);

        if (null !== $paiement && Paiement::STATUT_EN_ATTENTE === $paiement->getStatut()) {
            $paiement->setStatut(Paiement::STATUT_ANNULE);
            $this->entityManager->flush();
        }

        return $paiement;
    }

    /**
     * Confirmation commune au retour du navigateur et au webhook.
     */
    private function confirmer(Paiement $paiement): Paiement
    {
        if ($paiement->estPaye()) {
            return $paiement;
        }

        if (Paiement::STATUT_REMBOURSE === $paiement->getStatut()) {
            throw new \RuntimeException('Ce créneau n\'était plus disponible : votre paiement a été remboursé.');
        }

        if (null === $paiement->getSessionCheckoutId()) {
            throw new \RuntimeException('Ce paiement n\'a pas été initialisé. Aucune réservation n\'a été créée.');
        }

        // Seule source fiable : la session relue auprès de Stripe
        $etat = $this->passerelle->recupererSession($paiement->getSessionCheckoutId());

        if (!$etat->paye) {
            throw new \RuntimeException('Le paiement n\'a pas été validé par Stripe. Aucune réservation n\'a été créée.');
        }

        if ($etat->montantCentimes !== Montant::enCentimes((string) $paiement->getMontant())
            || mb_strtolower((string) $etat->devise) !== $paiement->getDevise()
            || $etat->referenceInterne !== (string) $paiement->getId()) {
            $this->logger->critical('Session Stripe incohérente avec le paiement Mentor.', [
                'paiement' => $paiement->getId(),
                'session' => $etat->sessionId,
                'montant_stripe' => $etat->montantCentimes,
                'devise_stripe' => $etat->devise,
            ]);

            throw new \RuntimeException('Le paiement reçu ne correspond pas à cette réservation. Contactez le support Mentor.');
        }

        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();

        try {
            // Verrou et relecture : deux retours simultanés (double clic, onglets)
            // ne créent pas deux fois les réservations
            $this->entityManager->refresh($paiement, LockMode::PESSIMISTIC_WRITE);

            if ($paiement->estPaye()) {
                $connexion->commit();

                return $paiement;
            }

            // Paiement vérifié auprès de Stripe : il est marqué « payé » avant la
            // création des réservations, qui ne sont confirmées que sur un paiement
            // effectué. En cas d'échec, la transaction annule ce changement.
            $paiement
                ->setReferencePaiement($etat->referencePaiement)
                ->setStatut(Paiement::STATUT_PAYE)
                ->setDatePaiement(new \DateTimeImmutable());

            try {
                // Compte supprimé pendant le paiement : traité comme un créneau perdu (remboursement)
                $student = $paiement->getStudent()
                    ?? throw new \RuntimeException('Le compte étudiant associé à ce paiement n\'existe plus.');

                $this->reservationService->creerReservations(
                    $student,
                    (int) $paiement->getDisponibilite()?->getId(),
                    (string) $paiement->getJour(),
                    $paiement
                );
            } catch (\InvalidArgumentException|\RuntimeException $creneauIndisponible) {
                // Payé, mais le créneau a été pris entre-temps : remboursement intégral
                $this->rembourser($paiement, $etat->referencePaiement, $creneauIndisponible);
                $connexion->commit();

                throw new \RuntimeException(sprintf(
                    '%s Votre paiement a été intégralement remboursé.',
                    $creneauIndisponible->getMessage()
                ), 0, $creneauIndisponible);
            }

            $this->entityManager->flush();
            $connexion->commit();
        } catch (\Throwable $exception) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            throw $exception;
        }

        return $paiement;
    }

    /**
     * Retour par le lien « annuler » de Stripe : la session est fermée.
     * Si l'étudiant a malgré tout payé (autre onglet), le paiement est confirmé.
     */
    public function annulerPaiement(Student $student, int $paiementId): Paiement
    {
        $paiement = $this->paiementDeLEtudiant($student, $paiementId);

        if (Paiement::STATUT_EN_ATTENTE !== $paiement->getStatut()) {
            return $paiement;
        }

        if (null !== $paiement->getSessionCheckoutId()) {
            if ($this->passerelle->recupererSession($paiement->getSessionCheckoutId())->paye) {
                return $this->confirmer($paiement);
            }

            $this->passerelle->expirerSession($paiement->getSessionCheckoutId());
        }

        $paiement->setStatut(Paiement::STATUT_ANNULE);
        $this->entityManager->flush();

        return $paiement;
    }

    private function paiementDeLEtudiant(Student $student, int $paiementId): Paiement
    {
        $paiement = $this->paiementRepository->find($paiementId);

        // Même message si le paiement appartient à un autre étudiant : rien n'est divulgué
        if (null === $paiement || $paiement->getStudent()?->getId() !== $student->getId()) {
            throw new \InvalidArgumentException('Paiement introuvable.');
        }

        return $paiement;
    }

    private function rembourser(Paiement $paiement, ?string $referencePaiement, \Throwable $cause): void
    {
        if (null === $referencePaiement) {
            throw new \LogicException(sprintf('Paiement %d payé sans référence Stripe : remboursement impossible.', $paiement->getId()), 0, $cause);
        }

        $this->passerelle->rembourser($referencePaiement);

        $paiement
            ->setStatut(Paiement::STATUT_REMBOURSE)
            ->setDatePaiement(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->warning('Paiement remboursé : créneau devenu indisponible pendant le paiement.', [
            'paiement' => $paiement->getId(),
            'raison' => $cause->getMessage(),
        ]);
    }
}
