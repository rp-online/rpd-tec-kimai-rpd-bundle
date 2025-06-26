<?php

namespace KimaiPlugin\RPDBundle\Reporting\SprintUserReport;

use App\Form\Model\DateRange;
use App\Repository\Query\ExportQuery;

class SprintUserQuery extends ExportQuery
{
    private DateRange $currentSprint;

    private int $planFactor = 50;

    public function __construct()
    {
        parent::__construct();
        $this->currentSprint = new DateRange();
        $this->currentSprint->setBegin($this->getLastMonday(new \DateTime()));
        $this->currentSprint->setEnd((clone $this->currentSprint->getBegin())->modify('+2 weeks -1 second'));
    }

    private function getLastMonday(\DateTime $date): \DateTime
    {
        // Überprüfen, ob der Tag Montag ist
        if ((int) $date->format('N') === 1) {
            return $date;
        }

        // Zum letzten Montag zurückgehen
        return $date->modify('last monday');
    }

    public function getCurrentSprint(): DateRange
    {
        return $this->currentSprint;
    }

    public function setCurrentSprint(DateRange $currentSprint): void
    {
        $this->currentSprint = $currentSprint;
    }

    public function getPlanFactor(): int
    {
        return $this->planFactor;
    }

    public function setPlanFactor(int $planFactor): void
    {
        $this->planFactor = $planFactor;
    }

}