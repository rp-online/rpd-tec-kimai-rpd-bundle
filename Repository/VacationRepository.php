<?php

namespace KimaiPlugin\RPDBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\RPDBundle\Entity\Vacation;

/**
 * @extends ServiceEntityRepository<Vacation>
 */
class VacationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vacation::class);
    }

    public function findByUser(User $user, int $year)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.user = :user AND YEAR(v.end) = :year')
            ->setParameter('year', $year)
            ->setParameter('user', $user)
            ->getQuery()->getResult();
    }

    public function findActualByUser(User $user, int $year)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.user = :user AND YEAR(v.end) = :year AND v.declined = 0 AND v.approved = 1')
            ->setParameter('year', $year)
            ->setParameter('user', $user)
            ->getQuery()->getResult();
    }

    public function findForUsers(array $users, int $year)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.user IN (:users) AND YEAR(v.end) = :year AND v.declined = 0 AND v.approved = 1')
            ->setParameter('year', $year)
            ->setParameter('users', $users)
            ->getQuery()->getResult();
    }

    public function checkIfOverlapped(\DateTime $start, \DateTime $end, User $user): bool
    {
        $qb = $this->createQueryBuilder('v');

        $qb->where('v.start <= :end')
            ->andWhere('v.end >= :start')
            ->andWhere('v.user = :user')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('user', $user)
        ;

        return count($qb->getQuery()->getResult()) > 0;
    }

    public function checkVacationForToManyVacations(Vacation $vacation, int $threshold = 3): array
    {
        $results = [];
        $interval = new \DateInterval('P1D'); // 1 Tag
        $end = clone $vacation->getEnd();
        $period = new \DatePeriod($vacation->getStart(), $interval, $end->modify('+1 day'));

        foreach ($period as $date) {
            $qb = $this->createQueryBuilder('v');

            try {
                $count = $qb->select('COUNT(v.id) as recordCount')
                    ->where(':date BETWEEN v.start AND v.end AND v.approved = 1')
                    ->setParameter('date', $date)
                    ->having('recordCount >= ' . $threshold)
                    ->getQuery()
                    ->getSingleScalarResult();
                $results[$date->format('d.m.Y')] = $count;

            } catch (NoResultException $e) {
            } catch (NonUniqueResultException $e) {
            }

        }

        return $results;
    }

//    /**
//     * @return Vacation[] Returns an array of Vacation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('v.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Vacation
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
