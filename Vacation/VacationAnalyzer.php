<?php

namespace KimaiPlugin\RPDBundle\Vacation;

use App\Configuration\SystemConfiguration;
use KimaiPlugin\RPDBundle\Entity\Vacation;
use KimaiPlugin\RPDBundle\Repository\VacationRepository;

class VacationAnalyzer
{


    public function __construct(
        private readonly VacationRepository $vacationRepository,
        private readonly SystemConfiguration $systemConfiguration,
        private readonly PublicHoliday $publicHoliday
    )
    {
    }

    public function analyzeVacation(Vacation $vacation): void
    {
        $otherVacationRequests = $this->vacationRepository->checkVacationForToManyVacations($vacation, $this->systemConfiguration->find('vacation.problem_threshold') ?? 2);
        $notes = [];
        foreach($otherVacationRequests as $date => $vacations) {
            $notes[] = sprintf('Am %s gibt es bereits %d %s', $date, $vacations, ($vacations === 1 ? 'Urlaub' : 'Urlaube'));
        }
        if(!$this->hasEnoughVacationDays($vacation)) {
            $notes[] = 'Der Urlaub kann nicht genehmigt werden, da nicht genügend Urlaubstage vorhanden sind.';
        }
        $vacation->setNotes(implode("\n", $notes));
    }

    protected function hasEnoughVacationDays(Vacation $vacation): bool
    {
        $allVacations = $this->vacationRepository->findAllByUserExcept($vacation);
        $vacationDays = 0;
        foreach($allVacations as $otherVacation) {
            $end = clone $otherVacation->getEnd();
            $end->modify('+1 day'); // include end date

            $interval = new \DateInterval('P1D'); // 1 day
            $period = new \DatePeriod($otherVacation->getStart(), $interval, $end);

            foreach($period as $date) {
                if(!$this->publicHoliday->isPublicHoliday($date) && $vacation->getUser()->getWorkHoursForDay($date) > 0) {
                    $vacationDays++;
                }
            }
        }

        return $vacation->getUser()->getHolidaysPerYear() >= $vacationDays;
    }


}