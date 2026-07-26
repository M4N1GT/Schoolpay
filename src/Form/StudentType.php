<?php

namespace App\Form;

use App\Entity\ParentGuardian;
use App\Entity\SchoolClass;
use App\Entity\SchoolYear;
use App\Entity\Student;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StudentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('registrationNumber', TextType::class, ['label' => 'Matricule'])
            ->add('firstName', TextType::class, ['label' => 'Prenom'])
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('gender', ChoiceType::class, ['label' => 'Sexe', 'choices' => ['Masculin' => 'M', 'Feminin' => 'F', 'Non precise' => 'Non precise']])
            ->add('birthDate', DateType::class, ['label' => 'Date de naissance', 'widget' => 'single_text', 'required' => false])
            ->add('birthPlace', TextType::class, ['label' => 'Lieu de naissance', 'required' => false])
            ->add('address', TextareaType::class, ['label' => 'Adresse', 'required' => false])
            ->add('status', ChoiceType::class, ['label' => 'Statut', 'choices' => ['Actif' => 'actif', 'Archive' => 'archive', 'Suspendu' => 'suspendu']])
            ->add('enrollmentDate', DateType::class, ['label' => 'Date d inscription', 'widget' => 'single_text'])
            ->add('schoolClass', EntityType::class, ['class' => SchoolClass::class, 'label' => 'Classe'])
            ->add('schoolYear', EntityType::class, ['class' => SchoolYear::class, 'label' => 'Annee scolaire'])
            ->add('parents', EntityType::class, ['class' => ParentGuardian::class, 'label' => 'Parents', 'multiple' => true, 'expanded' => false, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Student::class]);
    }
}
