<?php

namespace App\Service\Admin;

use App\Entity\Student;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StudentCrudService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher
    ) {
    }

    /**
     * Crée le compte utilisateur associé à un étudiant.
     */
    public function preparerCreation(
        Student $student,
        string $email,
        ?string $plainPassword
    ): User {
        if ($plainPassword === null || trim($plainPassword) === '') {
            throw new \RuntimeException(
                'Le mot de passe de l’étudiant est obligatoire.'
            );
        }

        $user = new User();

        $this->synchroniserInformations($student, $user, $email);

        $user->setRoles(['ROLE_STUDENT']);

        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            $plainPassword
        );

        $user->setPassword($hashedPassword);

        $student->setUser($user);
        $user->setStudent($student);

        $this->entityManager->persist($student);
        $this->entityManager->persist($user);

        return $user;
    }

    /**
     * Synchronise le compte utilisateur lors de la modification.
     */
    public function preparerModification(
        Student $student,
        ?string $email,
        ?string $plainPassword
    ): User {
        $user = $student->getUser();

        if ($user === null) {
            if ($email === null || trim($email) === '') {
                throw new \RuntimeException(
                    'Un e-mail est obligatoire pour créer le compte étudiant.'
                );
            }

            if ($plainPassword === null || trim($plainPassword) === '') {
                throw new \RuntimeException(
                    'Un mot de passe est obligatoire pour créer le compte étudiant.'
                );
            }

            $user = new User();

            $student->setUser($user);
            $user->setStudent($student);

            $user->setRoles(['ROLE_STUDENT']);

            $this->entityManager->persist($user);

            $hashedPassword = $this->userPasswordHasher->hashPassword(
                $user,
                $plainPassword
            );

            $user->setPassword($hashedPassword);
        } else {
            if ($email !== null && trim($email) !== '') {
                $user->setEmail($email);
            }

            if ($plainPassword !== null && $plainPassword !== '') {
                $hashedPassword = $this->userPasswordHasher->hashPassword(
                    $user,
                    $plainPassword
                );

                $user->setPassword($hashedPassword);
            }
        }

        $this->synchroniserInformations(
            $student,
            $user,
            $user->getEmail() ?? ''
        );

        $user->setRoles(['ROLE_STUDENT']);

        $this->entityManager->persist($student);
        $this->entityManager->persist($user);

        return $user;
    }

    private function synchroniserInformations(
        Student $student,
        User $user,
        string $email
    ): void {
        $user->setEmail($email);
        $user->setNomUtilisateur($student->getNom() ?? '');
        $user->setPrenomUtilisateur($student->getPrenom() ?? '');
    }
}
