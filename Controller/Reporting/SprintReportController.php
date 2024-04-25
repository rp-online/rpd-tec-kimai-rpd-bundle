<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\RPDBundle\Controller\Reporting;

use App\Controller\AbstractController;
use KimaiPlugin\RPDBundle\Reporting\SprintReport\SprintReportForm;
use KimaiPlugin\RPDBundle\Reporting\SprintReport\SprintReportQuery;
use KimaiPlugin\RPDBundle\Service\SprintReportService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reporting')]
#[IsGranted('report:other')]
class SprintReportController extends AbstractController
{
    #[Route(path: '/sprint', name: 'report_sprint', methods: ['GET', 'POST'])]
    public function report(Request $request, SprintReportService $reportService): Response
    {
        $query = new SprintReportQuery();
        $form = $this->createFormForGetRequest(SprintReportForm::class, $query);
        $form->submit($request->query->all(), false);

        return $this->render('@RPD/reporting/report_sprint.twig', [
            'report_title' => 'Sprintauswertung',
            'form' => $form,
            ...$reportService->getSprintReportData($query)
        ]);
    }
}
