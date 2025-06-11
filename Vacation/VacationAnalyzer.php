<?php

namespace KimaiPlugin\RPDBundle\Vacation;

use App\Configuration\SystemConfiguration;
use KimaiPlugin\RPDBundle\Entity\Vacation;
use KimaiPlugin\RPDBundle\Repository\VacationRepository;

class VacationAnalyzer
{


    public function __construct(
        private readonly VacationRepository $vacationRepository,
        private readonly SystemConfiguration $systemConfiguration
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
        $vacation->setNotes(implode("\n", $notes));
    }


}