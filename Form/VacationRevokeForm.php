<?php

namespace KimaiPlugin\RPDBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class VacationRevokeForm extends AbstractType
{
    #[\Override] public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('vacationId', HiddenType::class, [
            'label' => false,
            'required' => true,
        ])
        ->add('submit', SubmitType::class, [
            'label' => 'Urlaub stornieren',
            'attr' => ['class' => 'btn btn-sm btn-danger px-2']
        ]);
    }

}