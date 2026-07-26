<?php

namespace App\Form;

use App\Entity\ParentGuardian;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParentGuardianType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prenom'])
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Telephone'])
            ->add('alternatePhone', TextType::class, ['label' => 'Telephone secondaire', 'required' => false])
            ->add('address', TextareaType::class, ['label' => 'Adresse', 'required' => false])
            ->add('profession', TextType::class, ['label' => 'Profession', 'required' => false])
            ->add('relationshipType', TextType::class, ['label' => 'Lien avec l eleve'])
            ->add('user', EntityType::class, ['class' => User::class, 'choice_label' => 'email', 'label' => 'Compte utilisateur', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ParentGuardian::class]);
    }
}
