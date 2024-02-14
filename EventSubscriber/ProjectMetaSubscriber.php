<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Entity\ProjectMeta;
use App\Event\ProjectMetaDefinitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ProjectMetaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProjectMetaDefinitionEvent::class => ['loadMeta', 200]
        ];
    }

    public function loadMeta(ProjectMetaDefinitionEvent $event)
    {
        $definition = (new ProjectMeta())
            ->setName('has_tickets')
            ->setLabel('Enthält Tickets')
            ->setType(CheckboxType::class)
            ->setOptions([
                'label_attr' => ['class' => 'checkbox-switch'],
                'help' => 'Wenn aktiv, dann wird bei der Zeitbuchung ein Ticket erfragt, wo dann bei aktiven Jira-Account die Zeit hingebucht wird.'
            ])
            ->setIsVisible(true);
        $event->getEntity()->setMetaField($definition);
    }
}