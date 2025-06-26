<?php

namespace KimaiPlugin\RPDBundle\Vacation;

use App\Entity\User;
use KimaiPlugin\RPDBundle\Repository\VacationRepository;

class VacationService
{


    public function __construct(private readonly VacationRepository $repository)
    {
    }

    public function hasVacation(User $user, \DateTime $date): bool
    {
        return $this->repository->checkIfOverlapped($date, $date, $user, true);
    }
}