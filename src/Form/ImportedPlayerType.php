<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, ChoiceType, IntegerType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ImportedPlayerType extends AbstractType
{
    public function getParent(): string { return PlayerMatchStatType::class; }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('include',CheckboxType::class,['label'=>'Importer ce joueur','required'=>false])
            ->add('existingPlayer',ChoiceType::class,['label'=>'Rapprochement avec l’effectif','choices'=>['Choisir le joueur…'=>'__choose__','Créer un nouveau joueur'=>'__new__']+$options['player_choices']])
            ->add('firstName',TextType::class,['label'=>'Prénom','empty_data'=>'','constraints'=>[new Assert\NotBlank(),new Assert\Length(max:80)]])
            ->add('lastName',TextType::class,['label'=>'Nom','empty_data'=>'','constraints'=>[new Assert\NotBlank(),new Assert\Length(max:80)]])
            ->add('licenseNumber',TextType::class,['label'=>'Licence FFBB','required'=>false,'constraints'=>[new Assert\Length(max:24),new Assert\Regex('/^[A-Za-z0-9\s-]+$/')]])
            ->add('jerseyNumber',IntegerType::class,['label'=>'N° dans ce match','required'=>false,'constraints'=>[new Assert\Range(min:0,max:99)]])
            ->add('starter',ChoiceType::class,['label'=>'Cinq de départ','required'=>false,'placeholder'=>'Inconnu','choices'=>['Oui'=>true,'Non'=>false]])
            ->add('captain',ChoiceType::class,['label'=>'Capitaine','required'=>false,'placeholder'=>'Inconnu','choices'=>['Oui'=>true,'Non'=>false]]);
        // The parent entity constraints don't apply to this review array.
        foreach (['points'=>1000,'fouls'=>5,'twoPtMade'=>500,'threePtMade'=>333,'freeThrowsMade'=>1000] as $field=>$max) {
            $label=$builder->get($field)->getOption('label');
            $builder->add($field,IntegerType::class,['label'=>$label,'required'=>false,'constraints'=>[new Assert\Range(min:0,max:$max)],'attr'=>['min'=>0,'max'=>$max]]);
        }
        foreach (['fieldGoalsMade'=>'Paniers réussis','twoPtInsideMade'=>'2 pts intérieurs réussis','twoPtOutsideMade'=>'2 pts extérieurs réussis'] as $field=>$label) {
            $builder->add($field,IntegerType::class,['label'=>$label,'required'=>false,'constraints'=>[new Assert\Range(min:0,max:1000)],'attr'=>['min'=>0,'max'=>1000]]);
        }
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class'=>null,'player_choices'=>[]]); }
}
