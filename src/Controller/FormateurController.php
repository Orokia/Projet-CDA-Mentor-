<?php

namespace App\Controller;

use App\Service\FormateurService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FormateurController extends AbstractController
{
    public function __construct(
        private FormateurService $formateurService
    ) {
    }

    /**
     * Affiche la liste des formateurs.
     */
    #[Route('/formateurs', name: 'app_formateurs', methods: ['GET'])]
    public function index(): Response
    {
        $formateurs = $this->formateurService->getAllFormateurs();

        return $this->render('formateur/index.html.twig', [
            'formateurs' => $formateurs,
        ]);
    }

    /**
     * Affiche le profil d'un formateur.
     */
    #[Route('/formateur/{id}', name: 'app_formateur_profil', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function profil(int $id): Response
    {
        $formateur = $this->formateurService->getFormateurById($id);

        if ($formateur === null) {
            throw $this->createNotFoundException(
                'Le formateur demandé est introuvable.'
            );
        }

        return $this->render('formateur/profil.html.twig', [
            'formateur' => $formateur,
        ]);
    }
}
