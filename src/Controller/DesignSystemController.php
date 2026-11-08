<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Guide de style du skin Mentor : tokens et composants réutilisables.
 * Page de travail pour l'équipe, introuvable en production.
 */
final class DesignSystemController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment
    ) {
    }

    #[Route('/design-system', name: 'app_design_system', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->environment === 'prod') {
            throw $this->createNotFoundException();
        }

        return $this->render('design_system/index.html.twig');
    }
}
