<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;


use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            SystemConfigurationEvent::class => ['loadConfiguration']
        ];
    }

    public function loadConfiguration(SystemConfigurationEvent $event)
    {
        $configuration = new Configuration('jira.url');
        $configuration->setLabel('Jira Url')->setType(TextType::class);
        $systemConfiguration = new SystemConfiguration('Jira');
        $systemConfiguration->setConfiguration([$configuration]);
        $event->addConfiguration($systemConfiguration);

        $vacationProblemThresholdConfiguration = new Configuration('vacation.problem_threshold');
        $vacationProblemThresholdConfiguration->setLabel('Schwellenwert für parallele Urlaube')->setType(NumberType::class)->setOptions(['help' => 'Definiert die maximale Anzahl an sich überschneidenden Urlauben, bevor dies als potenzielles Problem betrachtet wird.']);
        $vacationSystemConfiguration = new SystemConfiguration('Urlaub');
        $vacationSystemConfiguration->setConfiguration([$vacationProblemThresholdConfiguration]);
        $event->addConfiguration($vacationSystemConfiguration);
    }

}