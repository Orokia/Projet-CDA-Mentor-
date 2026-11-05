<?php

namespace App\Paiement;

/**
 * Conversion entre montant décimal (colonne DECIMAL Doctrine, « 199.99 »)
 * et centimes (API Stripe), sans passer par les flottants.
 */
final class Montant
{
    public static function enCentimes(string $montant): int
    {
        $montant = trim($montant);

        if (!preg_match('/^(\d{1,12})(?:[.,](\d{1,2}))?$/', $montant, $parties)) {
            throw new \InvalidArgumentException(sprintf('Montant invalide : « %s ».', $montant));
        }

        return (int) $parties[1] * 100 + (int) str_pad($parties[2] ?? '0', 2, '0');
    }

    public static function depuisCentimes(int $centimes): string
    {
        if ($centimes < 0) {
            throw new \InvalidArgumentException('Un montant ne peut pas être négatif.');
        }

        return sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100);
    }
}
