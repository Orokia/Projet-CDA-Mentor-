<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\RegistrationService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private RegistrationService $registrationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Affiche le formulaire et traite l'inscription.
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        // Préparer le formulaire d'inscription
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer le mot de passe saisi
            $plainPassword = $form->get('plainPassword')->getData();

            // Créer le compte et le profil étudiant via le service
            $user = $this->registrationService->register(
                $user,
                $plainPassword
            );

            // Envoyer l'e-mail de confirmation
            try {
                $this->emailVerifier->sendEmailConfirmation(
                    'app_verify_email',
                    $user,
                    (new TemplatedEmail())
                        ->from(new Address(
                            'coulibaliorokia7@gmail.com',
                            'Mentor'
                        ))
                        ->to((string) $user->getEmail())
                        ->subject('Confirmez votre adresse e-mail')
                        ->htmlTemplate(
                            'registration/confirmation_email.html.twig'
                        )
                );

                $this->addFlash(
                    'success',
                    'Votre compte a été créé. Consultez votre boîte mail pour confirmer votre adresse.'
                );
            } catch (\Throwable $e) {
                // Enregistrer le détail technique sans l'afficher à l'utilisateur
                $this->logger->error(
                    'Échec de l’envoi de l’e-mail de confirmation.',
                    ['exception' => $e]
                );

                $this->addFlash(
                    'warning',
                    'Votre compte a été créé, mais l’e-mail de confirmation n’a pas pu être envoyé. Veuillez réessayer plus tard.'
                );
            }

            return $this->redirectToRoute('app_home');
        }

        // Afficher le formulaire en cas de première visite ou d'erreurs
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Vérifie le lien de confirmation reçu par e-mail.
     */
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        UserRepository $userRepository
    ): Response {
        // Récupérer l'identifiant de l'utilisateur depuis le lien
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        // Retrouver le compte concerné
        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // Vérifier le lien et confirmer l'adresse e-mail
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash(
                'verify_email_error',
                $translator->trans(
                    $exception->getReason(),
                    [],
                    'VerifyEmailBundle'
                )
            );

            return $this->redirectToRoute('app_register');
        }

        // Informer l'utilisateur de la confirmation réussie
        $this->addFlash(
            'success',
            'Votre adresse e-mail a été confirmée.'
        );

        return $this->redirectToRoute('app_register');
    }
}
