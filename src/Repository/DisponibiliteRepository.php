<?php

namespace App\Repository;

use App\Entity\Disponibilite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Disponibilite>
 */
class DisponibiliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Disponibilite::class);
    }

    /**
     * Récupère les disponibilités d'un formateur
     * pour une formation donnée.
     */
    public function findByFormationAndFormateur(
        int $formationId,
        int $formateurId
    ): array {
        return $this->createQueryBuilder('d')
            ->andWhere('IDENTITY(d.formation) = :formationId')
            ->andWhere('IDENTITY(d.formateur) = :formateurId')
            ->setParameter('formationId', $formationId)
            ->setParameter('formateurId', $formateurId)
            ->orderBy('d.dateDebut', 'ASC')
            ->addOrderBy('d.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }
}