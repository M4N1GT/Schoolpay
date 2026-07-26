<?php

namespace App\Form;

use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolClassType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Classe'])
            ->add('level', TextType::class, ['label' => 'Niveau'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('capacity', IntegerType::class, ['label' => 'Capacite', 'required' => false])
            ->add('schoolYear', EntityType::class, ['class' => SchoolYear::class, 'label' => 'Annee scolaire'])
            ->add('isActive', CheckboxType::class, ['label' => 'Active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SchoolClass::class]);
    }
}
