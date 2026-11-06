<?php

namespace App\Tests\Unit\Paiement;

use App\Paiement\Montant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MontantTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function montants(): iterable
    {
        yield 'colonne DECIMAL Doctrine' => ['199.99', 19999];
        yield 'sans centimes' => ['250', 25000];
        yield 'un seul chiffre après la virgule' => ['12.5', 1250];
        yield 'virgule française' => ['12,05', 1205];
        yield 'zéro' => ['0.00', 0];
        // 0.1 + 0.2 en flottant donnerait 30.000000000000004
        yield 'sans erreur d\'arrondi' => ['0.29', 29];
        yield 'espaces autour' => [' 19.90 ', 1990];
    }

    #[DataProvider('montants')]
    public function testEnCentimes(string $montant, int $centimes): void
    {
        self::assertSame($centimes, Montant::enCentimes($montant));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function montantsInvalides(): iterable
    {
        yield 'vide' => [''];
        yield 'négatif' => ['-10.00'];
        yield 'trois décimales' => ['10.999'];
        yield 'texte' => ['dix euros'];
        yield 'notation scientifique' => ['1e3'];
    }

    #[DataProvider('montantsInvalides')]
    public function testEnCentimesRefuseUnMontantInvalide(string $montant): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Montant::enCentimes($montant);
    }

    public function testDepuisCentimes(): void
    {
        self::assertSame('199.99', Montant::depuisCentimes(19999));
        self::assertSame('0.05', Montant::depuisCentimes(5));
        self::assertSame('250.00', Montant::depuisCentimes(25000));
    }

    public function testDepuisCentimesRefuseUnMontantNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Montant::depuisCentimes(-1);
    }
}
