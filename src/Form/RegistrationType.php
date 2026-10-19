<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('username', TextType::class, [
            'label' => 'Identifiant', 'attr' => ['autocomplete' => 'username', 'maxlength' => 32],
        ])->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class, 'mapped' => false,
            'invalid_message' => 'Les mots de passe ne correspondent pas.',
            'first_options' => ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 12, max: 128, minMessage: 'Utilisez au moins 12 caractères.'),
                new Assert\Length(max: 72, countUnit: Assert\Length::COUNT_BYTES, maxMessage: 'Le mot de passe doit tenir dans 72 octets (les caractères accentués peuvent en occuper plusieurs).'),
            ],
        ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'csrf_token_id' => 'registration']);
    }
}
