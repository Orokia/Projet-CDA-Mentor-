<?php

namespace App\DataFixtures;

use App\Entity\Formateur;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class Data extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // =====================================================
        // ADMIN
        // =====================================================

        $admin = new User();

        $admin->setEmail('admin@mentor.fr');
        $admin->setNomUtilisateur('ADMIN');
        $admin->setPrenomUtilisateur('Mentor');

        $admin->setRoles([
            'ROLE_ADMIN'
        ]);

        $admin->setPassword(
            $this->passwordHasher->hashPassword(
                $admin,
                'Admin123!'
            )
        );

        $admin->setIsVerified(true);

        $manager->persist($admin);


        // =====================================================
        // STUDENT
        // =====================================================

        $studentUser = new User();

        $studentUser->setEmail('student@mentor.fr');
        $studentUser->setNomUtilisateur('DUPONT');
        $studentUser->setPrenomUtilisateur('Jean');

        $studentUser->setRoles([
            'ROLE_USER'
        ]);

        $studentUser->setPassword(
            $this->passwordHasher->hashPassword(
                $studentUser,
                'Student123!'
            )
        );

        $studentUser->setIsVerified(true);

        $manager->persist($studentUser);


        // =====================================================
        // FORMATEUR
        // =====================================================

        $formateurUser = new User();

        $formateurUser->setEmail('formateur@mentor.fr');
        $formateurUser->setNomUtilisateur('MARTIN');
        $formateurUser->setPrenomUtilisateur('Sophie');

        $formateurUser->setRoles([
            'ROLE_FORMATEUR'
        ]);

        $formateurUser->setPassword(
            $this->passwordHasher->hashPassword(
                $formateurUser,
                'Formateur123!'
            )
        );

        $formateurUser->setIsVerified(true);


        // =====================================================
        // PROFIL FORMATEUR
        // =====================================================

        $formateur = new Formateur();

        $formateur->setNom('MARTIN');
        $formateur->setPrenom('Sophie');
        $formateur->setEmail('formateur@mentor.fr');

        $formateur->setPassword(
            $this->passwordHasher->hashPassword(
                $formateurUser,
                'Formateur123!'
            )
        );

        $formateur->setExperience('5 ans');
        $formateur->setLangue('Français');
        $formateur->setLocalisation('Paris');

        $formateur->setVideoPresentation(
            'presentation.mp4'
        );

        // Relation User <-> Formateur
        $formateur->setCompteUtilisateur($formateurUser);
        $formateurUser->setFormateur($formateur);

        $manager->persist($formateurUser);
        $manager->persist($formateur);


        // =====================================================
        // SAUVEGARDE
        // =====================================================

        $manager->flush();
    }
}