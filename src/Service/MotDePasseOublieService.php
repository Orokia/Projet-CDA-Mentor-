<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * « Mot de passe oublié » : envoi d'un lien à usage unique, valable une heure,
 * puis choix d'un nouveau mot de passe.
 */
class MotDePasseOublieService
{
    public function __construct(
        private UserRepository $userRepository,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Autowire('%mailer_expediteur%')]
        private string $expediteur,
    ) {
    }

    /**
     * Envoie le lien de réinitialisation si un compte existe pour cette adresse.
     *
     * Ne dit jamais si le compte existe : le contrôleur affiche le même message
     * dans tous les cas, pour qu'on ne puisse pas deviner les adresses inscrites.
     */
    public function demanderReinitialisation(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => trim($email)]);

        if (!$user instanceof User) {
            return;
        }

        try {
            $jeton = $this->resetPasswordHelper->generateResetToken($user);
        } catch (TooManyPasswordRequestsException) {
            // Un lien encore valable a été envoyé récemment : pas de nouvel e-mail
            return;
        } catch (ResetPasswordExceptionInterface $exception) {
            $this->logger->warning('Réinitialisation du mot de passe impossible.', [
                'userId' => $user->getId(),
                'raison' => $exception->getReason(),
            ]);

            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->expediteur, 'Mentor'))
            ->to((string) $user->getEmail())
            ->subject('Réinitialisation de votre mot de passe Mentor')
            ->htmlTemplate('mot_de_passe_oublie/email.html.twig')
            ->context([
                'jeton' => $jeton,
                'dureeMinutes' => intdiv($this->resetPasswordHelper->getTokenLifetime(), 60),
                'prenom' => $user->getPrenomUtilisateur(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            // Le lien inutilisable est supprimé : une nouvelle demande reste possible tout de suite
            $this->resetPasswordHelper->removeResetRequest($jeton->getToken());

            throw $exception;
        }

        $this->logger->info('Lien de réinitialisation du mot de passe envoyé.', ['userId' => $user->getId()]);
    }

    /**
     * @throws \InvalidArgumentException lien invalide, déjà utilisé ou expiré (message affichable)
     */
    public function verifierJeton(string $jeton): User
    {
        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($jeton);
        } catch (ResetPasswordExceptionInterface $exception) {
            throw new \InvalidArgumentException('Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.', 0, $exception);
        }

        \assert($user instanceof User);

        return $user;
    }

    /**
     * Enregistre le nouveau mot de passe ; le lien ne peut plus être réutilisé.
     *
     * @throws \InvalidArgumentException lien invalide, déjà utilisé ou expiré (message affichable)
     */
    public function changerMotDePasse(string $jeton, string $motDePasse): User
    {
        $user = $this->verifierJeton($jeton);

        // Usage unique
        $this->resetPasswordHelper->removeResetRequest($jeton);

        $user->setPassword($this->passwordHasher->hashPassword($user, $motDePasse));

        // Le lien a été reçu sur cette adresse : elle est de fait confirmée
        $user->setIsVerified(true);

        $this->entityManager->flush();

        $this->logger->info('Mot de passe réinitialisé.', ['userId' => $user->getId()]);

        return $user;
    }
}
