<?php

namespace KimaiPlugin\RPDBundle\Form;

use App\Form\Type\YearPickerType;
use KimaiPlugin\RPDBundle\Vacation\VacationYear;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VacationYearSelectionForm extends AbstractType
{
    #[\Override] public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('date', YearPickerType::class, [
            'model_timezone' => $options['timezone'],
            'view_timezone' => $options['timezone'],
            'start_date' => $options['start_date'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VacationYear::class,
            'timezone' => date_default_timezone_get(),
            'start_date' => new \DateTime(),
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
    }

}