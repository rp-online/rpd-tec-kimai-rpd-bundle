<?php

namespace KimaiPlugin\RPDBundle\Repository;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Form\Model\DateRange;
use App\Repository\TimesheetRepository;
use Doctrine\DBAL\Types\Types;

class ExtendedTimesheetRepository extends TimesheetRepository
{

    /**
     * @param User $user
     * @param DateRange $dateRange
     * @return array<Timesheet> array
     */
    public function getAllTimesheetsForDateRange(User $user, DateRange $dateRange, array $projects): array
    {
        $queryBuilder = $this->createQueryBuilder('t');
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->gte('DATE(t.begin)', ':start'),
                $queryBuilder->expr()->lte('DATE(t.end)', ':end'),
                $queryBuilder->expr()->eq('t.user', ':user')
            )
            ->setParameter('start', $dateRange->getBegin(), Types::DATETIME_MUTABLE)
            ->setParameter('end', $dateRange->getEnd(), Types::DATETIME_MUTABLE)
            ->setParameter('user', $user)
        ;
        if(!empty($projects)) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('t.project', ':projects'))
                ->setParameter('projects', $projects);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}