<?php

namespace App\Service;

use App\Entity\Formateur;
use App\Repository\FormateurRepository;

class FormateurService
{
    public function __construct(
        private FormateurRepository $formateurRepository
    ) {
    }

    public function getAllFormateurs(): array
    {
        return $this->formateurRepository->findAll();
    }

    public function getFormateurById(int $id): ?Formateur
    {
        return $this->formateurRepository->find($id);
    }
}
