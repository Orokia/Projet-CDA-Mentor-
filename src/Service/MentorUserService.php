<?php

namespace App\Service;

use App\Dto\MentorFormateurInfo;
use App\Dto\MentorStudent;
use App\Dto\MentorUser;
use App\Entity\User;

class MentorUserService
{
    public function build(User $user): MentorUser
    {
        $mentorUser = new MentorUser();

        // =====================================
        // Informations du compte
        // =====================================

        $mentorUser
            ->setIdCompte($user->getId())
            ->setEmail($user->getEmail())
            ->setRoles($user->getRoles())
            ->setNom($user->getNomUtilisateur())
            ->setPrenom($user->getPrenomUtilisateur())
            ->setPhoto($user->getPhotoUtilisateur());


        // =====================================
        // STUDENT
        // =====================================

        $student = $user->getStudent();

        if ($student !== null) {

            $studentDto = new MentorStudent();

            $studentDto
                ->setId($student->getId())
                ->setNom($student->getNom())
                ->setPrenom($student->getPrenom())
                ->setPhoto($student->getPhoto());

            $mentorUser->setStudent($studentDto);
        }


        // =====================================
        // FORMATEUR
        // =====================================

        $formateur = $user->getFormateur();

        if ($formateur !== null) {

            $formateurDto = new MentorFormateurInfo();

            $formateurDto
                ->setId($formateur->getId())
                ->setExperience($formateur->getExperience())
                ->setLangue($formateur->getLangue())
                ->setLocalisation($formateur->getLocalisation())
                ->setCreatedAt($formateur->getCreatedAt())
                ->setVideoPresentation($formateur->getVideoPresentation())
                ->setImage($formateur->getImage());

            $mentorUser->setFormateurInfo($formateurDto);
        }


        return $mentorUser;
    }
}