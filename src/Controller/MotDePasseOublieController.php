<?php

namespace App\Controller;

use App\Form\DemandeReinitialisationMotDePasseType;
use App\Form\NouveauMotDePasseType;
use App\Service\MotDePasseOublieService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Mot de passe oublié » : demande du lien par e-mail, puis choix d'un nouveau mot de passe.
 */
#[Route('/mot-de-passe-oublie')]
final class MotDePasseOublieController extends AbstractController
{
    // Le jeton est gardé en session, hors de l'URL (historique, en-tête Referer)
    private const CLE_SESSION_JETON = 'reinitialisation_mot_de_passe_jeton';

    public function __construct(
        private MotDePasseOublieService $motDePasseOublieService,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'app_mot_de_passe_oublie', methods: ['GET', 'POST'])]
    public function demander(Request $request): Response
    {
        $formulaire = $this->createForm(DemandeReinitialisationMotDePasseType::class);
        $formulaire->handleRequest($request);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            try {
                $this->motDePasseOublieService->demanderReinitialisation(
                    (string) $formulaire->get('email')->getData()
                );
            } catch (\Throwable $exception) {
                $this->logger->error('Échec de l\'envoi de l\'e-mail de réinitialisation du mot de passe.', [
                    'exception' => $exception,
                ]);

                $this->addFlash('error', 'L\'e-mail n\'a pas pu être envoyé suite à une erreur technique. Veuillez réessayer plus tard.');

                return $this->redirectToRoute('app_mot_de_passe_oublie');
            }

            // Même message que le compte existe ou non
            $this->addFlash('success', 'Si un compte existe pour cette adresse, vous allez recevoir un e-mail avec un lien pour choisir un nouveau mot de passe. Le lien est valable 1 heure.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('mot_de_passe_oublie/demander.html.twig', [
            'formulaire' => $formulaire,
        ]);
    }

    /**
     * Lien reçu par e-mail : le jeton passe en session, puis l'URL est nettoyée.
     */
    #[Route('/reinitialiser/{jeton}', name: 'app_reinitialiser_mot_de_passe_lien', methods: ['GET'])]
    public function ouvrirLien(Request $request, string $jeton): Response
    {
        $request->getSession()->set(self::CLE_SESSION_JETON, $jeton);

        return $this->redirectToRoute('app_reinitialiser_mot_de_passe');
    }

    #[Route('/reinitialiser', name: 'app_reinitialiser_mot_de_passe', methods: ['GET', 'POST'])]
    public function reinitialiser(Request $request): Response
    {
        $session = $request->getSession();
        $jeton = $session->get(self::CLE_SESSION_JETON);

        if (!\is_string($jeton) || '' === $jeton) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.');

            return $this->redirectToRoute('app_mot_de_passe_oublie');
        }

        try {
            $this->motDePasseOublieService->verifierJeton($jeton);
        } catch (\InvalidArgumentException $exception) {
            $session->remove(self::CLE_SESSION_JETON);
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_mot_de_passe_oublie');
        }

        $formulaire = $this->createForm(NouveauMotDePasseType::class);
        $formulaire->handleRequest($request);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            try {
                $this->motDePasseOublieService->changerMotDePasse(
                    $jeton,
                    (string) $formulaire->get('plainPassword')->getData()
                );
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToRoute('app_mot_de_passe_oublie');
            } finally {
                $session->remove(self::CLE_SESSION_JETON);
            }

            $this->addFlash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('mot_de_passe_oublie/reinitialiser.html.twig', [
            'formulaire' => $formulaire,
        ]);
    }
}
