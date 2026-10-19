<?php

namespace App\Form;

use App\Entity\Team;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'Nom de l’équipe', 'empty_data' => '', 'attr' => ['maxlength' => 100]])
            ->add('category', TextType::class, ['label' => 'Catégorie', 'required' => false, 'help' => 'Par exemple : Seniors masculins, U18…', 'attr' => ['maxlength' => 60]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => Team::class]); }
}
