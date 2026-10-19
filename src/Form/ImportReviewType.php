<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, ChoiceType, CollectionType, TextType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ImportReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('existingMatch',ChoiceType::class,['label'=>'Destination du match','required'=>false,'placeholder'=>'Créer un nouveau match','choices'=>$options['game_choices']])
            ->add('game',MatchGameType::class,['label'=>false])
            ->add('reference',TextType::class,['label'=>'N° de rencontre FFBB','required'=>false,'constraints'=>[new Assert\Length(max:32)]])
            ->add('competition',TextType::class,['label'=>'Compétition','required'=>false,'constraints'=>[new Assert\Length(max:200)]])
            ->add('venue',TextType::class,['label'=>'Lieu','required'=>false,'constraints'=>[new Assert\Length(max:120)]])
            ->add('players',CollectionType::class,['entry_type'=>ImportedPlayerType::class,'entry_options'=>['player_choices'=>$options['player_choices'],'label'=>false],'allow_add'=>false,'allow_delete'=>false])
            ->add('periods',CollectionType::class,['entry_type'=>ImportPeriodType::class,'entry_options'=>['label'=>false],'allow_add'=>false,'allow_delete'=>false])
            ->add('acknowledge',CheckboxType::class,['label'=>'J’ai vérifié les données, les avertissements et le rapprochement des joueurs. Si un match existant est sélectionné, je confirme sa mise à jour.','constraints'=>[new Assert\IsTrue(message:'Vérifiez les données avant de confirmer.')]]);
        $builder->get('game')->remove('home');
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['player_choices'=>[],'game_choices'=>[],'csrf_token_id'=>'ffbb_import_review']); }
}
