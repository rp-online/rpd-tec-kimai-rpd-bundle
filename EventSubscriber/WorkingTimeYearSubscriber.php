<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Event\WorkingTimeYearEvent;
use App\WorkingTime\Model\DayAddon;
use DateInterval;
use DatePeriod;
use KimaiPlugin\RPDBundle\Repository\VacationRepository;
use KimaiPlugin\RPDBundle\Vacation\PublicHoliday;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class WorkingTimeYearSubscriber
{
    public function __construct(
        private readonly VacationRepository $vacationRepository,
        private readonly PublicHoliday      $publicHoliday
    )
    {
    }

    public function __invoke(WorkingTimeYearEvent $event): void
    {
        $vacations = $this->vacationRepository->findActualByUser($event->getYear()->getUser(), $event->getYear()->getYear()->format('Y'));
        $allVacations = [];
        foreach ($vacations as $vacation) {
            $end = clone $vacation->getEnd();
            $end->modify('+1 day');

            $interval = new DateInterval('P1D'); // 1 Tag
            $period = new DatePeriod($vacation->getStart(), $interval, $end);

            /** @var \DateTime $date */
            foreach ($period as $date) {
                $allVacations[$date->getTimestamp()][] = $date;
            }
        }
        // Handle the WorkingTimeYearEvent here
        // This is where you would implement your logic for the event
        foreach ($event->getYear()->getMonths() as $month) {
            foreach ($month->getDays() as $day) {
                if ($event->getYear()->getUser()->getWorkStartingDay() > $day->getDay()) {
                    continue;
                }
                $workHours = $event->getYear()->getUser()->getWorkHoursForDay($day->getDay());
                if ($this->publicHoliday->isPublicHoliday($day->getDay())) {
                    $day->addAddon(new DayAddon($this->publicHoliday->getPublicHolidayLabel($day->getDay()), $workHours, $workHours));
                    continue;
                }
                if (!empty($allVacations[$day->getDay()->getTimestamp()])) {
                    if ($workHours <= 0) {
                        continue;
                    }
                    $day->addAddon(new DayAddon('Urlaub', $workHours, $workHours));
                }
            }
        }
    }

}