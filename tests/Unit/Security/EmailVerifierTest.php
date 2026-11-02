<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\ExpiredSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerifierTest extends TestCase
{
    /**
     * Compte non vérifié avec l'identifiant 42, comme après l'inscription.
     */
    private function creerUtilisateur(): User
    {
        $user = (new User())
            ->setEmail('ibrahim.sangare@exemple.fr')
            ->setNomUtilisateur('Sangaré')
            ->setPrenomUtilisateur('Ibrahim');

        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 42);

        return $user;
    }

    /**
     * Double de VerifyEmailHelperInterface pour la validation du lien.
     *
     * validateEmailConfirmationFromRequest() n'est déclarée que par un @method
     * de l'interface, et VerifyEmailHelper est final : PHPUnit ne peut pas la simuler.
     */
    private function creerHelperDeValidation(?VerifyEmailExceptionInterface $erreur = null): VerifyEmailHelperInterface
    {
        return new class($erreur) implements VerifyEmailHelperInterface {
            /** @var list<array{Request, string, string}> */
            public array $appels = [];

            public function __construct(private ?VerifyEmailExceptionInterface $erreur)
            {
            }

            public function generateSignature(string $routeName, string $userId, string $userEmail, array $extraParams = []): VerifyEmailSignatureComponents
            {
                throw new \LogicException('Non utilisé par la validation.');
            }

            public function validateEmailConfirmation(string $signedUrl, string $userId, string $userEmail): void
            {
                throw new \LogicException('Méthode dépréciée, ne doit plus être appelée.');
            }

            public function validateEmailConfirmationFromRequest(Request $request, string $userId, string $userEmail): void
            {
                $this->appels[] = [$request, $userId, $userEmail];

                if ($this->erreur !== null) {
                    throw $this->erreur;
                }
            }
        };
    }

    public function testSendEmailConfirmationAjouteLeLienSigneAuMessage(): void
    {
        $user = $this->creerUtilisateur();
        $lienSigne = 'https://mentor.exemple.fr/verify/email?expires=1793000000&id=42&signature=abc123&token=def456';

        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper
            ->expects($this->once())
            ->method('generateSignature')
            ->with('app_verify_email', '42', 'ibrahim.sangare@exemple.fr', ['id' => 42])
            ->willReturn(new VerifyEmailSignatureComponents(
                new \DateTimeImmutable('+1 hour'),
                $lienSigne,
                time()
            ));

        $email = (new TemplatedEmail())
            ->to('ibrahim.sangare@exemple.fr')
            ->subject('Confirmez votre adresse e-mail')
            ->context(['prenom' => 'Ibrahim']);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($email));

        // L'envoi ne touche pas à la base
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method($this->anything());

        (new EmailVerifier($verifyEmailHelper, $mailer, $entityManager))
            ->sendEmailConfirmation('app_verify_email', $user, $email);

        $contexte = $email->getContext();

        // Le contexte existant est conservé et enrichi
        $this->assertSame('Ibrahim', $contexte['prenom']);
        $this->assertSame($lienSigne, $contexte['signedUrl']);
        $this->assertSame('%count% hour|%count% hours', $contexte['expiresAtMessageKey']);
        $this->assertSame(['%count%' => 1], $contexte['expiresAtMessageData']);
    }

    public function testHandleEmailConfirmationValideLeCompte(): void
    {
        $user = $this->creerUtilisateur();
        $request = Request::create('/verify/email?id=42');
        $verifyEmailHelper = $this->creerHelperDeValidation();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($user);
        $entityManager
            ->expects($this->once())
            ->method('flush');

        (new EmailVerifier($verifyEmailHelper, $this->createStub(MailerInterface::class), $entityManager))
            ->handleEmailConfirmation($request, $user);

        $this->assertSame(
            [[$request, '42', 'ibrahim.sangare@exemple.fr']],
            $verifyEmailHelper->appels
        );
        $this->assertTrue($user->isVerified());
    }

    public function testHandleEmailConfirmationRefuseUnLienExpire(): void
    {
        $user = $this->creerUtilisateur();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method($this->anything());

        $emailVerifier = new EmailVerifier(
            $this->creerHelperDeValidation(new ExpiredSignatureException()),
            $this->createStub(MailerInterface::class),
            $entityManager
        );

        try {
            $emailVerifier->handleEmailConfirmation(Request::create('/verify/email?id=42'), $user);
            $this->fail('Un lien expiré aurait dû être refusé.');
        } catch (ExpiredSignatureException) {
            $this->assertFalse($user->isVerified());
        }
    }
}
