<?php

namespace App\Repository;

use App\Entity\Disponibilite;
use App\Entity\Paiement;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paiement>
 */
class PaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

    /**
     * Un autre étudiant est-il en train de payer ce créneau (session Stripe encore ouverte) ?
     */
    public function existePaiementEnCoursParUnAutreEtudiant(
        Disponibilite $disponibilite,
        string $jour,
        Student $student,
        \DateTimeImmutable $creeApres
    ): bool {
        $nombre = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.disponibilite = :disponibilite')
            ->andWhere('p.jour = :jour')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateCreation > :creeApres')
            ->andWhere('p.student IS NULL OR p.student <> :student')
            ->setParameter('disponibilite', $disponibilite)
            ->setParameter('jour', $jour)
            ->setParameter('statut', Paiement::STATUT_EN_ATTENTE)
            ->setParameter('creeApres', $creeApres)
            ->setParameter('student', $student)
            ->getQuery()
            ->getSingleScalarResult();

        return $nombre > 0;
    }
}
