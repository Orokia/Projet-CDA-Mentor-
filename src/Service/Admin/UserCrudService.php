<?php

namespace App\Service\Admin;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Hache le mot de passe uniquement s'il est renseigné.
     */
    public function hacherMotDePasse(
        User $user,
        ?string $plainPassword
    ): void {
        if ($plainPassword === null || trim($plainPassword) === '') {
            return;
        }

        $hash = $this->passwordHasher->hashPassword(
            $user,
            $plainPassword
        );

        $user->setPassword($hash);
    }
}
