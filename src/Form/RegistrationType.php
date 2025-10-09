<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('login', TextType::class, [
                'label' => 'Логин',
                'attr' => ['placeholder' => 'Введите логин'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Пароль не может быть пустым']),
                    new Assert\Length([
                        'min' => 6,
                        'minMessage' => 'Пароль должен содержать минимум {{ limit }} символов',
                        'max' => 4096,
                    ]),
                ],
                'first_options' => [
                    'label' => 'Пароль',
                    'attr' => ['placeholder' => 'Введите пароль'],
                ],
                'second_options' => [
                    'label' => 'Повторите пароль',
                    'attr' => ['placeholder' => 'Повторите пароль'],
                ],
                'invalid_message' => 'Пароли должны совпадать',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

