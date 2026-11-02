<?php

namespace App\Service\Admin;

use App\Entity\Formateur;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FormateurCrudService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher
    ) {
    }

    /**
     * Prépare la création du formateur et de son compte utilisateur.
     */
    public function preparerCreation(Formateur $formateur): User
    {
        // Récupérer le mot de passe saisi dans EasyAdmin
        $plainPassword = $formateur->getPassword();

        if ($plainPassword === null || $plainPassword === '') {
            throw new \RuntimeException(
                'Le mot de passe du formateur est obligatoire.'
            );
        }

        // Créer le compte utilisateur
        $user = new User();

        $user->setEmail($formateur->getEmail());
        $user->setNomUtilisateur($formateur->getNom());
        $user->setPrenomUtilisateur($formateur->getPrenom());
        $user->setRoles(['ROLE_FORMATEUR']);

        // Compte créé par l'administrateur : pas de confirmation par e-mail
        $user->setIsVerified(true);

        // Hacher le mot de passe
        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            $plainPassword
        );

        $user->setPassword($hashedPassword);

        // Conserver le hash dans Formateur pour compatibilité
        // avec le mapping actuel de l'entité
        $formateur->setPassword($hashedPassword);

        // Synchroniser les relations entre User et Formateur
        $formateur->setCompteUtilisateur($user);
        $user->setFormateur($formateur);

        // Persister le compte ; EasyAdmin persistera le formateur
        $this->entityManager->persist($user);

        return $user;
    }

    /**
     * Synchronise les informations du compte utilisateur.
     */
    public function preparerModification(Formateur $formateur): User
    {
        // Récupérer le compte utilisateur existant
        $user = $formateur->getCompteUtilisateur();

        // Créer un compte si aucun compte n'est associé
        if ($user === null) {
            $user = new User();

            $formateur->setCompteUtilisateur($user);
            $user->setFormateur($formateur);
            $user->setRoles(['ROLE_FORMATEUR']);
            $user->setIsVerified(true);

            $this->entityManager->persist($user);
        }

        // Synchroniser les informations du compte
        $user->setEmail($formateur->getEmail());
        $user->setNomUtilisateur($formateur->getNom());
        $user->setPrenomUtilisateur($formateur->getPrenom());
        $user->setRoles(['ROLE_FORMATEUR']);

        // Récupérer le mot de passe saisi
        $password = $formateur->getPassword();

        // Si aucun nouveau mot de passe n'est fourni,
        // conserver le mot de passe actuel
       
if ($password === null || $password === '') {
    $userPassword = $user->getPassword();

    if ($userPassword !== null) {
        $formateur->setPassword($userPassword);
    }

    return $user;
}


        // Ne pas hacher à nouveau un hash identique à celui du compte
        if ($password === $user->getPassword()) {
            return $user;
        }

        // Hacher le nouveau mot de passe
        $hashedPassword = $this->userPasswordHasher->hashPassword(
            $user,
            $password
        );

        $user->setPassword($hashedPassword);
        $formateur->setPassword($hashedPassword);

        return $user;
    }
}
