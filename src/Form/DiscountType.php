<?php

namespace App\Form;

use App\Entity\Discount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DiscountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('type', ChoiceType::class, ['label' => 'Type', 'choices' => ['Montant fixe' => Discount::TYPE_FIXED, 'Pourcentage' => Discount::TYPE_PERCENT]])
            ->add('value', MoneyType::class, ['label' => 'Valeur', 'currency' => false])
            ->add('reason', TextareaType::class, ['label' => 'Motif', 'required' => false])
            ->add('startDate', DateType::class, ['label' => 'Debut', 'widget' => 'single_text', 'required' => false])
            ->add('endDate', DateType::class, ['label' => 'Fin', 'widget' => 'single_text', 'required' => false])
            ->add('isActive', CheckboxType::class, ['label' => 'Active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Discount::class]);
    }
}
