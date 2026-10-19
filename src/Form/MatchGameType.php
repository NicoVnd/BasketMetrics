<?php

namespace App\Form;

use App\Entity\MatchGame;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MatchGameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('opponent', TextType::class, ['label' => 'Équipe adverse', 'empty_data' => '', 'attr' => ['maxlength' => 100]])
            ->add('playedAt', DateTimeType::class, ['label' => 'Date et heure', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'model_timezone' => 'UTC', 'view_timezone' => 'Europe/Paris'])
            ->add('home', ChoiceType::class, ['label' => 'Lieu', 'choices' => ['À domicile' => true, 'À l’extérieur' => false]])
            ->add('status', ChoiceType::class, ['label' => 'État du match', 'choices' => ['À jouer' => 'scheduled', 'Terminé' => 'finished']])
            ->add('teamScore', IntegerType::class, ['label' => 'Score de votre équipe', 'required' => false, 'attr' => ['min' => 0, 'max' => 1000]])
            ->add('opponentScore', IntegerType::class, ['label' => 'Score adverse', 'required' => false, 'attr' => ['min' => 0, 'max' => 1000]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => MatchGame::class]); }
}
