<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ImportPeriodType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['homePoints'=>'Locaux','awayPoints'=>'Visiteurs'] as $field=>$label) {
            $builder->add($field,IntegerType::class,['label'=>$label,'required'=>false,'constraints'=>[new Assert\Range(min:0,max:1000)],'attr'=>['min'=>0,'max'=>1000]]);
        }
    }
}
