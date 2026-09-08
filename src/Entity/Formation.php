<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $duree = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $prix = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\ManyToOne(inversedBy: 'formation')]
    private ?Specialite $specialite = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'formation')]
    private Collection $reservations;

    /**
     * @var Collection<int, Student>
     */
    #[ORM\ManyToMany(targetEntity: Student::class, mappedBy: 'formation')]
    private Collection $students;

    /**
     * @var Collection<int, Formateur>
     */
    #[ORM\ManyToMany(targetEntity: Formateur::class, mappedBy: 'formation')]
    private Collection $formateurs;

    #[ORM\OneToMany(
    mappedBy: 'formation',
    targetEntity: Disponibilite::class
)]
private Collection $disponibilites;



    public function __construct()
    {

        $this->reservations = new ArrayCollection();
        $this->students = new ArrayCollection();
        $this->formateurs = new ArrayCollection();
        $this->disponibilites = new ArrayCollection();
    }
  
    public function __toString(): string
    {
    return $this->titre ?? '';
    }



    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(int $duree): static
    {
        $this->duree = $duree;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getSpecialite(): ?Specialite
    {
        return $this->specialite;
    }

    public function setSpecialite(?Specialite $specialite): static
    {
        $this->specialite = $specialite;

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
            $reservation->setFormation($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getFormation() === $this) {
                $reservation->setFormation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(Student $student): static
    {
        if (!$this->students->contains($student)) {
            $this->students->add($student);
            $student->addFormation($this);
        }

        return $this;
    }

    public function removeStudent(Student $student): static
    {
        if ($this->students->removeElement($student)) {
            $student->removeFormation($this);
        }

        return $this;
    }

   

    /**
     * @return Collection<int, Formateur>
     */
    public function getFormateurs(): Collection
    {
        return $this->formateurs;
    }

    public function addFormateur(Formateur $formateur): static
    {
        if (!$this->formateurs->contains($formateur)) {
            $this->formateurs->add($formateur);
            $formateur->addFormation($this);
        }

        return $this;
    }

    public function removeFormateur(Formateur $formateur): static
    {
        if ($this->formateurs->removeElement($formateur)) {
            $formateur->removeFormation($this);
        }

        return $this;
    }

    public function getDisponibilites(): Collection
{
    return $this->disponibilites;
}

public function addDisponibilite(Disponibilite $disponibilite): static
{
    if (!$this->disponibilites->contains($disponibilite)) {
        $this->disponibilites->add($disponibilite);
        $disponibilite->setFormation($this);
    }

    return $this;
}

public function removeDisponibilite(Disponibilite $disponibilite): static
{
    $this->disponibilites->removeElement($disponibilite);

    return $this;
}
}
