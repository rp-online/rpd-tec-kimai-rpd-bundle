<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Event\ReportingEvent;
use App\Reporting\Report;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ReportSubscriber implements EventSubscriberInterface
{

    public function __construct(private AuthorizationCheckerInterface $security)
    {
    }

    #[\Override] public static function getSubscribedEvents()
    {
        return [ReportingEvent::class => ['addReport', 200]];
    }

    public function addReport(ReportingEvent $event)
    {
        if ($this->security->isGranted('report:other')) {
            $event->addReport(new Report('sprint', 'report_sprint', 'Sprintauswertung', 'project'));
            $event->addReport(new Report('sprint_user', 'report_sprint_user', 'Sprintauswertung für einen Benutzer', 'project'));
        }
    }
}