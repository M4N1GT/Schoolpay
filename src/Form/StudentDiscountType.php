<?php

namespace App\Form;

use App\Entity\Discount;
use App\Entity\FeeAssignment;
use App\Entity\SchoolYear;
use App\Entity\Student;
use App\Entity\StudentDiscount;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Attribution d'une reduction a un eleve (cahier des charges, section 15).
 *
 * L'approbateur n'est pas un champ du formulaire : il est deduit de
 * l'utilisateur connecte, une approbation ne devant pas pouvoir etre attribuee
 * a quelqu'un d'autre depuis l'interface.
 */
class StudentDiscountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'label' => 'Eleve',
                'choice_label' => fn (Student $student): string => $student->getRegistrationNumber() . ' - ' . $student->getFullName(),
                'query_builder' => static fn (EntityRepository $repository) => $repository
                    ->createQueryBuilder('s')
                    ->orderBy('s.lastName', 'ASC'),
            ])
            ->add('discount', EntityType::class, [
                'class' => Discount::class,
                'label' => 'Reduction',
                // Une reduction desactivee ne doit plus pouvoir etre accordee.
                'query_builder' => static fn (EntityRepository $repository) => $repository
                    ->createQueryBuilder('d')
                    ->andWhere('d.isActive = true')
                    ->orderBy('d.name', 'ASC'),
            ])
            ->add('schoolYear', EntityType::class, [
                'class' => SchoolYear::class,
                'label' => 'Annee scolaire',
                'help' => 'La reduction ne s applique qu aux frais de cette annee.',
            ])
            ->add('feeAssignment', EntityType::class, [
                'class' => FeeAssignment::class,
                'label' => 'Frais cible',
                'required' => false,
                'placeholder' => 'Tous les frais de l annee',
                'help' => 'Laisser vide pour appliquer la reduction a l ensemble des frais.',
            ])
            ->add('justification', TextareaType::class, [
                'label' => 'Justification',
                'required' => false,
                'help' => 'Bourse, fratrie, enfant d employe, exoneration...',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => StudentDiscount::class]);
    }
}
