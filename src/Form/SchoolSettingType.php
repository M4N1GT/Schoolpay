<?php

namespace App\Form;

use App\Entity\SchoolSetting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SchoolSettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('schoolName', TextType::class, ['label' => 'Nom de l ecole'])
            ->add('schoolCode', TextType::class, ['label' => 'Code'])
            ->add('address', TextareaType::class, ['label' => 'Adresse', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Telephone', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false])
            ->add('currency', TextType::class, ['label' => 'Devise'])
            ->add('currencySymbol', TextType::class, ['label' => 'Symbole'])
            ->add('receiptFooter', TextareaType::class, ['label' => 'Pied de recu', 'required' => false])
            ->add('defaultPaymentDeadline', IntegerType::class, ['label' => 'Delai de paiement'])
            ->add('lateFeeEnabled', CheckboxType::class, ['label' => 'Frais de retard actifs', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SchoolSetting::class]);
    }
}
