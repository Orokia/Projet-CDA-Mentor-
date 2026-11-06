<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    public const STATUT_EN_ATTENTE = 'en attente';
    public const STATUT_CONFIRMEE = 'confirmée';
    public const STATUT_ANNULEE = 'annulée';
    public const STATUT_TERMINEE = 'terminée';

    /** Libellés affichés (back-office) => statuts enregistrés */
    public const STATUTS = [
        'En attente' => self::STATUT_EN_ATTENTE,
        'Confirmée' => self::STATUT_CONFIRMEE,
        'Annulée' => self::STATUT_ANNULEE,
        'Terminée' => self::STATUT_TERMINEE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $dateReservation = null;

    #[ORM\Column]
    private ?\DateTime $dateHeureDebut = null;

    #[ORM\Column]
    private ?\DateTime $dateHeureFin = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $lienVisio = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formateur $formateur = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formation $formation = null;

    // Un même paiement couvre toutes les séances réservées pour le jour choisi
    #[ORM\ManyToOne(inversedBy: 'reservations')]
    private ?Paiement $paiement = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Disponibilite $disponibilite = null;

    // Statut lu en base (non enregistré) : les réservations confirmées avant
    // le paiement en ligne restent valides tant que leur statut ne change pas
    private ?string $statutCharge = null;

    #[ORM\PostLoad]
    public function memoriserStatutCharge(): void
    {
        $this->statutCharge = $this->statut;
    }

    /**
     * Seul moyen, côté application, de confirmer une réservation :
     * le paiement doit avoir été effectué.
     *
     * @throws \LogicException si le paiement n'est pas effectué
     */
    public function confirmer(Paiement $paiement): static
    {
        if (!$paiement->estPaye()) {
            throw new \LogicException('Une réservation ne peut être confirmée qu\'après le paiement.');
        }

        $paiement->addReservation($this);
        $this->statut = self::STATUT_CONFIRMEE;

        return $this;
    }

    public function estConfirmee(): bool
    {
        return self::STATUT_CONFIRMEE === $this->statut;
    }

    /**
     * Back-office : passer une réservation à « confirmée » exige un paiement effectué.
     */
    #[Assert\Callback]
    public function validerStatut(ExecutionContextInterface $context): void
    {
        $devientConfirmee = self::STATUT_CONFIRMEE === $this->statut
            && self::STATUT_CONFIRMEE !== $this->statutCharge;

        if ($devientConfirmee && true !== $this->paiement?->estPaye()) {
            $context->buildViolation('Une réservation ne peut être confirmée que si son paiement est effectué.')
                ->atPath('statut')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateReservation(): ?\DateTime
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTime $dateReservation): static
    {
        $this->dateReservation = $dateReservation;

        return $this;
    }

    public function getDateHeureDebut(): ?\DateTime
    {
        return $this->dateHeureDebut;
    }

    public function setDateHeureDebut(\DateTime $dateHeureDebut): static
    {
        $this->dateHeureDebut = $dateHeureDebut;

        return $this;
    }

    public function getDateHeureFin(): ?\DateTime
    {
        return $this->dateHeureFin;
    }

    public function setDateHeureFin(\DateTime $dateHeureFin): static
    {
        $this->dateHeureFin = $dateHeureFin;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getLienVisio(): ?string
    {
        return $this->lienVisio;
    }

    public function setLienVisio(?string $lienVisio): static
    {
        $this->lienVisio = $lienVisio;

        return $this;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getFormateur(): ?Formateur
    {
        return $this->formateur;
    }

    public function setFormateur(?Formateur $formateur): static
    {
        $this->formateur = $formateur;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getDisponibilite(): ?Disponibilite
{
    return $this->disponibilite;
}

public function setDisponibilite(?Disponibilite $disponibilite): static
{
    $this->disponibilite = $disponibilite;

    return $this;
}

    public function getPaiement(): ?Paiement
    {
        return $this->paiement;
    }

    public function setPaiement(?Paiement $paiement): static
    {
        $this->paiement = $paiement;

        return $this;
    }
}