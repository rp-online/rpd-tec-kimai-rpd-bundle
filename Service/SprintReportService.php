<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\RPDBundle\Service;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Model\DailyStatistic;
use App\Repository\TimesheetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use KimaiPlugin\RPDBundle\Reporting\SprintReport\SprintReportQuery;

class SprintReportService
{
    protected int $totalTime = 0;
    protected int $totalBookedTime = 0;

    protected int $bookedTimeOnTickets = 0;

    protected array $userInformation = [];

    public function __construct(private readonly TimesheetRepository $timesheetRepository)
    {
    }

    public function getSprintReportData(SprintReportQuery $query): array
    {
        $timesheetQueryBuilder = $this->timesheetRepository->createQueryBuilder('t');

        $timesheetQueryBuilder
            ->andWhere(
                $timesheetQueryBuilder->expr()->gte('DATE(t.begin)', ':start'),
                $timesheetQueryBuilder->expr()->lte('DATE(t.end)', ':end'),
            )
            ->setParameter('start', $query->getDateRange()->getBegin(), Types::DATETIME_MUTABLE)
            ->setParameter('end', $query->getDateRange()->getEnd(), Types::DATETIME_MUTABLE)
        ;
        $this->addCustomer($query, $timesheetQueryBuilder);
        $this->addProjects($query, $timesheetQueryBuilder);
        $this->addActivities($query, $timesheetQueryBuilder);
        $this->addUsers($query, $timesheetQueryBuilder);
        $this->addTicketCondition($query, $timesheetQueryBuilder);

        return $this->analyseTimeSheets($timesheetQueryBuilder->getQuery()->getResult(), $query);
    }

    private function analyseTimeSheets(array $timeSheets, SprintReportQuery $query): array
    {
        $result = [
            'stats' => $this->getDailyStatistics($timeSheets, $query),
            'timesheets' => $timeSheets,
            'dataType' => 'duration',
            'period_attribute' => 'days',
            'subReportRoute' => 'report_user_week',
            'subReportDate' => $query->getBegin(),
            'decimal' => false,
            'userData' => $this->getUserInformation($timeSheets, $query),
            ...$this->getChartData($timeSheets, $query)
        ];

        return $result;
    }

    private function getUserInformation(array $timeSheets, SprintReportQuery $query): array
    {
        $result = $this->userInformation;
        foreach($result as &$info) {
            $info['tickets'] = count(array_unique($info['tickets']));
            $info['shouldHours'] = $this->getTotalHoursAndMinutes($info['shouldHours'], 'slim');
            $info['bookedHours'] = $this->getTotalHoursAndMinutes($info['bookedHours'], 'slim');
            $info['bookedOnTickets'] = $this->getTotalHoursAndMinutes($info['bookedOnTickets'], 'slim');
        }

        return $result;
    }

    private function getChartData(array $timeSheets, SprintReportQuery $query): array
    {
        $doughnut = $this->getDoughnutChart($timeSheets);
        $bar = $this->getBarChart($query);

        return [
            'doughnut' => [
                'activities' => array_values($doughnut['activities']),
                'values' => array_values($doughnut['values'])
            ],
            'bar_chart' => [
                'bars' => array_values($bar['bars']),
                'values' => array_values($bar['values']),
            ]
        ];
    }

    private function convertSeconds(int $seconds): string
    {
        if($seconds === 3600) {
            return '1 Stunde';
        }
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");
        if($seconds % 3600 === 0) {
            return $dtF->diff($dtT)->format('%h Stunden');
        } else {
            return $dtF->diff($dtT)->format('%h Stunden und %i Minuten');
        }
    }

