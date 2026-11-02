<?php

namespace App\Tests\Unit\Security;

use App\Security\AppCustomAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class AppCustomAuthenticatorTest extends TestCase
{
    private AppCustomAuthenticator $authenticator;

    protected function setUp(): void
    {
        // Le routeur est simulé : seules les deux routes utilisées sont connues
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnMap([
                ['app_login', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/login'],
                ['app_home', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/'],
            ]);

        $this->authenticator = new AppCustomAuthenticator($urlGenerator);
    }

    private function creerRequete(string $methode, string $chemin, array $parametres = []): Request
    {
        $request = Request::create($chemin, $methode, $parametres);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    public function testSupporteUniquementLeFormulaireDeConnexionEnPost(): void
    {
        $this->assertTrue($this->authenticator->supports($this->creerRequete('POST', '/login')));
        $this->assertFalse($this->authenticator->supports($this->creerRequete('GET', '/login')));
        $this->assertFalse($this->authenticator->supports($this->creerRequete('POST', '/register')));
    }

    public function testAuthenticateConstruitLePassportDepuisLeFormulaire(): void
    {
        $request = $this->creerRequete('POST', '/login', [
            'email' => 'mariam.toure@exemple.fr',
            'password' => 'Soleil2026!',
            '_csrf_token' => 'jeton-csrf-du-formulaire',
        ]);

        $passport = $this->authenticator->authenticate($request);

        $this->assertSame(
            'mariam.toure@exemple.fr',
            $passport->getBadge(UserBadge::class)->getUserIdentifier()
        );
        $this->assertSame(
            'Soleil2026!',
            $passport->getBadge(PasswordCredentials::class)->getPassword()
        );

        $csrf = $passport->getBadge(CsrfTokenBadge::class);
        $this->assertSame('authenticate', $csrf->getCsrfTokenId());
        $this->assertSame('jeton-csrf-du-formulaire', $csrf->getCsrfToken());

        $this->assertTrue($passport->hasBadge(RememberMeBadge::class));
    }

    public function testAuthenticateMemoriseLEmailPourReafficherLeFormulaire(): void
    {
        $request = $this->creerRequete('POST', '/login', [
            'email' => 'mariam.toure@exemple.fr',
            'password' => 'mauvais-mot-de-passe',
        ]);

        $this->authenticator->authenticate($request);

        $this->assertSame(
            'mariam.toure@exemple.fr',
            $request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME)
        );
    }

    public function testConnexionReussieRedirigeVersLaPageDemandee(): void
    {
        $request = $this->creerRequete('POST', '/login');
        $request->getSession()->set('_security.main.target_path', 'http://localhost/mes-reservations');

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createStub(TokenInterface::class),
            'main'
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://localhost/mes-reservations', $response->getTargetUrl());
    }

    public function testConnexionReussieSansPageDemandeeRedirigeVersLAccueil(): void
    {
        $response = $this->authenticator->onAuthenticationSuccess(
            $this->creerRequete('POST', '/login'),
            $this->createStub(TokenInterface::class),
            'main'
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/', $response->getTargetUrl());
    }

    public function testConnexionEchoueeRedirigeVersLeFormulaireAvecLErreur(): void
    {
        $request = $this->creerRequete('POST', '/login');
        $erreur = new BadCredentialsException('Identifiants invalides.');

        $response = $this->authenticator->onAuthenticationFailure($request, $erreur);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getTargetUrl());
        $this->assertSame(
            $erreur,
            $request->getSession()->get(SecurityRequestAttributes::AUTHENTICATION_ERROR)
        );
    }

    public function testVisiteurAnonymeEstEnvoyeVersLaConnexion(): void
    {
        $response = $this->authenticator->start($this->creerRequete('GET', '/mes-reservations'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getTargetUrl());
    }
}
