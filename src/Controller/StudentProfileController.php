<?php

namespace App\Controller;

use App\Entity\Student;
use App\Entity\User;
use App\Form\StudentProfileType;
use App\Service\StudentProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class StudentProfileController extends AbstractController
{
    public function __construct(
        private StudentProfileService $studentProfileService
    ) {
    }

    /**
     * Affiche et met à jour le profil de l'étudiant connecté.
     */

    #[Route('/student/profil', name: 'app_student_profile')]
    public function index(
        Request $request,
        SluggerInterface $slugger
    ): Response {
        // Récupération de l'utilisateur connecté
        $user = $this->getUser();

        // Vérification que l'utilisateur est bien connecté
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Récupération du profil étudiant associé au compte
        $student = $user->getStudent();

        // Vérification de l'existence du profil étudiant
        if (!$student instanceof Student) {
            throw $this->createNotFoundException(
                'Aucun profil étudiant associé à ce compte.'
            );
        }

        // Préremplissage du profil avec les informations du compte
        $student->setPrenom($user->getPrenomUtilisateur() ?? '');
        $student->setNom($user->getNomUtilisateur() ?? '');
        $student->setEmail($user->getEmail() ?? '');

        // Synchronisation de la photo existante
        if ($user->getPhotoUtilisateur()) {
            $student->setPhoto($user->getPhotoUtilisateur());
        }

        // Création du formulaire associé à l'étudiant
        $form = $this->createForm(StudentProfileType::class, $student);

        // Traitement de la requête HTTP et des données du formulaire
        $form->handleRequest($request);

        // Vérification de la soumission et de la validité du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupération du fichier photo envoyé, s'il existe
            $photoFile = $form->get('photoFile')->getData();

            if ($photoFile) {
                // Récupération du nom original sans extension
                $originalFilename = pathinfo(
                    $photoFile->getClientOriginalName(),
                    PATHINFO_FILENAME
                );

                // Nettoyage du nom de fichier pour éviter les caractères indésirables
                $safeFilename = $slugger->slug($originalFilename);

                // Création d'un nom unique pour éviter les collisions
                $newFilename = $safeFilename . '-' . bin2hex(random_bytes(6))
                    . '.' . $photoFile->guessExtension();

                try {
                    // Déplacement de la photo vers le dossier public,
                    // le même que celui utilisé par l'admin EasyAdmin
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir')
                            . '/public/assets/images/utilisateurs',
                        $newFilename
                    );
                } catch (FileException) {
                    // Message d'erreur si le transfert échoue
                    $this->addFlash(
                        'danger',
                        'Impossible de téléverser la photo.'
                    );

                    return $this->redirectToRoute('app_student_profile');
                }

                // Synchronisation de la nouvelle photo via le service
                $this->studentProfileService->updatePhoto(
                    $student,
                    $user,
                    $newFilename
                );
            }

            // Synchronisation des informations et enregistrement en base
            $this->studentProfileService->updateProfile($student, $user);

            // Confirmation de la mise à jour
            $this->addFlash(
                'success',
                'Ton profil a été mis à jour.'
            );

            // Redirection pour éviter de renvoyer le formulaire à l'actualisation
            return $this->redirectToRoute('app_student_profile');
        }

        // Affichage du formulaire et du profil dans Twig
        return $this->render('student_profile/index.html.twig', [
            'form' => $form,
            'student' => $student,
        ]);
    }
}
