<?php

namespace KimaiPlugin\RPDBundle\Controller\Vacation;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Utils\PageSetup;
use DateInterval;
use DatePeriod;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\RPDBundle\Entity\Vacation;
use KimaiPlugin\RPDBundle\Form\VacationAddForm;
use KimaiPlugin\RPDBundle\Form\VacationApproveForm;
use KimaiPlugin\RPDBundle\Form\VacationRequestForm;
use KimaiPlugin\RPDBundle\Form\VacationRevokeForm;
use KimaiPlugin\RPDBundle\Form\VacationYearSelectionForm;
use KimaiPlugin\RPDBundle\Repository\VacationRepository;
use KimaiPlugin\RPDBundle\Vacation\PublicHoliday;
use KimaiPlugin\RPDBundle\Vacation\VacationAnalyzer;
use KimaiPlugin\RPDBundle\Vacation\VacationMailer;
use KimaiPlugin\RPDBundle\Vacation\VacationYear;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/vacation')]
class VacationController extends AbstractController
{

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly VacationMailer $vacationMailer,
        private readonly VacationAnalyzer $vacationAnalyzer,
        private readonly PublicHoliday $publicHoliday
    )
    {
    }

    #[Route(path: '', name: 'vacation_overview', methods: ['GET', 'POST'])]
    public function index(Request $request, VacationRepository $vacationRepository, EntityManagerInterface $entityManager): Response
    {
        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory($currentUser);
        $defaultDate = $dateTimeFactory->createStartOfYear();

        $values = new VacationYear();
        $values->setDate($defaultDate);
        $form = $this->createFormForGetRequest(VacationYearSelectionForm::class, $values, [
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate()
        ]);
        $form->submit($request->query->all(), false);
        $date = $values->getDate();
        list($relevantUsers, $allVacationDays) = $this->getCalendarInformation($currentUser, $vacationRepository, $date);

        $page = new PageSetup('Urlaub');
        $page->setActionName('overview');
        $page->setActionPayload(['year' => $date]);
        $page->setPaginationForm($form);
        $holidayInformation = $this->getHolidayInformation($currentUser, $vacationRepository, $request, $entityManager, $date);
        if (!is_array($holidayInformation)) {
            return $holidayInformation; // Response from holiday information form submission
        }
        $teamOverview = $this->getTeamOverview($currentUser, $vacationRepository, $request, $entityManager, $date);
        if (!is_array($teamOverview)) {
            return $teamOverview; // Response from team overview form submission
        }

        return $this->render('@RPD/vacation/index.html.twig', [
            'page_title' => 'Urlaubsverwaltung',
            'page_setup' => $page,
            'page_description' => 'Hier kannst du deinen Urlaub verwalten.',
            'year' => $date,
            'max_days' => $this->getMaxDaysNumber($date),
            'all_vacations' => $allVacationDays,
            'public_holidays' => $this->publicHoliday->getAll($date),
            'me' => $currentUser,
            'all_users' => $relevantUsers,
            'calendar_year_form' => $form->createView(),
            'holiday_infos' => $holidayInformation,
            'team_overview' => $teamOverview,
        ]);
    }

    /**
     * @param User $currentUser
     * @param VacationRepository $vacationRepository
     * @param \DateTime $defaultDate
     * @return array[]
     */
    public function getCalendarInformation(User $currentUser, VacationRepository $vacationRepository, \DateTime $defaultDate): array
    {
        $relevantUsers = [];
        $relevantUsers[$currentUser->getId()] = $currentUser;
        foreach ($currentUser->getTeams() as $team) {
            foreach ($team->getUsers() as $user) {
                if ($user->getId() === $currentUser->getId() || !empty($relevantUsers[$user->getId()])) {
                    continue;
                }
                $relevantUsers[$user->getId()] = $user;
            }
        }
        $allVacationDays = [];
        $vacations = $vacationRepository->findForUsers($relevantUsers, $defaultDate->format('Y'));
        /** @var Vacation $vacation */
        foreach ($vacations as $vacation) {
            $end = clone $vacation->getEnd();
            $end->modify('+1 day');

            $interval = new DateInterval('P1D'); // 1 Tag
            $period = new DatePeriod($vacation->getStart(), $interval, $end);

            /** @var \DateTime $date */
            foreach ($period as $date) {
                if ($vacation->getUser()->getWorkHoursForDay($date) <= 0) {
                    continue;
                }
                $allVacationDays[$date->getTimestamp()]['users'][] = $vacation->getUser()->getDisplayName();
                $allVacationDays[$date->getTimestamp()]['colors'][] = $vacation->getUser()->getColor();
            }
        }
        return array($relevantUsers, $allVacationDays);
    }

    protected function getHolidayInformation(User $user, VacationRepository $vacationRepository, Request $request, EntityManagerInterface $entityManager, \DateTime $currentYear): array|Response
    {
        $result = [
            'holidays_per_year' => $user->getHolidaysPerYear()
        ];

        $vacationRevokeForm = $this->createForm(VacationRevokeForm::class);
        $vacationRevokeForm->handleRequest($request);
        if ($vacationRevokeForm->isSubmitted() && $vacationRevokeForm->isValid()) {
            $data = $vacationRevokeForm->getData();
            if (!empty($data['vacationId'])) {
                $vacationToRemove = $vacationRepository->find($data['vacationId']);
                if ($vacationToRemove instanceof Vacation) {
                    $this->vacationMailer->sendVacationRevoked($vacationToRemove);
                    $entityManager->remove($vacationToRemove);
                    $entityManager->flush();
                    $this->flashSuccess('Urlaub erfolgreich widerrufen!');
                    return $this->redirectToRoute('vacation_overview');
                }
            }
        }
        $result['vacation_revoke_form'] = $vacationRevokeForm;

        $dummyVacation = new Vacation();
        $vacationRequestForm = $this->createForm(VacationRequestForm::class, $dummyVacation);
        $vacationRequestForm->handleRequest($request);
        if ($vacationRequestForm->isSubmitted()) {
            if ($vacationRequestForm->isValid()) {
                $newVacation = $vacationRequestForm->getData();
                if ($newVacation->getStart() > $newVacation->getEnd()) {
                    $this->flashError('Fehler: Das Startdatum muss vor dem Enddatum liegen!');
                } else if ($vacationRepository->checkIfOverlapped($newVacation->getStart(), $newVacation->getEnd(), $user)) {
                    $this->flashError('Fehler: An einem der Tage hast du bereits Urlaub genommen! Bitte passe deine Tage an!');
                } else {
                    $newVacation->setUser($user);
                    $entityManager->persist($newVacation);
                    $entityManager->flush();
                    $this->vacationMailer->sendNewVacationRequest($newVacation);
                    $this->flashSuccess('Urlaub erfolgreich beantragt!');
                    return $this->redirectToRoute('vacation_overview');
                }
            } else {
                $this->flashError('Fehler: Da ist etwas schief gelaufen! Bitte überprüfe deine Eingaben.');
            }
        }
        $result['vacation_request_form'] = $vacationRequestForm->createView();

        $result['current_vacations'] = $vacationRepository->findByUser($user, $currentYear->format('Y'));
        $days = 0;
        $notApprovedDays = 0;
        $alreadyNoticed = [];
        /** @var Vacation $vacation */
        foreach ($result['current_vacations'] as $vacation) {
            if ($vacation->isDeclined()) {
                continue; // Urlaub abgelehnt
            }
            $end = clone $vacation->getEnd();
            $end->modify('+1 day');
            $interval = new DateInterval('P1D'); // 1 Tag
            $period = new DatePeriod($vacation->getStart(), $interval, $end);
            $type = $vacation->isApproved() ? 'days' : 'notApprovedDays';
            foreach ($period as $date) {
                if ($vacation->getUser()->getWorkHoursForDay($date) && empty($alreadyNoticed[$date->getTimestamp()]) && !$this->publicHoliday->isPublicHoliday($date)) {
                    $$type++;
                    $alreadyNoticed[$date->getTimestamp()] = $date;
                }
            }
        }
        $result['already_taken'] = $days;
        $result['pending'] = $notApprovedDays;


        return $result;
    }

    protected function getTeamOverview(User $user, VacationRepository $vacationRepository, Request $request, EntityManagerInterface $entityManager, \DateTime $currentYear): array|Response
    {
        $teamOverview = [];
        $approveForm = $this->createForm(VacationApproveForm::class);
        $approveForm->handleRequest($request);
        if ($approveForm->isSubmitted() && $approveForm->isValid()) {
            $data = $approveForm->getData();
            if (!empty($data['vacationId'])) {
                $approvedOrDeclinedVacation = $vacationRepository->find($data['vacationId']);
                if ($approvedOrDeclinedVacation instanceof Vacation) {
                    if ($approveForm->get('approve')->isClicked()) {
                        $approvedOrDeclinedVacation->setApproved(true);
                        $approvedOrDeclinedVacation->setApprovedAt(new \DateTimeImmutable());
                        $approvedOrDeclinedVacation->setApprovedBy($user);
                        $this->vacationMailer->sendVacationApproved($approvedOrDeclinedVacation);
                        $this->flashSuccess('Antrag genehmigt');
                    } else {
                        $approvedOrDeclinedVacation->setDeclined(true);
                        $approvedOrDeclinedVacation->setDeclineReason($data['reason'] ?? '');
                        $this->vacationMailer->sendVacationDeclined($approvedOrDeclinedVacation);
                        $this->flashSuccess('Antrag abgelehnt');
                    }
                    $entityManager->persist($approvedOrDeclinedVacation);
                    $entityManager->flush();
                    return $this->redirectToRoute('vacation_overview');
                }
            }
        }
        $teamOverview['approve_form'] = $approveForm;
        $dummyVacation = new Vacation();
        $addForm = $this->createForm(VacationAddForm::class, $dummyVacation);
        $addForm->handleRequest($request);
        if ($addForm->isSubmitted() && $addForm->isValid()) {
            /** @var Vacation $newVacation */
            $newVacation = $addForm->getData();
            if ($newVacation->getStart() > $newVacation->getEnd()) {
                $this->flashError('Fehler: Das Startdatum muss vor dem Enddatum liegen!');
            } else if ($vacationRepository->checkIfOverlapped($newVacation->getStart(), $newVacation->getEnd(), $newVacation->getUser())) {
                $this->flashError('Fehler: An einem der Tage hast du bereits Urlaub genommen! Bitte passe deine Tage an!');
            } else {
                if ($newVacation->isApproved()) {
                    $newVacation->setApprovedAt(new \DateTimeImmutable());
                    $newVacation->setApprovedBy($user);
                }
                $entityManager->persist($newVacation);
                $entityManager->flush();
                $this->vacationMailer->sendVacationAdded($newVacation);
                $this->flashSuccess('Urlaub erfolgreich hinzugefügt!');
                return $this->redirectToRoute('vacation_overview');
            }
        }
        $teamOverview['add_form'] = $addForm->createView();
        foreach ($user->getTeams() as $team) {
            if (!$user->isTeamleadOf($team)) {
                continue;
            }
            $teamData = [
                'label' => $team->getName(),
                'members' => [],
                'open_requests' => []
            ];
            foreach ($team->getMembers() as $member) {
                $vacations = $vacationRepository->findByUser($member->getUser(), $currentYear->format('Y'));
                $taken = 0;
                $open = 0;
                $noticedDates = [];
                /** @var Vacation $vacation */
                foreach ($vacations as $vacation) {
                    if ($vacation->isDeclined()) {
                        continue; // Urlaub abgelehnt
                    }
                    $mode = 'taken';
                    if (!$vacation->isApproved()) {
                        $mode = 'open';
                        $this->vacationAnalyzer->analyzeVacation($vacation);
                        $teamData['open_requests'][] = $vacation;
                    }
                    $end = clone $vacation->getEnd();
                    $end->modify('+1 day');
                    $interval = new DateInterval('P1D'); // 1 Tag
                    $period = new DatePeriod($vacation->getStart(), $interval, $end);
                    foreach ($period as $date) {
                        if ($vacation->getUser()->getWorkHoursForDay($date) > 0 && empty($noticedDates[$date->getTimestamp()]) && !$this->publicHoliday->isPublicHoliday($date)) {
                            $$mode++;
                            $noticedDates[$date->getTimestamp()] = $date;
                        }
                    }
                }
                $teamData['members'][] = [
                    'user' => $member->getUser(),
                    'vacations' => $vacations,
                    'open' => $open,
                    'taken' => $taken,
                ];
            }
            $teamOverview['teams'][] = $teamData;
        }

        return $teamOverview;
    }

    protected function getMaxDaysNumber(\DateTime $date): int
    {
        $max = 31;
        for ($i = 1; $i < 13; $i++) {
            $currentDate = new \DateTime($date->format('Y') . '-' . $i . '-01');
            $daysInMonth = (int)$currentDate->format('t');
            $startDay = (int)$currentDate->format('N');
            $dayAmount = $daysInMonth + $startDay - 1;
            if ($dayAmount > $max) {
                $max = $dayAmount;
            }
        }

        return $max - 1;
    }
}