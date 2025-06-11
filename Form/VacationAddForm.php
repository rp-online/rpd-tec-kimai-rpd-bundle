<?php

namespace KimaiPlugin\RPDBundle\Form;

use App\Entity\User;
use App\Form\Type\DatePickerType;
use App\Form\Type\UserType;
use KimaiPlugin\RPDBundle\Entity\Vacation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VacationAddForm extends AbstractType
{
    #[\Override] public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('user', UserType::class, [
            'choice_label' => 'displayName',
            'label' => 'Benutzer',
            'required' => true,
            'placeholder' => 'Bitte wählen',
            'attr' => ['class' => 'form-select']
        ])->add('start', DatePickerType::class, [
            'label' => 'Startdatum',
            'required' => true,
        ])->add('end', DatePickerType::class, [
            'label' => 'Enddatum',
            'required' => true,
        ])
            ->add('approved', CheckboxType::class, [
                'label' => 'Genehmigt',
                'label_attr' => [
                    'class' => 'checkbox-switch',
                ],
                'required' => false
            ])
            ->add('submit', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, [
            'label' => 'Urlaub hinzufügen',
            'attr' => ['class' => 'btn btn-primary']
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vacation::class,
            'timezone' => date_default_timezone_get(),
            'start' => new \DateTime(),
            'end' => new \DateTime(),
            'csrf_protection' => false,
            'method' => 'POST',
            'allow_extra_fields' => true
        ]);
    }
}