<?php

namespace KimaiPlugin\RPDBundle\Form;

use App\Form\Type\DatePickerType;
use KimaiPlugin\RPDBundle\Entity\Vacation;
use KimaiPlugin\RPDBundle\Vacation\VacationYear;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VacationRequestForm extends AbstractType
{
    #[\Override] public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('start', DatePickerType::class, [
                'label' => 'Startdatum',
                'required' => true,
            ])
            ->add('end', DatePickerType::class, [
                'label' => 'Enddatum',
                'required' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Urlaubsantrag absenden',
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