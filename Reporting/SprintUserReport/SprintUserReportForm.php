<?php

namespace KimaiPlugin\RPDBundle\Reporting\SprintUserReport;

use App\Form\Toolbar\ToolbarFormTrait;
use App\Form\Type\DateRangeType;
use App\Form\Type\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SprintUserReportForm extends AbstractType
{
    use ToolbarFormTrait;

    #[\Override] public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('user', UserType::class, [
            'documentation' => [
                'type' => 'array',
                'items' => ['type' => 'integer', 'description' => 'User ID'],
                'description' => 'Array of user IDs',
            ],
            'label' => 'user',
            'multiple' => false,
            'required' => true,
        ]);
        $this->addProjectMultiChoice($builder, ['ignore_date' => true], true, true);
        $this->addNamedDateRange($builder, [], 'current_sprint', false, ['label' => 'Aktueller Sprint']);
        $builder->add('plan_factor', TextType::class, [
            'label' => 'Planung in %'
        ]);
    }

    protected function addNamedDateRange(FormBuilderInterface $builder, array $options, string $name = 'daterange', bool $allowEmpty = true, array $additionalParams = []): void
    {
        $params = [
            'required' => !$allowEmpty,
            'allow_empty' => $allowEmpty,
        ];

        if (\array_key_exists('timezone', $options)) {
            $params['timezone'] = $options['timezone'];
        }
        $params = array_merge($params, $additionalParams);

        $builder->add($name, DateRangeType::class, $params);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SprintUserQuery::class,
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
    }

}