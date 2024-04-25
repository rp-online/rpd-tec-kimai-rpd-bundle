<?php

namespace KimaiPlugin\RPDBundle\Reporting\SprintReport;

use App\Form\Toolbar\ToolbarFormTrait;
use App\Form\Type\DatePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SprintReportForm extends AbstractType
{
    use ToolbarFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addDateRange($builder, []);
        $this->addCustomerMultiChoice($builder, ['start_date_param' => null, 'end_date_param' => null, 'ignore_date' => true], true);
        $this->addProjectMultiChoice($builder, ['ignore_date' => true], true, true);
        $this->addActivitySelect($builder, [], true, true, false);
        $this->addUsersChoice($builder);
        $builder->add('hasTicket', ChoiceType::class, [
            'label' => 'Hat Tickets',
            'choices' => [
                'Ja' => true,
                'Nein' => false
            ]
        ]);
        $builder->add('plan', TextType::class, [
            'label' => 'Planung in %'
        ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SprintReportQuery::class,
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
    }
}