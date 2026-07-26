<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('firstName', TextType::class, ['label' => 'Prenom'])
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('phone', TextType::class, ['label' => 'Telephone', 'required' => false])
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Comptable' => 'ROLE_COMPTABLE',
                    'Directeur' => 'ROLE_DIRECTEUR',
                    'Parent' => 'ROLE_PARENT',
                ],
            ])
            ->add('plainPassword', PasswordType::class, ['label' => 'Mot de passe', 'mapped' => false, 'required' => $options['password_required']])
            ->add('isActive', CheckboxType::class, ['label' => 'Actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'password_required' => false]);
    }
}
