<?php

namespace App\Controller;

use App\Entity\Formateur;
use App\Repository\FormateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FormateurController extends AbstractController
{
    #[Route('/formateurs', name: 'app_formateurs')]
    public function index(FormateurRepository $formateurRepository): Response
    {
        $formateurs = $formateurRepository->findAll();

        return $this->render('formateur/index.html.twig', [
            'formateurs' => $formateurs,
        ]);
    }

    #[Route('/formateur/{id}', name: 'app_formateur_profil')]
public function profil(Formateur $formateur): Response
{
    return $this->render('formateur/profil.html.twig', [
        'formateur' => $formateur,
    ]);
}
}
