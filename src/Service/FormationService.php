<?php

namespace App\Service;

// Importation des entités utilisées par le service
use App\Entity\Formation;
use App\Entity\Formateur;

// Importation des repositories pour accéder aux données
use App\Repository\FormationRepository;
use App\Repository\FormateurRepository;

// Service qui centralise la logique métier liée aux formations
// et à la récupération des formateurs.
class FormationService
{
    /**
     * Constructeur du service.
     *
     * Symfony injecte automatiquement les deux repositories.
     * Le service peut ainsi récupérer les formations et les formateurs
     * sans accéder directement à la base de données.
     */
    public function __construct(
        private FormationRepository $formationRepository,
        private FormateurRepository $formateurRepository
    ) {
    }

    /**
     * Récupère la liste de toutes les formations.
     *
     * findAll() est une méthode fournie par Doctrine
     * via le repository de l'entité Formation.
     *
     * @return Formation[] Tableau contenant les formations
     */
    public function getAllFormations(): array
    {
        return $this->formationRepository->findAll();
    }

    /**
     * Recherche une formation à partir de son identifiant.
     *
     * @param int $id Identifiant de la formation recherchée
     *
     * @return Formation|null La formation trouvée ou null si elle n'existe pas
     */
    public function getFormationById(int $id): ?Formation
    {
        return $this->formationRepository->find($id);
    }

    /**
     * Recherche un formateur à partir de son identifiant.
     *
     * @param int $id Identifiant du formateur recherché
     *
     * @return Formateur|null Le formateur trouvé ou null s'il n'existe pas
     */
    public function getFormateurById(int $id): ?Formateur
    {
        return $this->formateurRepository->find($id);
    }
}
