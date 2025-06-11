<?php

namespace KimaiPlugin\RPDBundle\Vacation;

class VacationYear
{

    private \DateTime $date;

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): void
    {
        $this->date = $date;
    }

}