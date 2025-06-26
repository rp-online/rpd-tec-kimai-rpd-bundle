<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Configuration\SystemConfiguration;
use App\Entity\TimesheetMeta;
use App\Event\TimesheetCreatePostEvent;
use App\Event\TimesheetMetaDefinitionEvent;
use App\Event\TimesheetStopPostEvent;
use App\Event\TimesheetUpdatePostEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TimesheetSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SystemConfiguration $systemConfiguration,
        private HttpClientInterface $client,
        private EntityManagerInterface $entityManager
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            TimesheetMetaDefinitionEvent::class => ['loadMeta', 200],
            TimesheetCreatePostEvent::class => ['addJiraWorklog', 200],
            TimesheetUpdatePostEvent::class => ['updateJiraWorklog', 200],
            TimesheetStopPostEvent::class => ['updateJiraWorklog', 200],
        ];
    }

    public function loadMeta(TimesheetMetaDefinitionEvent $event): void
    {
        $ticket = (new TimesheetMeta())->setName('ticket')->setLabel('Ticket')->setType(TextType::class)->setIsVisible(true);

        $event->getEntity()->setMetaField($ticket);

        $worklogId = (new TimesheetMeta())->setName('worklog_id')->setLabel('Worklog ID')->setType(HiddenType::class)->setIsVisible(true);

        $event->getEntity()->setMetaField($worklogId);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function updateJiraWorklog(TimesheetUpdatePostEvent|TimesheetStopPostEvent $event): void
    {
        $worklogId = $event->getTimesheet()->getMetaField('worklog_id')?->getValue();
        $user = $event->getTimesheet()->getUser();
        $jiraUsername = $user?->getPreference('jira_username')?->getValue();
        $jiraPassword = $user?->getPreference('jira_password')?->getValue();
        $jiraUrl = $this->systemConfiguration->find('jira.url');
        $ticket = $event->getTimesheet()->getMetaField('ticket')?->getValue();
        if(!empty($ticket)) {
            if (!empty($worklogId)) {
                $this->updateWorklog($jiraUrl, $jiraUsername, $jiraPassword, $ticket, $event, $worklogId);
            } else {
                $this->createWorklog($jiraUrl, $jiraUsername, $jiraPassword, $event, $ticket);
            }
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function addJiraWorklog(TimesheetCreatePostEvent $event): void
    {
        $user = $event->getTimesheet()->getUser();
        $ticket = $event->getTimesheet()->getMetaField('ticket')?->getValue();
        if (!empty($ticket)) {
            $jiraUsername = $user?->getPreference('jira_username')?->getValue();
            $jiraPassword = $user?->getPreference('jira_password')?->getValue();
            $jiraUrl = $this->systemConfiguration->find('jira.url');
            $this->createWorklog($jiraUrl, $jiraUsername, $jiraPassword, $event, $ticket);
        }
    }

    public function updateWorklog(string $jiraUrl, string $jiraUsername, string $jiraPassword, string $ticket, TimesheetUpdatePostEvent $event, int $worklogId): void
    {
        if (!empty($jiraUrl) && !empty($jiraUsername) && !empty($jiraPassword) && !empty($ticket)) {
            $body = [
                'comment' => $event->getTimesheet()->getActivity()?->getName() . ': ' . $event->getTimesheet()->getDescription(),
                'started' => preg_replace("/([\d]{2})(:)([\d]{2})$/", '$1$3', (string) $event->getTimesheet()->getBegin()?->format(DATE_RFC3339_EXTENDED)),
                'timeSpentSeconds' => $event->getTimesheet()->getDuration()
            ];
            $this->client->request('PUT', rtrim((string) $jiraUrl, '/') . '/' . $ticket . '/worklog/' . $worklogId, [
                'auth_basic' => $jiraUsername . ':' . $jiraPassword,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($body)
            ]);
        }
    }

    public function createWorklog(string $jiraUrl, string $jiraUsername, string $jiraPassword, TimesheetCreatePostEvent|TimesheetUpdatePostEvent|TimesheetStopPostEvent $event, string $ticket): void
    {
        if (!empty($jiraUrl) && !empty($jiraUsername) && !empty($jiraPassword) && !empty($event->getTimesheet()->getEnd())) {
            $body = [
                'comment' => $event->getTimesheet()->getActivity()?->getName() . ': ' . $event->getTimesheet()->getDescription(),
                'started' => preg_replace("/([\d]{2})(:)([\d]{2})$/", '$1$3', (string) $event->getTimesheet()->getBegin()?->format(DATE_RFC3339_EXTENDED)),
                'timeSpentSeconds' => $event->getTimesheet()->getDuration()
            ];
            $response = $this->client->request('POST', rtrim((string) $jiraUrl, '/') . '/' . $ticket . '/worklog', [
                'auth_basic' => $jiraUsername . ':' . $jiraPassword,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($body)
            ]);

            if ($response->getStatusCode() < 300) {
                $content = $response->getContent();
                $contentArray = [];
                if (!empty($content)) {
                    $contentArray = @json_decode($content, true);
                }
                if (!empty($contentArray['id'])) {
                    $event->getTimesheet()->getMetaField('worklog_id')?->setValue($contentArray['id']);
                    $this->entityManager->persist((object) $event->getTimesheet()->getMetaField('worklog_id'));
                    $this->entityManager->flush();
                }
            }
        }
    }
}
