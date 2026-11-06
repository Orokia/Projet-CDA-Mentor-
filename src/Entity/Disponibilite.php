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
    // Statuts possibles d'un jour dans joursSemaine
    public const STATUT_DISPONIBLE = 'disponible';
    public const STATUT_PLUS_DISPONIBLE = 'plus disponible';

    // Ordre des jours, de lundi à dimanche
    public const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

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

    /**
     * Clé : nom du jour (« lundi »…), valeur : statut du jour
     * (self::STATUT_DISPONIBLE ou self::STATUT_PLUS_DISPONIBLE).
     *
     * @var array<string, string>
     */
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

    /**
     * Jours triés de lundi à dimanche.
     *
     * MySQL réordonne les clés d'un objet JSON (par longueur) :
     * l'ordre d'enregistrement n'est donc pas conservé en base.
     * Les clés non reconnues sont placées à la fin, dans leur ordre d'origine.
     *
     * @return array<string, string>
     */
    public function getJoursSemaine(): array
    {
        $jours = $this->joursSemaine;
        $rangs = array_flip(self::JOURS);

        $rang = static function (int|string $jour) use ($rangs): int {
            $cle = is_string($jour) ? mb_strtolower(trim($jour)) : null;

            return null !== $cle && isset($rangs[$cle]) ? $rangs[$cle] : \count($rangs);
        };

        // uksort est stable depuis PHP 8 : les clés de même rang gardent leur ordre
        uksort($jours, static fn (int|string $a, int|string $b): int => $rang($a) <=> $rang($b));

        return $jours;
    }

    public function setJoursSemaine(array $joursSemaine): static
    {
        $this->joursSemaine = $joursSemaine;

        return $this;
    }

    /**
     * Change le statut d'un jour déjà présent dans joursSemaine.
     * La comparaison ignore la casse et les espaces ; la clé enregistrée est conservée.
     *
     * @throws \InvalidArgumentException si le jour n'est pas proposé
     */
    public function changerStatutJour(string $jour, string $statut): void
    {
        $jourNormalise = mb_strtolower(trim($jour));

        foreach ($this->joursSemaine as $jourEnregistre => $statutActuel) {
            if (is_string($jourEnregistre) && mb_strtolower(trim($jourEnregistre)) === $jourNormalise) {
                $this->joursSemaine[$jourEnregistre] = $statut;

                return;
            }
        }

        throw new \InvalidArgumentException(
            "Le jour choisi n'est pas disponible."
        );
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