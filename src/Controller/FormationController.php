<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FormationController extends AbstractController
{
    #[Route('/formations', name: 'app_formation')]
    public function index(
        FormationRepository $formationRepository
    ): Response {
        $formations = $formationRepository->findAll();

        return $this->render('formation/index.html.twig', [
            'formations' => $formations,
        ]);
    }


    #[Route('/formations/{id}', name: 'app_formation_show')]
    public function show(
        int $id,
        FormationRepository $formationRepository
    ): Response {
        $formation = $formationRepository->find($id);

        if (!$formation) {
            throw $this->createNotFoundException(
                'Formation introuvable.'
            );
        }

        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
        ]);
    }
}