<?php

namespace App\Form;

use App\Entity\PlayerMatchStat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PlayerMatchStatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('participation',ChoiceType::class,['label'=>'Participation','choices'=>['Déduire des données disponibles'=>'unknown','A joué'=>'played','N’a pas joué'=>'did_not_play'],'help'=>'Sans temps de jeu ni preuve d’entrée en jeu, la participation reste inconnue.']);
        foreach (['points' => ['Points', 1000], 'fouls' => ['Fautes', 5], 'twoPtMade' => ['2 pts réussis', 500], 'threePtMade' => ['3 pts réussis', 333], 'freeThrowsMade' => ['LF réussis', 1000]] as $field => [$label, $max]) {
            $builder->add($field, IntegerType::class, ['label' => $label, 'required' => false, 'attr' => ['min' => 0, 'max' => $max]]);
        }
        $builder->add('secondsPlayed', TextType::class, [
            'label' => 'Temps joué (min:ss)', 'required' => false,
            'invalid_message' => 'Utilisez le format min:ss, par exemple 23:45.',
            'attr' => ['placeholder' => '23:45', 'maxlength' => 8],
        ]);
        $builder->get('secondsPlayed')->addModelTransformer(new CallbackTransformer(
            static fn (?int $value): string => $value === null ? '' : sprintf('%d:%02d', intdiv($value, 60), $value % 60),
            static function (?string $value): ?int {
                if ($value === null || $value === '') { return null; }
                if (!preg_match('/\A(\d{1,4}):([0-5]\d)\z/D', $value, $parts)) { throw new TransformationFailedException('Invalid duration.'); }
                return (int) $parts[1] * 60 + (int) $parts[2];
            },
        ));
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => PlayerMatchStat::class]); }
}
