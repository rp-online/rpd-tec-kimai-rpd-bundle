<?php

namespace KimaiPlugin\RPDBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class VacationApproveForm extends AbstractType
{
    #[\Override] public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('vacationId', HiddenType::class, [
            'label' => false,
            'required' => true,
        ])->add('reason', HiddenType::class, [
            'label' => false,
            'required' => false,
        ])->add('approve', SubmitType::class, [
            'label' => 'Urlaub genehmigen',
            'attr' => ['class' => 'btn btn-sm btn-success px-2']
        ])->add('decline', SubmitType::class, [
            'label' => 'Urlaub ablehnen',
            'attr' => ['class' => 'btn btn-sm btn-danger px-2']
        ]);
    }

}