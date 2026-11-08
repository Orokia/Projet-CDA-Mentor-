<?php

namespace App\Controller\Admin\Field;

use App\Form\JoursSemaineType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

/**
 * Champ EasyAdmin pour Disponibilite::$joursSemaine (jour => statut).
 *
 * Un Field générique ne convient pas : EasyAdmin le remplace par un
 * ArrayField pour une colonne JSON, qui ajoute des options de collection
 * inconnues de JoursSemaineType.
 */
final class JoursSemaineField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/generic')
            ->setFormType(JoursSemaineType::class)
            ->formatValue(static fn ($value): string => JoursSemaineType::formater($value));
    }
}
