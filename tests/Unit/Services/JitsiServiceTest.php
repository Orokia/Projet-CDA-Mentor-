<?php

namespace App\Tests\Unit\Services;

use App\Entity\Formateur;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Entity\User;
use App\Service\JitsiService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

final class JitsiServiceTest extends TestCase
{
    private const APP_ID = 'vpaas-magic-cookie-test';
    private const KEY_ID = 'vpaas-magic-cookie-test/abc123';
    private const SALLE = 'Mentor-Reservation-12-a1b2c3d4';

    private string $cheminCle;
    private string $clePublique;

    protected function setUp(): void
    {
        $cle = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($cle);

        openssl_pkey_export($cle, $clePrivee);
        $this->clePublique = openssl_pkey_get_details($cle)['key'];

        $this->cheminCle = tempnam(sys_get_temp_dir(), 'jaas');
        file_put_contents($this->cheminCle, $clePrivee);
    }

    protected function tearDown(): void
    {
        unlink($this->cheminCle);
    }

    private function service(string $appId = self::APP_ID, ?string $cheminCle = null): JitsiService
    {
        return new JitsiService($appId, self::KEY_ID, $cheminCle ?? $this->cheminCle);
    }

    /**
     * Réservation confirmée, avec le compte de son étudiant et de son formateur.
     *
     * @return array{Reservation, User, User}
     */
    private function reservationConfirmee(): array
    {
        $student = new Student();
        $formateur = new Formateur();

        $compteEtudiant = (new User())
            ->setEmail('amadou@example.test')
            ->setPrenomUtilisateur('Amadou')
            ->setNomUtilisateur('Diallo')
            ->setStudent($student);

        $compteFormateur = (new User())
            ->setEmail('aminata@example.test')
            ->setPrenomUtilisateur('Aminata')
            ->setNomUtilisateur('Diallo')
            ->setFormateur($formateur);

        $reservation = (new Reservation())
            ->setStatut('confirmée')
            ->setLienVisio('https://meet.jit.si/' . self::SALLE)
            ->setStudent($student)
            ->setFormateur($formateur);

        return [$reservation, $compteEtudiant, $compteFormateur];
    }

    private function decoderJeton(string $jeton): object
    {
        return JWT::decode($jeton, new Key($this->clePublique, 'RS256'));
    }

    public function testEstConfigureExigeLesIdentifiantsEtLaCle(): void
    {
        self::assertTrue($this->service()->estConfigure());
        self::assertFalse($this->service('')->estConfigure());
        self::assertFalse($this->service(cheminCle: $this->cheminCle . '.absent')->estConfigure());
    }

    public function testPeutRejoindreAccepteLesParticipantsDeLaSeance(): void
    {
        [$reservation, $compteEtudiant, $compteFormateur] = $this->reservationConfirmee();

        self::assertTrue($this->service()->peutRejoindre($reservation, $compteEtudiant));
        self::assertTrue($this->service()->peutRejoindre($reservation, $compteFormateur));
    }

    public function testPeutRejoindreRefuseLesAutresComptes(): void
    {
        [$reservation] = $this->reservationConfirmee();

        $autreEtudiant = (new User())->setStudent(new Student());
        $autreFormateur = (new User())->setFormateur(new Formateur());

        self::assertFalse($this->service()->peutRejoindre($reservation, $autreEtudiant));
        self::assertFalse($this->service()->peutRejoindre($reservation, $autreFormateur));
        self::assertFalse($this->service()->peutRejoindre($reservation, new User()));
    }

    public function testPeutRejoindreRefuseUneSeanceNonConfirmee(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        $reservation->setStatut('annulée');

        self::assertFalse($this->service()->peutRejoindre($reservation, $compteEtudiant));
    }

    public function testPeutRejoindreRefuseUneSeanceSansLien(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        $reservation->setLienVisio(null);

        self::assertFalse($this->service()->peutRejoindre($reservation, $compteEtudiant));
    }

