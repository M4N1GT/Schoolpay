<?php

namespace App\Form;

use App\Entity\FeeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FeeTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('code', TextType::class, ['label' => 'Code'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('isMandatory', CheckboxType::class, ['label' => 'Obligatoire', 'required' => false])
            ->add('isRecurring', CheckboxType::class, ['label' => 'Recurrent', 'required' => false])
            ->add('recurrenceType', TextType::class, ['label' => 'Periodicite', 'required' => false])
            ->add('isActive', CheckboxType::class, ['label' => 'Actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FeeType::class]);
    }
}
