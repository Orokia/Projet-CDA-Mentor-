<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\FormateurDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FormateurDashboardController extends AbstractController
{
    public function __construct(
        private FormateurDashboardService $formateurDashboardService
    ) {
    }

    /**
     * Affiche l'espace du formateur connecté.
     *
     * Route : /espace-formateur
     * Template : templates/dashboard/formateur.html.twig
     */
    #[Route('/espace-formateur', name: 'app_formateur_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $formateur = $user->getFormateur();

        if ($formateur === null) {
            throw $this->createAccessDeniedException(
                'Aucun profil formateur associé à votre compte.'
            );
        }

        return $this->render('dashboard/formateur.html.twig', [
            'formateur' => $formateur,
            'tableau' => $this->formateurDashboardService->build($formateur),
        ]);
    }
}
