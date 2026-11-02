<?php

namespace App\Service;

// Importation des repositories pour accéder aux données
use App\Repository\FormationRepository;
use App\Repository\FormateurRepository;

// Service qui centralise la récupération des données
// nécessaires à la page d'accueil.
class HomeService
{
    /**
     * Injection des repositories via le constructeur.
     *
     * Symfony fournit automatiquement les dépendances.
     */
    public function __construct(
        private FormationRepository $formationRepository,
        private FormateurRepository $formateurRepository
    ) {
    }

    /**
     * Récupère toutes les formations.
     *
     * @return array Liste des formations
     */
    public function getAllFormations(): array
    {
        return $this->formationRepository->findAll();
    }

    /**
     * Récupère tous les formateurs.
     *
     * @return array Liste des formateurs
     */
    public function getAllFormateurs(): array
    {
        return $this->formateurRepository->findAll();
    }
}
