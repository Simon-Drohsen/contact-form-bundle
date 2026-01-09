<?php

/**
 * instride AG.
 *
 * LICENSE
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that is distributed with this source code.
 *
 * @copyright 2026 instride AG (https://instride.ch)
 */

namespace Instride\Bundle\ContactFormBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class ContactFormType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label_format' => 'instride.contact-form.%name%.label',
                'required' => true,
                'attr' => [
                    'placeholder' => 'instride.contact-form.firstname.placeholder',
                ]
            ])
            ->add('lastname', TextType::class, [
                'label_format' => 'instride.contact-form.%name%.label',
                'required' => true,
                'attr' => [
                    'placeholder' => 'instride.contact-form.lastname.placeholder',
                ],
            ])
            ->add('email', EmailType::class, [
                'label_format' => 'instride.contact-form.%name%.label',
                'required' => true,
                'attr' => [
                    'placeholder' => 'instride.contact-form.email.placeholder',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label_format' => 'instride.contact-form.%name%.label',
                'required' => true,
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'instride.contact-form.message.placeholder',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label_format' => 'instride.contact-form.%name%',
                'attr' => [
                    'class' => 'uk-button uk-button-primary',
                ],
            ]);
    }
}
