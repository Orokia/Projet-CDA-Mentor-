<?php

namespace App\Entity;

use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    /**
     * Compte utilisateur associé à l'étudiant.
     */
    #[ORM\OneToOne(
        targetEntity: User::class,
        mappedBy: 'student'
    )]
    private ?User $user = null;

    /**
     * Réservations de l'étudiant.
     *
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(
        targetEntity: Reservation::class,
        mappedBy: 'student'
    )]
    private Collection $reservations;

    /**
     * Formations suivies par l'étudiant.
     *
     * @var Collection<int, Formation>
     */
    #[ORM\ManyToMany(
        targetEntity: Formation::class,
        inversedBy: 'students'
    )]
    private Collection $formations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->formations = new ArrayCollection();
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
    // PRÉNOM
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

    // =========================================================
    // PHOTO
    // =========================================================

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    // =========================================================
    // USER
    // =========================================================

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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

    public function addReservation(
        Reservation $reservation
    ): static {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setStudent($this);
        }

        return $this;
    }

    public function removeReservation(
        Reservation $reservation
    ): static {
        if ($this->reservations->removeElement($reservation)) {

            if ($reservation->getStudent() === $this) {
                $reservation->setStudent(null);
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
    public function getFormations(): Collection
    {
        return $this->formations;
    }

    public function addFormation(
        Formation $formation
    ): static {
        if (!$this->formations->contains($formation)) {
            $this->formations->add($formation);
        }

        return $this;
    }

    public function removeFormation(
        Formation $formation
    ): static {
        $this->formations->removeElement($formation);

        return $this;
    }
}