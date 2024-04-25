<?php

namespace KimaiPlugin\RPDBundle\Reporting\SprintReport;

use App\Repository\Query\ExportQuery;

class SprintReportQuery extends ExportQuery
{
    private bool $hasTicket = false;

    private string $plan = '50';

    public function __construct()
    {
        parent::__construct();
        $this->dateRange?->setBegin(new \DateTime('-14 days'));
        $this->dateRange?->setEnd(new \DateTime());
    }

    public function isHasTicket(): bool
    {
        return $this->hasTicket;
    }

    public function setHasTicket(bool $hasTicket): void
    {
        $this->hasTicket = $hasTicket;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): void
    {
        $this->plan = $plan;
    }
}