<?php

namespace App\Entity;

use App\Repository\FormateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

    #[ORM\Column(length: 180)]
private ?string $email = null;

#[ORM\Column(nullable: true)]
private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $experience = null;

    #[ORM\Column(length: 255)]
    private ?string $langue = null;

    #[ORM\Column(length: 255)]
    private ?string $localisation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $videoPresentation = null;

    /**
     * Un formateur possède un seul compte utilisateur.
     */
   #[ORM\OneToOne(
    mappedBy: 'formateur',
    cascade: ['persist', 'remove']
)]
private ?User $compteUtilisateur = null;
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

    /**
     * Images du formateur
     */
     #[ORM\Column(length: 255, nullable:true)]
    private ?string $image = null;

    public function __construct()
    {
        $this->specialites = new ArrayCollection();
        $this->disponibilites = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->formation = new ArrayCollection();

        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
{
    return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
}

    // =========================================================
    // ID
    // =========================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    // =========================================================
    // NOM
    // =========================================================

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    // =========================================================
    // PRENOM
    // =========================================================

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

     public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    // =========================================================
    // EXPERIENCE
    // =========================================================

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(string $experience): static
    {
        $this->experience = $experience;

        return $this;
    }

    // =========================================================
    // LANGUE
    // =========================================================

    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(string $langue): static
    {
        $this->langue = $langue;

        return $this;
    }

    // =========================================================
    // LOCALISATION
    // =========================================================

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): static
    {
        $this->localisation = $localisation;

        return $this;
    }

    // =========================================================
    // DATE DE CREATION
    // =========================================================

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    // =========================================================
    // VIDEO PRESENTATION
    // =========================================================

    public function getVideoPresentation(): ?string
    {
        return $this->videoPresentation;
    }

    public function setVideoPresentation(string $videoPresentation): static
    {
        $this->videoPresentation = $videoPresentation;

        return $this;
    }

    // =========================================================
    // COMPTE UTILISATEUR
    // =========================================================

    public function getCompteUtilisateur(): ?User
    {
        return $this->compteUtilisateur;
    }

   public function setCompteUtilisateur(?User $compteUtilisateur): static
{
    $this->compteUtilisateur = $compteUtilisateur;

    if ($compteUtilisateur !== null && $compteUtilisateur->getFormateur() !== $this) {
        $compteUtilisateur->setFormateur($this);
    }
        return $this;
    }

    // =========================================================
    // SPECIALITES
    // =========================================================

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

    // =========================================================
    // DISPONIBILITES
    // =========================================================

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
            if ($disponibilite->getFormateur() === $this) {
                $disponibilite->setFormateur(null);
            }
        }

        return $this;
    }

    // =========================================================
    // RESERVATIONS
    // =========================================================

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
            if ($reservation->getFormateur() === $this) {
                $reservation->setFormateur(null);
            }
        }

        return $this;
    }

    // =========================================================
    // FORMATIONS
    // =========================================================

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
            $formation->addFormateur($this);
        }

        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        if ($this->formation->removeElement($formation)) {
            $formation->removeFormateur($this);
        }

        return $this;
    }

    // =========================================================
    // IMAGES
    // =========================================================

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }
}