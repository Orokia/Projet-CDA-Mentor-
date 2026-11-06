<?php

namespace App\Entity;

use App\Repository\PaiementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paiement d'une réservation par Stripe Checkout.
 *
 * Créé « en attente » avant la redirection vers Stripe, avec le créneau demandé
 * (disponibilité + jour). Les réservations ne sont créées qu'après vérification
 * du paiement auprès de Stripe : elles sont alors rattachées à ce paiement.
 */
#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\Index(name: 'idx_paiement_creneau', columns: ['disponibilite_id', 'jour', 'statut'])]
class Paiement
{
    public const MODE_STRIPE_CHECKOUT = 'stripe_checkout';

    // Paiement créé, étudiant redirigé vers Stripe
    public const STATUT_EN_ATTENTE = 'en_attente';
    // Paiement vérifié, réservations créées
    public const STATUT_PAYE = 'paye';
    // Retour par le lien « annuler » de Stripe
    public const STATUT_ANNULE = 'annule';
    // Payé, mais le créneau a été pris entre-temps : remboursé
    public const STATUT_REMBOURSE = 'rembourse';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montant = null;

    // Code ISO 4217 en minuscules, comme Stripe (« eur »)
    #[ORM\Column(length: 3, options: ['default' => 'eur'])]
    private string $devise = 'eur';

    #[ORM\Column(length: 50)]
    private ?string $mode = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    // Identifiant de la session Stripe Checkout (cs_…)
    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $sessionCheckoutId = null;

    // Identifiant du paiement Stripe (pi_…), connu une fois payé
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referencePaiement = null;

    // Jour de la semaine réservé (« lundi »…)
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $jour = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Student $student = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Disponibilite $disponibilite = null;

    /**
     * Une réservation par séance : un paiement couvre toutes les séances du jour choisi.
     *
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'paiement')]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getDevise(): string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): static
    {
        $this->devise = mb_strtolower($devise);

        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(string $mode): static
    {
        $this->mode = $mode;

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

    public function estPaye(): bool
    {
        return self::STATUT_PAYE === $this->statut;
    }

    public function getSessionCheckoutId(): ?string
    {
        return $this->sessionCheckoutId;
    }

    public function setSessionCheckoutId(?string $sessionCheckoutId): static
    {
        $this->sessionCheckoutId = $sessionCheckoutId;

        return $this;
    }

    public function getReferencePaiement(): ?string
    {
        return $this->referencePaiement;
    }

    public function setReferencePaiement(?string $referencePaiement): static
    {
        $this->referencePaiement = $referencePaiement;

        return $this;
    }

    public function getJour(): ?string
    {
        return $this->jour;
    }

    public function setJour(?string $jour): static
    {
        $this->jour = $jour;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeImmutable $datePaiement): static
    {
        $this->datePaiement = $datePaiement;

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

    public function getDisponibilite(): ?Disponibilite
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?Disponibilite $disponibilite): static
    {
        $this->disponibilite = $disponibilite;

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setPaiement($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('Paiement #%d – %s %s (%s)', $this->id ?? 0, $this->montant, mb_strtoupper($this->devise), $this->statut);
    }
}
