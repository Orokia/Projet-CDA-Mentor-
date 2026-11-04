<?php

namespace App\Controller;

// Importation du service qui gère la récupération des formations
// et des formateurs.
use App\Service\FormationService;

// Importation des classes Symfony nécessaires au contrôleur
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Contrôleur responsable des pages liées aux formations
class FormationController extends AbstractController
{
    /**
     * Constructeur du contrôleur.
     *
     * Symfony injecte automatiquement FormationService.
     * Le contrôleur utilise ce service pour récupérer les données.
     */
    public function __construct(
        private FormationService $formationService
    ) {
    }

    /**
     * Affiche la liste de toutes les formations.
     *
     * Route : /formations
     * Template : templates/formation/index.html.twig
     */
    #[Route('/formations', name: 'app_formation', methods: ['GET'])]
    public function index(): Response
    {
        // Récupération de toutes les formations via le service
        $formations = $this->formationService->getAllFormations();

        // Envoi des formations au template Twig pour affichage
        return $this->render('formation/index.html.twig', [
            'formations' => $formations,
        ]);
    }

    /**
     * Affiche le détail d'une formation.
     *
     * Route : /formations/{id}
     * Template : templates/formation/show.html.twig
     *
     * @param int $id Identifiant de la formation
     */
    // 18 chiffres au plus : un identifiant plus long dépasserait PHP_INT_MAX (erreur 500)
    #[Route('/formations/{id}', name: 'app_formation_show', requirements: ['id' => '\d{1,18}'], methods: ['GET'])]
    public function show(int $id): Response
    {
        // Recherche de la formation à partir de son identifiant
        $formation = $this->formationService->getFormationById($id);

        // Si la formation n'existe pas, retourner une erreur 404
        if ($formation === null) {
            throw $this->createNotFoundException(
                'Formation introuvable.'
            );
        }

        // Transmission de la formation au template Twig
        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
        ]);
    }

    /**
     * Récupère le formateur sélectionné pour une formation
     * et redirige l'utilisateur vers son profil.
     *
     * Route : /formations/{formationId}/choisir-formateur
     * Méthode HTTP : POST
     *
     * @param int $formationId Identifiant de la formation
     * @param Request $request Requête contenant le formateur sélectionné
     */
    #[Route(
        '/formations/{formationId}/choisir-formateur',
        name: 'app_formation_formateur',
        requirements: ['formationId' => '\d{1,18}'],
        methods: ['POST']
    )]
    public function choisirFormateur(
        int $formationId,
        Request $request
    ): Response {
        // Vérification de l'existence de la formation
        $formation = $this->formationService
            ->getFormationById($formationId);

        if ($formation === null) {
            throw $this->createNotFoundException(
                'Formation introuvable.'
            );
        }

        // Récupération de l'identifiant du formateur envoyé par le formulaire
        $formateurId = $request->request->get('formateur');

        // Si aucun formateur n'a été sélectionné, afficher un message
        if (
            !$formateurId
            || !ctype_digit((string) $formateurId)
        ) {
            $this->addFlash(
                'error',
                'Veuillez sélectionner un formateur.'
            );

            // Retour à la page de détail de la formation
            return $this->redirectToRoute(
                'app_formation_show',
                ['id' => $formationId]
            );
        }

        // Recherche du formateur sélectionné via le service
        $formateur = $this->formationService
            ->getFormateurById((int) $formateurId);

        // Si le formateur n'existe pas, retourner une erreur 404
        if ($formateur === null) {
            throw $this->createNotFoundException(
                'Formateur introuvable.'
            );
        }

        // Redirection vers le profil du formateur sélectionné
        return $this->redirectToRoute(
            'app_formateur_profil',
            [
                'id' => $formateur->getId(),
            ]
        );
    }
}