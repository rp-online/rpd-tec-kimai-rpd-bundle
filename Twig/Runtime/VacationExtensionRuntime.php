<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\RPDBundle\Twig\Runtime;

use DateInterval;
use DatePeriod;
use KimaiPlugin\RPDBundle\Entity\Vacation;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Extension\RuntimeExtensionInterface;

class VacationExtensionRuntime implements RuntimeExtensionInterface
{
    private static array $publicHolidays = [];

    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function isPublicHoliday(\DateTime $date): bool
    {
        $this->loadPublicHoliday($date);

        return !empty(self::$publicHolidays[$date->format('Y')][$date->format('Y-m-d')]);
    }

    public function getPublicHolidayLabel(\DateTime $date): string
    {
        $this->loadPublicHoliday($date);

        return self::$publicHolidays[$date->format('Y')][$date->format('Y-m-d')];
    }

    protected function loadPublicHoliday(\DateTime $date): void
    {
        if(!empty(self::$publicHolidays[$date->format('Y')])) {
            return;
        }
        $file = $this->kernel->getCacheDir() . '/public_holidays_' . $date->format('Y') . '.json';
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

    public function getVacationDuration(Vacation $vacation)
    {
        $end = clone $vacation->getEnd();
        $end->modify('+1 day');

        $interval = new DateInterval('P1D'); // 1 Tag
        $period = new DatePeriod($vacation->getStart(), $interval, $end);

        $result = 0;
        /** @var \DateTime $date */
        foreach ($period as $date) {
            $this->loadPublicHoliday($date);
            if($vacation->getUser()->getWorkHoursForDay($date) <= 0 || !empty(self::$publicHolidays[$date->format('Y')][$date->format('Y-m-d')])) {
                continue;
            }
            $result++;
        }

        return $result === 1 ? '1 Tag' : $result . ' Tage';
    }
}
