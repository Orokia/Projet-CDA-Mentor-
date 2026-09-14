<?php

namespace App\Dto;

class MentorFormateurInfo
{
    private ?int $id = null;

    private ?string $experience = null;

    private ?string $langue = null;

    private ?string $localisation = null;

    private ?\DateTimeImmutable $createdAt = null;

    private ?string $videoPresentation = null;

    private array $image = [];


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }


    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(?string $experience): static
    {
        $this->experience = $experience;

        return $this;
    }


    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(?string $langue): static
    {
        $this->langue = $langue;

        return $this;
    }


    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): static
    {
        $this->localisation = $localisation;

        return $this;
    }


    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }


    public function getVideoPresentation(): ?string
    {
        return $this->videoPresentation;
    }

    public function setVideoPresentation(?string $videoPresentation): static
    {
        $this->videoPresentation = $videoPresentation;

        return $this;
    }


    public function getImage(): array
    {
        return $this->image;
    }

    public function setImage(array $image): static
    {
        $this->image = $image;

        return $this;
    }
}