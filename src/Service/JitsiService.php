<?php
namespace App\Service;

use App\Entity\Reservation;
use App\Entity\User;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Prépare l'intégration d'une salle Jitsi Meet (IFrame API) dans une page,
 * via JaaS : meet.jit.si n'autorise plus l'intégration sur un site tiers.
 */
class JitsiService
{
    private const DOMAINE = '8x8.vc';
    private const DUREE_JETON_SECONDES = 7200;
    // Tolère une horloge serveur en avance sur celle de JaaS
    private const MARGE_HORLOGE_SECONDES = 300;

    public function __construct(
        #[Autowire(env: 'JAAS_APP_ID')]
        private string $appId,
        #[Autowire(env: 'JAAS_KEY_ID')]
        private string $keyId,
        #[Autowire(env: 'resolve:JAAS_PRIVATE_KEY_PATH')]
        private string $cheminClePrivee
    ) {
    }

    /**
     * Indique si les identifiants JaaS sont renseignés.
     */
    public function estConfigure(): bool
    {
        return $this->appId !== ''
            && $this->keyId !== ''
            && is_readable($this->cheminClePrivee);
    }

    /**
     * Seuls l'étudiant et le formateur de la réservation
     * peuvent rejoindre une séance confirmée.
     */
    public function peutRejoindre(Reservation $reservation, User $user): bool
    {
        return $reservation->estConfirmee()
            && $reservation->getLienVisio()
            && ($this->estEtudiant($reservation, $user) || $this->estFormateur($reservation, $user));
    }

    /**
     * Retourne les paramètres attendus par JitsiMeetExternalAPI.
     *
     * @return array{domaine: string, salle: string, scriptUrl: string, jeton: string}
     */
    public function preparerSalle(Reservation $reservation, User $user): array
    {
        // Le nom de la salle est la dernière partie du lien enregistré
        $salle = basename((string) $reservation->getLienVisio());

        return [
            'domaine' => self::DOMAINE,
            'salle' => $this->appId . '/' . $salle,
            'scriptUrl' => 'https://' . self::DOMAINE . '/' . $this->appId . '/external_api.js',
            'jeton' => $this->genererJeton(
                $user,
                $salle,
                $this->estFormateur($reservation, $user)
            ),
        ];
    }

    private function genererJeton(User $user, string $salle, bool $moderateur): string
    {
        $maintenant = time();

        $charge = [
            'aud' => 'jitsi',
            'iss' => 'chat',
            'sub' => $this->appId,
            'room' => $salle,
            'nbf' => $maintenant - self::MARGE_HORLOGE_SECONDES,
            'exp' => $maintenant + self::DUREE_JETON_SECONDES,
            'context' => [
                'user' => [
                    'id' => (string) $user->getId(),
                    'name' => trim($user->getPrenomUtilisateur() . ' ' . $user->getNomUtilisateur()),
                    'email' => $user->getEmail(),
                    'moderator' => $moderateur ? 'true' : 'false',
                ],
                'features' => [
                    'livestreaming' => false,
                    'outbound-call' => false,
                    'transcription' => false,
                    'recording' => false,
                ],
                'room' => [
                    'regex' => false,
                ],
            ],
        ];

        try {
            return JWT::encode($charge, $this->lireClePrivee(), 'RS256', $this->keyId);
        } catch (\DomainException) {
            throw new \RuntimeException('La visioconférence est momentanément indisponible.');
        }
    }

    private function lireClePrivee(): string
    {
        $clePrivee = @file_get_contents($this->cheminClePrivee);

        if ($clePrivee === false) {
            throw new \RuntimeException('La visioconférence est momentanément indisponible.');
        }

        return $clePrivee;
    }

    private function estEtudiant(Reservation $reservation, User $user): bool
    {
        return $user->getStudent() !== null
            && $reservation->getStudent() === $user->getStudent();
    }

    private function estFormateur(Reservation $reservation, User $user): bool
    {
        return $user->getFormateur() !== null
            && $reservation->getFormateur() === $user->getFormateur();
    }
}
