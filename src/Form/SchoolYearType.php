<?php

namespace App\Form;

use App\Entity\SchoolYear;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolYearType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Annee scolaire'])
            ->add('startDate', DateType::class, ['label' => 'Debut', 'widget' => 'single_text'])
            ->add('endDate', DateType::class, ['label' => 'Fin', 'widget' => 'single_text'])
            ->add('isActive', CheckboxType::class, ['label' => 'Active', 'required' => false])
            ->add('isClosed', CheckboxType::class, ['label' => 'Cloturee', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SchoolYear::class]);
    }
}
