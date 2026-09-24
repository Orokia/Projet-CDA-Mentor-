<?php

namespace App\Controller;

use App\Repository\FormateurRepository;
use App\Repository\FormationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route(
        '/formations/{formationId}/choisir-formateur',
        name: 'app_formation_formateur',
        methods: ['POST']
    )]
    public function choisirFormateur(
        int $formationId,
        Request $request,
        FormationRepository $formationRepository,
        FormateurRepository $formateurRepository
    ): Response {

        $formation = $formationRepository->find($formationId);

        if (!$formation) {
            throw $this->createNotFoundException(
                'Formation introuvable.'
            );
        }

        $formateurId = $request->request->get('formateur');

        if (!$formateurId) {
            $this->addFlash(
                'error',
                'Veuillez sélectionner un formateur.'
            );

            return $this->redirectToRoute(
                'app_formation_show',
                ['id' => $formationId]
            );
        }

        $formateur = $formateurRepository->find($formateurId);

        if (!$formateur) {
            throw $this->createNotFoundException(
                'Formateur introuvable.'
            );
        }

        return $this->redirectToRoute(
            'app_formateur_profil',
            [
                'id' => $formateur->getId(),
            ]
        );
    }
}