    public function testPreparerSalleRetourneLesParametresDeLIframeApi(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        $salle = $this->service()->preparerSalle($reservation, $compteEtudiant);

        self::assertSame('8x8.vc', $salle['domaine']);
        self::assertSame(self::APP_ID . '/' . self::SALLE, $salle['salle']);
        self::assertSame(
            'https://8x8.vc/' . self::APP_ID . '/external_api.js',
            $salle['scriptUrl']
        );
    }

    public function testLeJetonEstSigneEtLimiteALaSalle(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        $jeton = $this->service()->preparerSalle($reservation, $compteEtudiant)['jeton'];
        $entete = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $jeton)[0]));
        $charge = $this->decoderJeton($jeton);

        self::assertSame('RS256', $entete->alg);
        self::assertSame(self::KEY_ID, $entete->kid);
        self::assertSame('jitsi', $charge->aud);
        self::assertSame('chat', $charge->iss);
        self::assertSame(self::APP_ID, $charge->sub);
        self::assertSame(self::SALLE, $charge->room);
        self::assertFalse($charge->context->room->regex);
        self::assertSame('Amadou Diallo', $charge->context->user->name);
        self::assertGreaterThan(time(), $charge->exp);
        // Valable même si l'horloge du serveur avance de quelques minutes
        self::assertLessThanOrEqual(time() - 300, $charge->nbf);
    }

    public function testSeulLeFormateurEstModerateur(): void
    {
        [$reservation, $compteEtudiant, $compteFormateur] = $this->reservationConfirmee();

        $jetonEtudiant = $this->service()->preparerSalle($reservation, $compteEtudiant)['jeton'];
        $jetonFormateur = $this->service()->preparerSalle($reservation, $compteFormateur)['jeton'];

        self::assertSame('false', $this->decoderJeton($jetonEtudiant)->context->user->moderator);
        self::assertSame('true', $this->decoderJeton($jetonFormateur)->context->user->moderator);
    }

    public function testEstConfigureRefuseUnIdentifiantDeCleVide(): void
    {
        self::assertFalse((new JitsiService(self::APP_ID, '', $this->cheminCle))->estConfigure());
    }

    public function testPeutRejoindreExigeLeStatutExact(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        // La comparaison est stricte : casse et accent comptent
        foreach (['Confirmée', 'confirmee', 'confirmée ', 'en attente', ''] as $statut) {
            $reservation->setStatut($statut);

            self::assertFalse(
                $this->service()->peutRejoindre($reservation, $compteEtudiant),
                sprintf('Le statut « %s » ne doit pas ouvrir la salle.', $statut)
            );
        }
    }

    public function testPeutRejoindreRefuseUnLienVide(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        $reservation->setLienVisio('');

        self::assertFalse($this->service()->peutRejoindre($reservation, $compteEtudiant));
    }

    public function testPeutRejoindreRefuseUneReservationSansParticipant(): void
    {
        // Ni étudiant ni formateur : un compte sans profil ne doit pas
        // être confondu avec une réservation sans profil (null === null)
        $reservation = (new Reservation())
            ->setStatut('confirmée')
            ->setLienVisio('https://meet.jit.si/' . self::SALLE);

        self::assertFalse($this->service()->peutRejoindre($reservation, new User()));
    }

    public function testPreparerSalleTolereUneBarreObliqueFinale(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        $reservation->setLienVisio('https://meet.jit.si/' . self::SALLE . '/');

        self::assertSame(
            self::APP_ID . '/' . self::SALLE,
            $this->service()->preparerSalle($reservation, $compteEtudiant)['salle']
        );
    }

    public function testUneCleIllisibleDonneUnMessageLisible(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La visioconférence est momentanément indisponible.');

        $this->service(cheminCle: $this->cheminCle . '.absent')->preparerSalle($reservation, $compteEtudiant);
    }

    public function testUneCleInvalideDonneUnMessageLisible(): void
    {
        [$reservation, $compteEtudiant] = $this->reservationConfirmee();

        file_put_contents($this->cheminCle, 'cle-invalide');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La visioconférence est momentanément indisponible.');

        $this->service()->preparerSalle($reservation, $compteEtudiant);
    }
}
