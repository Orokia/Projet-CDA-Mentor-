<?php

namespace App\Entity;
use App\Entity\Reservation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\DisponibiliteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DisponibiliteRepository::class)]
class Disponibilite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(type: Types::JSON)]
    private array $joursSemaine = [];

    #[ORM\ManyToOne(inversedBy: 'disponibilites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formateur $formateur = null;

    #[ORM\ManyToOne(inversedBy: 'disponibilites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formation $formation = null;

    #[ORM\OneToMany(
    mappedBy: 'disponibilite',
    targetEntity: Reservation::class
)]
private Collection $reservations;


public function __construct()
{
    $this->reservations = new ArrayCollection();
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId( int $id ): static{

      $this->id= $id;

        return $this;
        

    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(\DateTimeInterface $heureDebut): static
    {
        $this->heureDebut = $heureDebut;

        return $this;
    }

    public function getHeureFin(): ?\DateTimeInterface
    {
        return $this->heureFin;
    }

    public function setHeureFin(\DateTimeInterface $heureFin): static
    {
        $this->heureFin = $heureFin;

        return $this;
    }

    public function getJoursSemaine(): array
    {
        return $this->joursSemaine;
    }

    public function setJoursSemaine(array $joursSemaine): static
    {
        $this->joursSemaine = $joursSemaine;

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
        $reservation->setDisponibilite($this);
    }

    return $this;
}

public function removeReservation(Reservation $reservation): static
{
    if ($this->reservations->removeElement($reservation)) {
        if ($reservation->getDisponibilite() === $this) {
            $reservation->setDisponibilite(null);
        }
    }

    return $this;
}
}