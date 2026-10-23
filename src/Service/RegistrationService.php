<?php

namespace App\Service;

use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Psr\Log\LoggerInterface;

class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Crée un utilisateur et son profil étudiant.
     */
    public function register(User $user, string $plainPassword): User
    {
        // Définir le rôle par défaut
        $user->setRoles(['ROLE_USER']);

        // Hacher le mot de passe avant son enregistrement
        $user->setPassword(
            $this->userPasswordHasher->hashPassword($user, $plainPassword)
        );

        // Créer le profil étudiant associé au compte
        $student = new Student();
        $student->setNom($user->getNomUtilisateur());
        $student->setPrenom($user->getPrenomUtilisateur());
        $student->setEmail($user->getEmail());

        // Synchroniser les relations entre User et Student
        $user->setStudent($student);
        $student->setUser($user);

        // Enregistrer les deux entités
        $this->entityManager->persist($user);
        $this->entityManager->persist($student);
        $this->entityManager->flush();

        $this->logger->info('Nouveau compte étudiant créé.', [
            'userId' => $user->getId(),
        ]);

        return $user;
    }
}
