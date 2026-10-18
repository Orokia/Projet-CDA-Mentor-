<?php

namespace App\Controller;

use App\Entity\Student;
use App\Entity\User;
use App\Form\StudentProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final class StudentProfileController extends AbstractController
{
    #[Route('/student/profil', name: 'app_student_profile')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que le compte connecté est bien associé à un étudiant.
        $student = $user->getStudent();

        if (!$student instanceof Student) {
            throw $this->createNotFoundException(
                'Aucun profil étudiant associé à ce compte.'
            );
        }

        // Préremplir le profil avec les données du compte.
        $student->setPrenom($user->getPrenomUtilisateur() ?? '');
        $student->setNom($user->getNomUtilisateur() ?? '');
        $student->setEmail($user->getEmail() ?? '');

        if ($user->getPhotoUtilisateur()) {
            $student->setPhoto($user->getPhotoUtilisateur());
        }

        $form = $this->createForm(StudentProfileType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoFile')->getData();

            if ($photoFile) {
                $originalFilename = pathinfo(
                    $photoFile->getClientOriginalName(),
                    PATHINFO_FILENAME
                );

                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . bin2hex(random_bytes(6))
                    . '.' . $photoFile->guessExtension();

                try {
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir')
                            . '/public/uploads/students',
                        $newFilename
                    );
                } catch (FileException) {
                    $this->addFlash(
                        'danger',
                        'Impossible de téléverser la photo.'
                    );

                    return $this->redirectToRoute('app_student_profile');
                }

                $student->setPhoto($newFilename);
                $user->setPhotoUtilisateur($newFilename);
            }

            // Synchroniser les données entre Student et User.
            $user->setPrenomUtilisateur($student->getPrenom());
            $user->setNomUtilisateur($student->getNom());
            $user->setEmail($student->getEmail());
            $user->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->flush();

            $this->addFlash(
                'success',
                'Ton profil a été mis à jour.'
            );

            return $this->redirectToRoute('app_student_profile');
        }

        return $this->render('student_profile/index.html.twig', [
            'form' => $form,
            'student' => $student,
        ]);
    }
}
