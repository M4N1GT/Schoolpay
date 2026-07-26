<?php

namespace App\Form;

use App\Entity\FeeAssignment;
use App\Entity\FeeType;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FeeAssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('feeType', EntityType::class, ['class' => FeeType::class, 'label' => 'Type de frais'])
            ->add('schoolYear', EntityType::class, ['class' => SchoolYear::class, 'label' => 'Annee scolaire'])
            ->add('schoolClass', EntityType::class, ['class' => SchoolClass::class, 'label' => 'Classe', 'required' => false])
            ->add('student', EntityType::class, ['class' => Student::class, 'label' => 'Eleve individuel', 'required' => false])
            ->add('amount', MoneyType::class, ['label' => 'Montant', 'currency' => false])
            ->add('dueDate', DateType::class, ['label' => 'Echeance', 'widget' => 'single_text'])
            ->add('startDate', DateType::class, ['label' => 'Debut', 'widget' => 'single_text', 'required' => false])
            ->add('endDate', DateType::class, ['label' => 'Fin', 'widget' => 'single_text', 'required' => false])
            ->add('monthNumber', IntegerType::class, ['label' => 'Mois', 'required' => false])
            ->add('isMandatory', CheckboxType::class, ['label' => 'Obligatoire', 'required' => false])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FeeAssignment::class]);
    }
}
