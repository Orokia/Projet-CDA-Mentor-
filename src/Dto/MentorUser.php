<?php

namespace App\Dto;

class MentorUser
{
    // Informations du compte
    private ?int $idCompte = null;

    private ?string $email = null;

    private array $roles = [];


    // Informations communes
    private ?string $nom = null;

    private ?string $prenom = null;

    private ?string $photo = null;


    // Informations spécifiques
    private ?MentorFormateurInfo $formateurInfo = null;

    private ?MentorStudent $student = null;


    public function getIdCompte(): ?int
    {
        return $this->idCompte;
    }

    public function setIdCompte(?int $idCompte): static
    {
        $this->idCompte = $idCompte;

        return $this;
    }


    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }


    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }


    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }


    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }


    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }


    public function getFormateurInfo(): ?MentorFormateurInfo
    {
        return $this->formateurInfo;
    }

    public function setFormateurInfo(?MentorFormateurInfo $formateurInfo): static
    {
        $this->formateurInfo = $formateurInfo;

        return $this;
    }


    public function getStudent(): ?MentorStudent
    {
        return $this->student;
    }

    public function setStudent(?MentorStudent $student): static
    {
        $this->student = $student;

        return $this;
    }
}