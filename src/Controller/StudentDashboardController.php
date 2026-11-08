<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StudentDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StudentDashboardController extends AbstractController
{
    public function __construct(
        private StudentDashboardService $studentDashboardService
    ) {
    }

    /**
     * Affiche le tableau de bord de l'étudiant connecté.
     *
     * Route : /tableau-de-bord
     * Template : templates/dashboard/student.html.twig
     */
    #[Route('/tableau-de-bord', name: 'app_student_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $student = $user->getStudent();

        if ($student === null) {
            // Un formateur a son propre espace
            if ($user->getFormateur() !== null) {
                return $this->redirectToRoute('app_formateur_dashboard');
            }

            throw $this->createNotFoundException(
                'Aucun profil étudiant associé à votre compte.'
            );
        }

        return $this->render('dashboard/student.html.twig', [
            'student' => $student,
            'tableau' => $this->studentDashboardService->build($student),
        ]);
    }
}
