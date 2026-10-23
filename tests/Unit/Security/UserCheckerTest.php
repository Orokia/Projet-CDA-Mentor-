<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserCheckerTest extends TestCase
{
    public function testRefuseUnCompteNonVerifie(): void
    {
        $user = new User();
        $user->setIsVerified(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new UserChecker())->checkPreAuth($user);
    }

    public function testAccepteUnCompteVerifie(): void
    {
        $user = new User();
        $user->setIsVerified(true);

        (new UserChecker())->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }
}
