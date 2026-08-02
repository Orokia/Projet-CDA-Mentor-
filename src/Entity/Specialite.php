<?php

namespace App\Entity;

use App\Repository\SpecialiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpecialiteRepository::class)]
class Specialite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    /**
     * @var Collection<int, Formateur>
     */
    #[ORM\ManyToMany(targetEntity: Formateur::class, inversedBy: 'specialites')]
    private Collection $fomateur;

    /**
     * @var Collection<int, Formation>
     */
    #[ORM\OneToMany(targetEntity: Formation::class, mappedBy: 'specialite')]
    private Collection $formation;

    public function __construct()
    {
        $this->fomateur = new ArrayCollection();
        $this->formation = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
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

    /**
     * @return Collection<int, Formateur>
     */
    public function getFomateur(): Collection
    {
        return $this->fomateur;
    }

    public function addFomateur(Formateur $fomateur): static
    {
        if (!$this->fomateur->contains($fomateur)) {
            $this->fomateur->add($fomateur);
        }

        return $this;
    }

    public function removeFomateur(Formateur $fomateur): static
    {
        $this->fomateur->removeElement($fomateur);

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
            $formation->setSpecialite($this);
        }

        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        if ($this->formation->removeElement($formation)) {
            // set the owning side to null (unless already changed)
            if ($formation->getSpecialite() === $this) {
                $formation->setSpecialite(null);
            }
        }

        return $this;
    }
}
