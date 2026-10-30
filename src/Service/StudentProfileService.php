<?php

namespace App\Service;

use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

// Service responsable de la mise à jour du profil étudiant.
class StudentProfileService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Synchronise les informations entre Student et User,
     * puis enregistre les modifications en base de données.
     */
    public function updateProfile(
        Student $student,
        User $user
    ): void {
        // Mise à jour du prénom du compte utilisateur
        $user->setPrenomUtilisateur($student->getPrenom());

        // Mise à jour du nom du compte utilisateur
        $user->setNomUtilisateur($student->getNom());

        // Synchronisation de l'adresse e-mail
        $user->setEmail($student->getEmail());

        // Mise à jour de la date de modification du compte
        $user->setUpdatedAt(new \DateTimeImmutable());

        // Enregistrement des modifications des entités gérées par Doctrine
        $this->entityManager->flush();
    }

    /**
    
    
     */
    public function updatePhoto(
        Student $student,
        User $user,
        string $filename
    ): void {
        // Enregistrement du nom de la photo sur le profil étudiant
        $student->setPhoto($filename);

        // Synchronisation de la photo du compte utilisateur
        $user->setPhotoUtilisateur($filename);
    }
}
