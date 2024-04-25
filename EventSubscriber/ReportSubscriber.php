<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Event\ReportingEvent;
use App\Reporting\Report;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReportSubscriber implements EventSubscriberInterface
{
    #[\Override] public static function getSubscribedEvents()
    {
        return [ReportingEvent::class => ['addReport', 200]];
    }

    public function addReport(ReportingEvent $event)
    {
        $event->addReport(new Report('sprint', 'report_sprint', 'Sprintauswertung', 'project'));
    }
}