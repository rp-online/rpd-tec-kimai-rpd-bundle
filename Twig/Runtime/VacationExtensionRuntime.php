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
use KimaiPlugin\RPDBundle\Vacation\PublicHoliday;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Extension\RuntimeExtensionInterface;

class VacationExtensionRuntime implements RuntimeExtensionInterface
{

    public function __construct(private readonly PublicHoliday $publicHoliday)
    {
    }

    public function isPublicHoliday(\DateTime $date): bool
    {
        return $this->publicHoliday->isPublicHoliday($date);
    }

    public function getPublicHolidayLabel(\DateTime $date): string
    {
        return $this->publicHoliday->getPublicHolidayLabel($date);
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
            if($vacation->getUser()->getWorkHoursForDay($date) <= 0 || $this->publicHoliday->isPublicHoliday($date)) {
                continue;
            }
            $result++;
        }

        return $result === 1 ? '1 Tag' : $result . ' Tage';
    }

    public function getHumanTime(int $seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $result = [];
        if ($hours > 0) {
            $result[] = sprintf('%d Stunde%s', $hours, $hours > 1 ? 'n' : '');
        }
        if ($minutes > 0) {
            $result[] = sprintf('%d Minute%s', $minutes, $minutes > 1 ? 'n' : '');
        }
        if ($seconds > 0) {
            $result[] = sprintf('%d Sekunde%s', $seconds, $seconds > 1 ? 'n' : '');
        }

        return implode(', ', $result);
    }
}
