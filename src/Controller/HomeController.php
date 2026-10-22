<?php

namespace App\Controller;

// Importation du service utilisé par la page d'accueil
use App\Service\HomeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Contrôleur responsable de la page d'accueil
final class HomeController extends AbstractController
{
    /**
     * Injection du service HomeService.
     */
    public function __construct(
        private HomeService $homeService
    ) {
    }

    /**
     * Affiche la page d'accueil.
     *
     * Route : /
     * Template : templates/home/index.html.twig
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        // Récupération de toutes les formations via le service
        $formations = $this->homeService->getAllFormations();

        // Récupération de tous les formateurs via le service
        $formateurs = $this->homeService->getAllFormateurs();

        // Transmission des données au template Twig
        return $this->render('home/index.html.twig', [
            'formations' => $formations,
            'formateurs' => $formateurs,
        ]);
    }
}