    private function getColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }

    private function getDailyStatistics(array $timeSheets, SprintReportQuery $query)
    {
        $result = [];
        $sheets = [];
        $users = [];
        /** @var Timesheet $timeSheet */
        foreach($timeSheets as $timeSheet) {
            $users[$timeSheet->getUser()->getId()] = $timeSheet->getUser();
            $name = $timeSheet->getUser()->getName();
            $year = $timeSheet->getBegin()->format('Y');
            $month = $timeSheet->getBegin()->format('n');
            $day = $timeSheet->getBegin()->format('d');
            if(empty($sheets[$name][$year][$month][$day])) {
                $sheets[$name][$year][$month][$day] = 0;
            }
            $sheets[$name][$year][$month][$day] += $timeSheet->getDuration();
            $this->totalBookedTime += $timeSheet->getDuration();
            if($timeSheet->getMetaField('ticket')->getValue()) {
                $this->bookedTimeOnTickets += $timeSheet->getDuration();
            }
            $this->initUserInformation($timeSheet->getUser());
            $this->userInformation[$timeSheet->getUser()->getId()]['bookedHours'] += $timeSheet->getDuration();
            if($timeSheet->getMetaField('ticket')->getValue()) {
                $this->userInformation[$timeSheet->getUser()->getId()]['tickets'][] = $timeSheet->getMetaField('ticket')->getValue();
                $this->userInformation[$timeSheet->getUser()->getId()]['bookedOnTickets'] += $timeSheet->getDuration();
            }
        }
        foreach($query->getUsers() as $user) {
            $users[$user->getId()] = $user;
        }
        foreach($users as $user) {
            $statistics = new DailyStatistic($query->getBegin(), $query->getEnd(), $user);
            foreach($statistics->getDays() as $day) {
                if(!empty($sheets[$user->getName()][$day->getDate()->format('Y')][$day->getDate()->format('n')][$day->getDate()->format('d')])) {
                    $day->setTotalDuration($sheets[$user->getName()][$day->getDate()->format('Y')][$day->getDate()->format('n')][$day->getDate()->format('d')]);
                }
                $this->totalTime += $user->getWorkHoursForDay($day->getDate());
                $this->initUserInformation($user);
                $this->userInformation[$user->getId()]['shouldHours'] += $user->getWorkHoursForDay($day->getDate());
            }

            $result[] = $statistics;
        }

        return $result;
    }

    private function initUserInformation(User $user): void
    {
        if(empty($this->userInformation[$user->getId()])) {
            $this->userInformation[$user->getId()] = [
                'shouldHours' => 0,
                'bookedHours' => 0,
                'tickets' => [],
                'bookedOnTickets' => 0,
                'user' => $user
            ];
        }
    }

    private function addTicketCondition(SprintReportQuery $query, QueryBuilder $timesheetQueryBuilder): void
    {
        if($query->isHasTicket()) {
            $timesheetQueryBuilder->innerJoin('t.meta', 'm')
                ->andWhere(
                    $timesheetQueryBuilder->expr()->eq('m.name', ':ticket'),
                    $timesheetQueryBuilder->expr()->isNotNull('m.value'),
                )
                ->setParameter('ticket', 'ticket');
        }
    }

    private function addCustomer(SprintReportQuery $query, QueryBuilder $timesheetQueryBuilder): void
    {
        $customerIdArray = [];
        foreach ($query->getCustomers() as $customer) {
            $customerIdArray[] = $customer->getId();
        }
        if (!empty($customerIdArray)) {
            $timesheetQueryBuilder->innerJoin('t.project', 'p')
                ->andWhere(
                    $timesheetQueryBuilder->expr()->in('p.customer', ':customers')
                )
                ->setParameter('customers', $customerIdArray);
        }
    }

    private function addProjects(SprintReportQuery $query, QueryBuilder $timesheetQueryBuilder): void
    {
        $projectIdArray = [];
        foreach ($query->getProjects() as $project) {
            $projectIdArray[] = $project->getId();
        }
        if (!empty($projectIdArray)) {
            $timesheetQueryBuilder->andWhere(
                $timesheetQueryBuilder->expr()->in('t.project', ':projects')
            )
                ->setParameter('projects', $projectIdArray);
        }
    }

    private function addActivities(SprintReportQuery $query, QueryBuilder $timesheetQueryBuilder): void
    {
        $activityIdArray = [];
        foreach ($query->getActivities() as $activity) {
            $activityIdArray[] = $activity->getId();
        }
        if (!empty($activityIdArray)) {
            $timesheetQueryBuilder->andWhere(
                $timesheetQueryBuilder->expr()->in('t.activity', ':activities')
            )
                ->setParameter('activities', $activityIdArray);
        }
    }

    private function addUsers(SprintReportQuery $query, QueryBuilder $timesheetQueryBuilder): void
    {
        $userIdArray = [];
        foreach ($query->getUsers() as $users) {
            $userIdArray[] = $users->getId();
        }
        if (!empty($userIdArray)) {
            $timesheetQueryBuilder->andWhere(
                $timesheetQueryBuilder->expr()->in('t.user', ':users')
            )
                ->setParameter('users', $userIdArray);
        }
    }

    private function getBarChart(SprintReportQuery $query): array
    {
        $totalHours = round($this->totalTime / 3600, 2);
        $result = [
            'bars' => [
                'Verfügbare Gesamtstunden',
                'Geplante Stunden',
                'Gesamt gebuchte Stunden',
                'Gebuchte Stunden auf Tickets'
            ],
            'values' => [
                [
                    ['value' => $totalHours, 'duration' => $this->getTotalHoursAndMinutes($this->totalTime), 'label' => 'Verfügbare Gesamtstunden', 'name' => 'Verfügbare Gesamtstunden', 'color' => $this->getColor()],
                    ['value' => round($totalHours * $query->getPlan() / 100), 'duration' => $this->getTotalHoursAndMinutes($this->totalTime * $query->getPlan() / 100), 'label' => 'Geplante Gesamtstunden', 'name' => 'Geplante Gesamtstunden', 'color' => $this->getColor()],
                    ['value' => round($this->totalBookedTime / 3600, 2), 'duration' => $this->getTotalHoursAndMinutes($this->totalBookedTime), 'label' => 'Gebuchte Stunden', 'name' => 'Gebuchte Stunden', 'color' => $this->getColor()],
                    ['value' => round($this->bookedTimeOnTickets / 3600, 2), 'duration' => $this->getTotalHoursAndMinutes($this->bookedTimeOnTickets), 'label' => 'Gebuchte Stunden auf Tickets', 'name' => 'Gebuchte Stunden auf Tickets', 'color' => $this->getColor()],
                ]
            ]
        ];

        return $result;
    }

    protected function getTotalHoursAndMinutes(int $seconds, string $mode = 'detailed'): string
    {
        $hours = floor($seconds / 3600);
        if($mode == 'detailed') {
            if ($hours === 1) {
                $hours .= ' Stunde';
            } else {
                $hours .= ' Stunden';
            }
            if ($seconds % 3600 > 0) {
                $minutes = round(($seconds % 3600) / 60);
                if ($minutes > 0) {
                    if ($minutes === 1) {
                        $hours .= ' und 1 Minute';
                    } else {
                        $dtF = new \DateTime('@0');
                        $dtT = new \DateTime('@' . ($seconds % 3600));
                        $hours .= ' und ' . $dtF->diff($dtT)->format('%i Minuten');
                    }
                }
            }
        } else {
            $minutes = 0;
            if ($seconds % 3600 > 0) {
                $dtF = new \DateTime('@0');
                $dtT = new \DateTime('@' . ($seconds % 3600));
                $minutes = $dtF->diff($dtT)->format('%i');
            }
            return $hours . ':' . $minutes;
        }

        return $hours;
    }


    /**
     * @param array $timeSheets
     * @return array
     */
    private function getDoughnutChart(array $timeSheets): array
    {
        $doughnut = [
            'activities' => [],
            'values' => []
        ];
        /** @var Timesheet $timeSheet */
        foreach ($timeSheets as $timeSheet) {
            $activityId = $timeSheet->getActivity()->getId();
            $doughnut['activities'][$activityId] = $timeSheet->getActivity()->getName();
            if (empty($doughnut['values'][$activityId])) {
                $doughnut['values'][$activityId] = [
                    'name' => $timeSheet->getActivity()->getName(),
                    'color' => $timeSheet->getActivity()->getColor() ?: $this->getColor(),
                    'value' => 0,
                    'duration' => '',
                    'rate' => 0
                ];
            }
            $doughnut['values'][$activityId]['value'] += $timeSheet->getDuration();
        }
        foreach ($doughnut['values'] as &$value) {
            if ($value['value'] != 1) {
                $value['duration'] = $this->convertSeconds($value['value']);
            } else {
                $value['duration'] = $this->convertSeconds($value['value']);
            }
        }
        return $doughnut;
    }
}
