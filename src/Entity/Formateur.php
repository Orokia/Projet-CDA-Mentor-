<?php

namespace App\Entity;

use App\Repository\FormateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormateurRepository::class)]
class Formateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    private ?string $photo = null;

    #[ORM\Column(length: 255)]
    private ?string $experience = null;

    #[ORM\Column(length: 255)]
    private ?string $langue = null;

    #[ORM\Column(length: 255)]
    private ?string $localisation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(length: 255)]
    private ?string $videoPresentation = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToOne(targetEntity: User::class, mappedBy: 'formateur')]
    private Collection $compteUtilisateur;

    /**
     * @var Collection<int, Specialite>
     */
    #[ORM\ManyToMany(targetEntity: Specialite::class, mappedBy: 'fomateur')]
    private Collection $specialites;

    /**
     * @var Collection<int, Disponibilite>
     */
    #[ORM\OneToMany(targetEntity: Disponibilite::class, mappedBy: 'formateur')]
    private Collection $disponibilites;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'formateur')]
    private Collection $reservations;

    /**
     * @var Collection<int, Formation>
     */
    #[ORM\ManyToMany(targetEntity: Formation::class, inversedBy: 'formateurs')]
    private Collection $formation;

    public function __construct()
    {
        $this->compteUtilisateur = new ArrayCollection();
        $this->specialites = new ArrayCollection();
        $this->disponibilites = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->formation = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(string $experience): static
    {
        $this->experience = $experience;

        return $this;
    }

    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(string $langue): static
    {
        $this->langue = $langue;

        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): static
    {
        $this->localisation = $localisation;

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

    public function getVideoPresentation(): ?string
    {
        return $this->videoPresentation;
    }

    public function setVideoPresentation(string $videoPresentation): static
    {
        $this->videoPresentation = $videoPresentation;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getCompteUtilisateur(): Collection
    {
        return $this->compteUtilisateur;
    }

    public function addCompteUtilisateur(User $compteUtilisateur): static
    {
        if (!$this->compteUtilisateur->contains($compteUtilisateur)) {
            $this->compteUtilisateur->add($compteUtilisateur);
            $compteUtilisateur->setFormateur($this);
        }

        return $this;
    }

    public function removeCompteUtilisateur(User $compteUtilisateur): static
    {
        if ($this->compteUtilisateur->removeElement($compteUtilisateur)) {
            // set the owning side to null (unless already changed)
            if ($compteUtilisateur->getFormateur() === $this) {
                $compteUtilisateur->setFormateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Specialite>
     */
    public function getSpecialites(): Collection
    {
        return $this->specialites;
    }

    public function addSpecialite(Specialite $specialite): static
    {
        if (!$this->specialites->contains($specialite)) {
            $this->specialites->add($specialite);
            $specialite->addFomateur($this);
        }

        return $this;
    }

    public function removeSpecialite(Specialite $specialite): static
    {
        if ($this->specialites->removeElement($specialite)) {
            $specialite->removeFomateur($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Disponibilite>
     */
    public function getDisponibilites(): Collection
    {
        return $this->disponibilites;
    }

    public function addDisponibilite(Disponibilite $disponibilite): static
    {
        if (!$this->disponibilites->contains($disponibilite)) {
            $this->disponibilites->add($disponibilite);
            $disponibilite->setFormateur($this);
        }

        return $this;
    }

    public function removeDisponibilite(Disponibilite $disponibilite): static
    {
        if ($this->disponibilites->removeElement($disponibilite)) {
            // set the owning side to null (unless already changed)
            if ($disponibilite->getFormateur() === $this) {
                $disponibilite->setFormateur(null);
            }
        }

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
            $reservation->setFormateur($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getFormateur() === $this) {
                $reservation->setFormateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Formation>
     */
    public function getFormation(): Collection
    {
        return $this->formation;
    }

    public function addFormation(Formation $formation): static
    {
        if (!$this->formation->contains($formation)) {
            $this->formation->add($formation);
        }

        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        $this->formation->removeElement($formation);

        return $this;
    }
}
