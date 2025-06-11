<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Event\WorkingTimeYearEvent;
use App\WorkingTime\Model\DayAddon;
use DateInterval;
use DatePeriod;
use KimaiPlugin\RPDBundle\Repository\VacationRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsEventListener]
class WorkingTimeYearSubscriber
{
    private static array $publicHolidays = [];

    public function __construct(private readonly VacationRepository $vacationRepository, private readonly KernelInterface $kernel)
    {
    }

    protected function loadPublicHoliday(\DateTimeInterface $date): void
    {
        if(!empty(self::$publicHolidays[$date->format('Y')])) {
            return;
        }
        $file = $this->kernel->getCacheDir() . '/public_holidays.json';
        if(!file_exists($file)) {
            $client = HttpClient::create();
            $response = $client->request('GET', 'https://feiertage-api.de/api/?jahr=' . $date->format('Y') . '&nur_land=NW');
            $content = $response->getContent();
            file_put_contents($file, $content);
        } else {
            $content = file_get_contents($file);
        }
        if($content !== false) {
            $holidays = @json_decode($content, true);

            if(is_array($holidays)) {
                foreach($holidays as $name => $holiday) {
                    if(!empty($holiday['datum'])) {
                        self::$publicHolidays[$date->format('Y')][$holiday['datum']] = $name;
                    }
                }
            }
        }
    }

    public function __invoke(WorkingTimeYearEvent $event): void
    {
        $vacations = $this->vacationRepository->findActualByUser($event->getYear()->getUser(), $event->getYear()->getYear()->format('Y'));
        $allVacations = [];
        foreach($vacations as $vacation) {
            $end = clone $vacation->getEnd();
            $end->modify('+1 day');

            $interval = new DateInterval('P1D'); // 1 Tag
            $period = new DatePeriod($vacation->getStart(), $interval, $end);

            /** @var \DateTime $date */
            foreach ($period as $date) {
                $allVacations[$date->getTimestamp()][] = $date;
            }
        }
        $this->loadPublicHoliday($event->getYear()->getYear());
        // Handle the WorkingTimeYearEvent here
        // This is where you would implement your logic for the event
        foreach($event->getYear()->getMonths() as $month) {
            foreach($month->getDays() as $day) {
                if($event->getYear()->getUser()->getWorkStartingDay() > $day->getDay()) {
                    continue;
                }
                $workHours = $event->getYear()->getUser()->getWorkHoursForDay($day->getDay());
                if(!empty(self::$publicHolidays[$day->getDay()->format('Y')][$day->getDay()->format('Y-m-d')])) {
                    $day->addAddon(new DayAddon(self::$publicHolidays[$day->getDay()->format('Y')][$day->getDay()->format('Y-m-d')], $workHours, $workHours));
                    continue;
                }
                if(!empty($allVacations[$day->getDay()->getTimestamp()])) {
                    if($workHours <= 0) {
                        continue;
                    }
                    $day->addAddon(new DayAddon('Urlaub', $workHours, $workHours));
                }
            }
        }
    }

}