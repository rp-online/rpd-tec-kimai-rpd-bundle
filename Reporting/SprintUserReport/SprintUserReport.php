<?php

namespace KimaiPlugin\RPDBundle\Reporting\SprintUserReport;

use App\Configuration\SystemConfiguration;
use App\Entity\Timesheet;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\RPDBundle\Repository\ExtendedTimesheetRepository;
use KimaiPlugin\RPDBundle\Vacation\PublicHoliday;
use KimaiPlugin\RPDBundle\Vacation\VacationService;
use Symfony\Component\HttpClient\HttpClient;

class SprintUserReport
{
    private SprintUserQuery $query;
    private ExtendedTimesheetRepository $repository;

    private array $timesheets = [];
    private array $tickets = [];

    private int $targetHours = 0;
    private int $finishedTickets = 0;
    private int $supportedTickets = 0;
    private int $totalBookedTime = 0;
    private int $bookedTimeOnTickets = 0;
    private float $focusScore = 0.0;
    private float $pti = 0.0;
    private float $estimateAccuracy = 0.0;
    private array $loopedTickets = [];
    private array $overviewChart = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemConfiguration    $systemConfiguration,
        private readonly PublicHoliday          $publicHoliday,
        private readonly VacationService        $vacationService
    )
    {
        $this->repository = $this->getRepository();
    }

    private function getRepository(): ExtendedTimesheetRepository
    {
        $classMetaData = $this->entityManager->getClassMetadata(Timesheet::class);
        return new ExtendedTimesheetRepository($this->entityManager, $classMetaData);
    }

    public function setQuery(SprintUserQuery $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function create(): void
    {
        $this->timesheets = $this->repository->getAllTimesheetsForDateRange(
            $this->query->getUser(), $this->query->getCurrentSprint(), $this->query->getProjects());
        foreach ($this->timesheets as $timesheet) {
            $this->totalBookedTime += $timesheet->getDuration();
            $ticket = $timesheet->getMetaField('ticket')?->getValue();
            if (empty($ticket)) {
                continue;
            }
            $this->bookedTimeOnTickets += $timesheet->getDuration();
            $this->tickets[$ticket] = [];
        }
        $this->loadTicketData()
            ->createTargetHours()
            ->calculateFinishedTickets()
            ->calculateSupportedTickets()
            ->calculateFocusScore()
            ->calculatePTI()
            ->calculateEstimateAccuracy()
            ->calculateLoopedTickets()
            ->generateOverviewChart();
    }

    private function generateOverviewChart(): self
    {

        $doughnut = [
            'labels' => [],
            'values' => []
        ];
        /** @var Timesheet $timeSheet */
        foreach ($this->timesheets as $timeSheet) {
            $activityId = $timeSheet->getActivity()->getId();
            $doughnut['labels'][$activityId] = $timeSheet->getActivity()->getName();
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
                $value['duration'] = $this->getTotalHoursAndMinutes($value['value']);
            } else {
                $value['duration'] = $this->getTotalHoursAndMinutes($value['value']);
            }
        }
        $doughnut['labels'] = array_values($doughnut['labels']);
        $doughnut['values'] = array_values($doughnut['values']);
        $this->overviewChart = $doughnut;

        return $this;
    }

    protected function getTotalHoursAndMinutes(int $seconds, string $mode = 'detailed'): string
    {
        $hours = (int)floor($seconds / 3600);
        if ($mode == 'detailed') {
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

    private function calculateLoopedTickets(): self
    {
        $currentWeek = $this->query->getCurrentSprint()->getBegin()?->format('W');
        $year = $this->query->getCurrentSprint()->getBegin()?->format('y');
        foreach ($this->tickets as $id => &$ticket) {
            $earliestSprint = ['year' => 2100, 'week' => 53];
            $labels = $ticket['labels'] ?? [];
            foreach ($labels as $label) {
                $matches = [];
                if (preg_match("/RPD-KW(?<kw>[\d]{2})-(?<year>[\d]{2})/", $label, $matches)) {
                    if (empty($matches['kw']) || empty($matches['year'])) {
                        continue;
                    }
                    if ($matches['kw'] < $currentWeek && $matches['year'] <= $year) {
                        $this->loopedTickets[$id] = &$ticket;
                    }
                    if ($matches['kw'] < $earliestSprint['week'] && $matches['year'] <= $earliestSprint['year']) {
                        $earliestSprint['week'] = $matches['kw'];
                        $earliestSprint['year'] = $matches['year'];
                    }
                }
            }
            if ($earliestSprint['year'] < 2100) {
                $ticket['earliest_sprint'] = 'RPD-KW' . $earliestSprint['week'] . '-' . $earliestSprint['year'];
            }
        }

        return $this;
    }

    private function calculateEstimateAccuracy(): self
    {
        $times = [];
        foreach ($this->tickets as $ticket) {
            if (empty($ticket['estimate']) || $ticket['estimate'] <= 0) {
                continue;
            }
            $factor = (($ticket['total_time_spent'] - $ticket['estimate']) / $ticket['estimate']) * 100;
            if ($factor > 0) {
                $times[] = $factor;
            }
        }
        $this->estimateAccuracy = count($times) > 0 ? (100 - (array_sum($times) / count($times))) : 0.0;

        return $this;
    }

    private function getColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }

    private function calculatePTI(): self
    {
        if($this->bookedTimeOnTickets <= 0 || $this->targetHours <= 0) {
            return $this;
        }
        $efficiencyFactor = (($this->targetHours * $this->query->getPlanFactor()) / 100) / $this->bookedTimeOnTickets;
        $actualTicketBookedAndEstimatedTime = [];
        foreach ($this->tickets as $ticket) {
            if (empty($ticket['estimate']) || $ticket['estimate'] <= 0) {
                continue;
            }
            $factor = ($ticket['total_time_spent'] - $ticket['estimate']) / $ticket['estimate'];
            if ($factor > 0) {
                $actualTicketBookedAndEstimatedTime[] = $factor;
            }
        }
        if (!empty($actualTicketBookedAndEstimatedTime)) {
            $this->pti = $efficiencyFactor * (1 - (array_sum($actualTicketBookedAndEstimatedTime) / count($actualTicketBookedAndEstimatedTime)));
        }

        return $this;
    }

    private function calculateFocusScore(): self
    {
        if ($this->totalBookedTime > 0) {
            $this->focusScore = (($this->bookedTimeOnTickets / $this->totalBookedTime) * 100);
        }

        return $this;
    }

    private function calculateSupportedTickets(): self
    {
        foreach ($this->tickets as $ticket) {
            if (empty($ticket['worklogs']) || $ticket['code_owner'] === $this->query->getUser()?->getDisplayName()) {
                continue;
            }
            foreach ($ticket['worklogs'] as $workLog) {
                if (
                    (
                        $workLog['author'] === $this->query->getUser()?->getDisplayName()
                        || $workLog['author'] === $this->query->getUser()?->getEmail()
                    )
                ) {

                    $this->supportedTickets++;
                    break; // only count once per ticket
                }
            }
        }

        return $this;
    }

    private function calculateFinishedTickets(): self
    {
        foreach ($this->tickets as $ticket) {
            $openTicketStatuses = ['Offen', 'In Arbeit', 'Fertig zur Umsetzung', 'Fertig zur Beauftragung', 'Bereit für Code Review', 'Refinement', 'Code Review', 'Planung', 'PO Backlog refined', 'Sprint Backlog', 'Dev-Refinement'];
            if (empty($ticket['status']) || in_array($ticket['status'], $openTicketStatuses, true)) {
                continue;
            }
            $this->finishedTickets++;
        }

        return $this;
    }

    private function createTargetHours(): self
    {
        $currentSprintDateRange = $this->query->getCurrentSprint();
        $start = $currentSprintDateRange->getBegin();
        $end = clone $currentSprintDateRange->getEnd();
        if (empty($start) || empty($end)) {
            return $this;
        }
        $user = $this->query->getUser();
        if (empty($user)) {
            return $this;
        }
        if($start->format('d.m.Y') === $end->format('d.m.Y')) {
            $date = $start;
            if (
                $user->getWorkHoursForDay($date) > 0
                && !$this->publicHoliday->isPublicHoliday($date)
                && $date <= (new \DateTime('now'))
                && !$this->vacationService->hasVacation($user, $date)
            ) {
                $this->targetHours += $user->getWorkHoursForDay($date);
            }
        } else {
            $interval = new \DateInterval('P1D'); // 1 day
            $period = new \DatePeriod($start, $interval, $end->modify('+1 day'));
            foreach ($period as $date) {
                if (
                    $user->getWorkHoursForDay($date) > 0
                    && !$this->publicHoliday->isPublicHoliday($date)
                    && $date <= (new \DateTime('now'))
                    && !$this->vacationService->hasVacation($user, $date)
                ) {
                    $this->targetHours += $user->getWorkHoursForDay($date);
                }
            }
        }

        return $this;
    }

    private function loadTicketData(): self
    {
        if (empty($this->tickets)) {
            return $this;
        }
        $ticketData = $this->bulkFetchTicketData();
        foreach ($ticketData['issues'] ?? [] as $issue) {
            if (empty($issue['key'])) {
                continue;
            }
            $this->tickets[$issue['key']] = $this->extractRelevantIssueData($issue);
        }

        return $this;
    }

    private function bulkFetchTicketData(): array
    {
        $jiraUrl = $this->systemConfiguration->find('jira.url');
        if (empty($jiraUrl)) {
            return [];
        }
        $ticketIds = array_keys($this->tickets);
        $body = [
            'issueIdsOrKeys' => $ticketIds,
            'fields' => [
                'summary',
                'status',
                'assignee',
                'issuetype',
                'priority',
                'customfield_13302', // Code Owner
                'progress',
                'labels',
                'timeoriginalestimate'
            ]
        ];
        $jiraUrl = rtrim($jiraUrl, '/') . '/bulkfetch';
        $client = HttpClient::create();
        $user = $this->query->getUser();
        $jiraUsername = $user?->getPreference('jira_username')?->getValue();
        $jiraApiKey = $user?->getPreference('jira_password')?->getValue();
        if (empty($jiraUsername) || empty($jiraApiKey)) {
            return [];
        }

        $response = $client->request('POST', $jiraUrl, [
            'auth_basic' => $jiraUsername . ':' . $jiraApiKey,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body)
        ]);

        return $response->toArray();
    }

    private function extractRelevantIssueData(array $issue): array
    {
        $result = [
            'id' => $issue['key'] ?? '',
            'status' => $issue['fields']['status']['name'] ?? 'unknown',
            'statusCategory' => $issue['fields']['status']['statusCategory']['key'] ?? [],
            'assignee' => $issue['fields']['assignee']['displayName'] ?? 'unassigned',
            'type' => $issue['fields']['issuetype']['name'] ?? 'unknown',
            'title' => $issue['fields']['summary'] ?? 'No title',
            'priority' => $issue['fields']['priority']['name'] ?? 'unknown',
            'code_owner' => $issue['fields']['customfield_13302']['displayName'] ?? 'unknown',
            'total_time_spent' => $issue['fields']['progress']['total'] ?? 0,
            'labels' => $issue['fields']['labels'] ?? [],
            'time_spent_from_user_total' => 0,
            'time_spent_from_user_in_sprint' => 0,
            'estimate' => $issue['fields']['timeoriginalestimate'] ?? 0,
        ];
        $workLogArray = $this->loadWorkLogOfTicket($result['id']);
        foreach ($workLogArray['worklogs'] ?? [] as $workLog) {
            if (empty($workLog['author']['displayName'])) {
                continue;
            }
            $result['worklogs'][] = [
                'author' => $workLog['author']['displayName'],
                'timeSpent' => $workLog['timeSpentSeconds'] ?? 0,
                'comment' => $workLog['comment'] ?? '',
                'created' => $workLog['created'] ?? '',
            ];
            if (
                $workLog['author']['emailAddress'] === $this->query->getUser()?->getEmail()
                || $workLog['author']['displayName'] === $this->query->getUser()?->getDisplayName()
            ) {
                $result['time_spent_from_user_total'] += $workLog['timeSpentSeconds'] ?? 0;
                $created = new \DateTime($workLog['created'] ?? 'now');
                if($created >= $this->query->getCurrentSprint()->getBegin() && $created <= $this->query->getCurrentSprint()->getEnd()) {
                    $result['time_spent_from_user_in_sprint'] += $workLog['timeSpentSeconds'] ?? 0;
                }
            }
        }

        return $result;
    }

    private function loadWorkLogOfTicket(string $ticket): array
    {
        $jiraUrl = $this->systemConfiguration->find('jira.url');
        if (empty($jiraUrl)) {
            return [];
        }
        $jiraUrl = rtrim($jiraUrl, '/') . '/' . $ticket . '/worklog';
        $client = HttpClient::create();
        $user = $this->query->getUser();
        $jiraUsername = $user?->getPreference('jira_username')?->getValue();
        $jiraApiKey = $user?->getPreference('jira_password')?->getValue();
        if (empty($jiraUsername) || empty($jiraApiKey)) {
            return [];
        }

        $response = $client->request('GET', $jiraUrl, [
            'auth_basic' => $jiraUsername . ':' . $jiraApiKey,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);

        return $response->toArray();
    }

    public function getTotalTickets(): int
    {
        return count($this->tickets);
    }

    public function getBookedHoursOnTickets(): string
    {
        return $this->getTotalHoursAndMinutes($this->bookedTimeOnTickets);
    }

    public function getBookedHours(): string
    {
        return $this->getTotalHoursAndMinutes($this->totalBookedTime);
    }

    public function getTargetHours(): string
    {
        return $this->getTotalHoursAndMinutes(($this->targetHours * $this->query->getPlanFactor()) / 100);
    }

    public function getTotalHours(): string
    {
        return $this->getTotalHoursAndMinutes($this->targetHours);
    }

    public function getFinishedTickets(): int
    {
        return $this->finishedTickets;
    }

    public function getSupportedTickets(): int
    {
        return $this->supportedTickets;
    }

    public function getFocusScore(): float
    {
        return $this->focusScore;
    }

    public function getPTI(): float
    {
        return $this->pti;
    }

    public function getEstimateAccuracy(): float
    {
        return $this->estimateAccuracy;
    }

    public function getLoopedTickets(): int
    {
        return count($this->loopedTickets);
    }

    public function getOverviewChart(): array
    {
        return $this->overviewChart;
    }

    public function getAllTickets(): array
    {
        return $this->tickets;
    }

    public function getAllLoopedTickets(): array
    {
        return $this->loopedTickets;
    }

}