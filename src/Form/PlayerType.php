<?php

namespace App\Form;

use App\Entity\Player;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PlayerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('firstName', TextType::class, ['label' => 'Prénom', 'empty_data' => '', 'attr' => ['maxlength' => 80]])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'empty_data' => '', 'attr' => ['maxlength' => 80]])
            ->add('jerseyNumber', IntegerType::class, ['label' => 'Numéro de maillot', 'required' => false, 'attr' => ['min' => 0, 'max' => 99]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => Player::class]); }
}
