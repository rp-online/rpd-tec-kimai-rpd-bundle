<?php

namespace KimaiPlugin\RPDBundle\Form;

use App\Entity\User;
use App\Form\Type\DatePickerType;
use App\Form\Type\DurationType;
use IntlDateFormatter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class UserContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dayOptions = [
            'translation_domain' => 'system-configuration',
            'constraints' => [
                new GreaterThanOrEqual(0)
            ],
        ];

        $builder
            ->add('workHoursMonday', DurationType::class, array_merge(['label' => 'Monday'], $dayOptions))
            ->add('workHoursTuesday', DurationType::class, array_merge(['label' => 'Tuesday'], $dayOptions))
            ->add('workHoursWednesday', DurationType::class, array_merge(['label' => 'Wednesday'], $dayOptions))
            ->add('workHoursThursday', DurationType::class, array_merge(['label' => 'Thursday'], $dayOptions))
            ->add('workHoursFriday', DurationType::class, array_merge(['label' => 'Friday'], $dayOptions))
            ->add('workHoursSaturday', DurationType::class, array_merge(['label' => 'Saturday'], $dayOptions))
            ->add('workHoursSunday', DurationType::class, array_merge(['label' => 'Sunday'], $dayOptions))
            ->add('workStartingDay', DatePickerType::class, ['label' => 'Beschäftigt seit'])
            ->add('holidaysPerYear', IntegerType::class, [
                'label' => 'Urlaubstage pro Jahr',
                'constraints' => [
                    new GreaterThanOrEqual(0)
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'edit_user_contract',
        ]);
    }
}