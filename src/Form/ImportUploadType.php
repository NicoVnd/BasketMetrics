<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{ChoiceType, FileType};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ImportUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('archive',FileType::class,['label'=>'Archive e-Marque (.zip)','constraints'=>[new Assert\NotNull(),new Assert\File(maxSize:'20M',extensions:['zip'],mimeTypes:['application/zip','application/x-zip-compressed','application/octet-stream'])],'attr'=>['accept'=>'.zip']])
            ->add('side',ChoiceType::class,['label'=>'Mon équipe dans ce document','choices'=>['Équipe A · locaux (domicile)'=>'home','Équipe B · visiteurs (extérieur)'=>'away']]);
    }
}
