<?php

namespace App\Form;

use App\Entity\Disponibilite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Édition de Disponibilite::$joursSemaine dans le back-office.
 *
 * Un champ par jour de la semaine. « Non proposé » retire le jour,
 * sinon le jour est enregistré avec le statut choisi :
 * ['lundi' => 'disponible', 'mardi' => 'plus disponible'].
 */
class JoursSemaineType extends AbstractType
{
    // Ordre d'affichage et libellés des jours
    public const JOURS = [
        'lundi' => 'Lundi',
        'mardi' => 'Mardi',
        'mercredi' => 'Mercredi',
        'jeudi' => 'Jeudi',
        'vendredi' => 'Vendredi',
        'samedi' => 'Samedi',
        'dimanche' => 'Dimanche',
    ];

    public const STATUTS = [
        'Disponible' => Disponibilite::STATUT_DISPONIBLE,
        'Plus disponible' => Disponibilite::STATUT_PLUS_DISPONIBLE,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::JOURS as $jour => $libelle) {
            $builder->add($jour, ChoiceType::class, [
                'label' => $libelle,
                'required' => false,
                'placeholder' => 'Non proposé',
                'choices' => self::STATUTS,
            ]);
        }

        $builder->addModelTransformer(new CallbackTransformer(
            // Entité → formulaire : un statut (ou null) pour chacun des sept jours
            static function (?array $joursSemaine): array {
                $statuts = array_fill_keys(array_keys(self::JOURS), null);

                foreach ($joursSemaine ?? [] as $jour => $statut) {
                    if (!is_string($jour) || !is_string($statut)) {
                        continue;
                    }

                    $cle = mb_strtolower(trim($jour));

                    if (array_key_exists($cle, $statuts)) {
                        $statuts[$cle] = mb_strtolower(trim($statut));
                    }
                }

                return $statuts;
            },
            // Formulaire → entité : on ne garde que les jours proposés
            static fn (?array $statuts): array => array_filter(
                $statuts ?? [],
                static fn (?string $statut): bool => null !== $statut && '' !== $statut
            )
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'empty_data' => [],
        ]);
    }

    /**
     * Texte lisible pour les pages liste et détail :
     * « Lundi (disponible), Mardi (plus disponible) ».
     */
    public static function formater(?array $joursSemaine): string
    {
        $libelles = [];

        foreach ($joursSemaine ?? [] as $jour => $statut) {
            if (!is_string($jour) || !is_string($statut)) {
                continue;
            }

            $libelles[] = sprintf('%s (%s)', mb_convert_case(trim($jour), MB_CASE_TITLE), trim($statut));
        }

        return $libelles ? implode(', ', $libelles) : 'Aucun jour';
    }
}
