<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use App\Repository\FormateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        FormationRepository $formationRepository,
        FormateurRepository $formateurRepository
    ): Response {
        $formations = $formationRepository->findAll();
        $formateurs = $formateurRepository->findAll();

        return $this->render('home/index.html.twig', [
            'formations' => $formations,
            'formateurs' => $formateurs,
        ]);
    }
}