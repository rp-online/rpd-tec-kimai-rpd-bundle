<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Entity\TimesheetMeta;
use App\Event\TimesheetMetaDisplayEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Form\Extension\Core\Type\TextType;

#[AsEventListener]
class TimesheetMetaEventSubscriber
{

    public function __invoke(TimesheetMetaDisplayEvent $event)
    {
        $event->addField((new TimesheetMeta())->setName('ticket')->setLabel('Ticket')->setType(TextType::class)->setIsVisible(true));
    }
}