<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Configuration\SystemConfiguration;
use App\Entity\TimesheetMeta;
use App\Event\TimesheetCreatePostEvent;
use App\Event\TimesheetDeletePreEvent;
use App\Event\TimesheetMetaDefinitionEvent;
use App\Event\TimesheetUpdatePostEvent;
use Doctrine\ORM\EntityManagerInterface;
use http\Client;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TimesheetSubscriber implements EventSubscriberInterface
{

    public function __construct(private SystemConfiguration $systemConfiguration, private HttpClientInterface $client, private EntityManagerInterface $entityManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TimesheetMetaDefinitionEvent::class => ['loadMeta', 200],
            TimesheetCreatePostEvent::class => ['addJiraWorklog', 200],
            TimesheetUpdatePostEvent::class => ['updateJiraWorklog', 200],
//            TimesheetDeletePreEvent::class => ['deleteJiraWorklog', 200],
        ];
    }

    public function loadMeta(TimesheetMetaDefinitionEvent $event)
    {
        $ticket = (new TimesheetMeta())
            ->setName('ticket')
            ->setLabel('Ticket')
            ->setType(TextType::class)
            ->setIsVisible(true);

        $event->getEntity()->setMetaField($ticket);

        $worklogId = (new TimesheetMeta())
            ->setName('worklog_id')
            ->setLabel('Worklog ID')
            ->setType(HiddenType::class)
            ->setIsVisible(true);

        $event->getEntity()->setMetaField($worklogId);
    }

//    public function deleteJiraWorklog(TimesheetDeletePreEvent $event)
//    {
//        $worklogId = $event->getTimesheet()->getMetaField('worklog_id')?->getValue();
//        if(!empty($worklogId)) {
//            $user = $event->getTimesheet()->getUser();
//            $jiraUsername = $user->getPreference('jira_username')->getValue();
//            $jiraPassword = $user->getPreference('jira_password')->getValue();
//            $jiraUrl = $this->systemConfiguration->find('jira.url');
//            $ticket = $event->getTimesheet()->getMetaField('ticket')->getValue();
//            if (!empty($jiraUrl) && !empty($jiraUsername) && !empty($jiraPassword) && !empty($ticket)) {
//                $this->client->request('DEL', rtrim($jiraUrl, '/') . '/' . $ticket . '/worklog/' . $worklogId, [
//                    'auth_basic' => $jiraUsername . ':' . $jiraPassword,
//                    'headers' => [
//                        'Accept' => 'application/json'
//                    ]
//                ]);
//            }
//        }
//    }

    public function updateJiraWorklog(TimesheetUpdatePostEvent $event)
    {
        $worklogId = $event->getTimesheet()->getMetaField('worklog_id')?->getValue();
        if(!empty($worklogId)) {
            $user = $event->getTimesheet()->getUser();
            $jiraUsername = $user->getPreference('jira_username')->getValue();
            $jiraPassword = $user->getPreference('jira_password')->getValue();
            $jiraUrl = $this->systemConfiguration->find('jira.url');
            $ticket = $event->getTimesheet()->getMetaField('ticket')->getValue();

            if (!empty($jiraUrl) && !empty($jiraUsername) && !empty($jiraPassword) && !empty($ticket)) {
                $body = [
                    'comment' => $event->getTimesheet()->getActivity()->getName() . ': ' . $event->getTimesheet()->getDescription(),
                    'started' => preg_replace("/([\d]{2})(:)([\d]{2})$/", '$1$3', $event->getTimesheet()->getBegin()->format(DATE_RFC3339_EXTENDED)),
                    'timeSpentSeconds' => $event->getTimesheet()->getDuration()
                ];
                $this->client->request('PUT', rtrim($jiraUrl, '/') . '/' . $ticket . '/worklog/' . $worklogId, [
                    'auth_basic' => $jiraUsername . ':' . $jiraPassword,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($body)
                ]);
            }
        }
    }

    public function addJiraWorklog(TimesheetCreatePostEvent $event)
    {
        $user = $event->getTimesheet()->getUser();
        $ticket = $event->getTimesheet()->getMetaField('ticket')->getValue();
        if(!empty($ticket)) {
            $jiraUsername = $user->getPreference('jira_username')->getValue();
            $jiraPassword = $user->getPreference('jira_password')->getValue();
            $jiraUrl = $this->systemConfiguration->find('jira.url');
            if (!empty($jiraUrl) && !empty($jiraUsername) && !empty($jiraPassword)) {
                $body = [
                    'comment' => $event->getTimesheet()->getActivity()->getName() . ': ' . $event->getTimesheet()->getDescription(),
                    'started' => preg_replace("/([\d]{2})(:)([\d]{2})$/", '$1$3', $event->getTimesheet()->getBegin()->format(DATE_RFC3339_EXTENDED)),
                    'timeSpentSeconds' => $event->getTimesheet()->getDuration()
                ];
                $response = $this->client->request('POST', rtrim($jiraUrl, '/') . '/' . $ticket . '/worklog', [
                    'auth_basic' => $jiraUsername . ':' . $jiraPassword,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($body)
                ]);

                if($response->getStatusCode() < 300) {
                    $content = $response->getContent();
                    if(!empty($content) && is_string($content)) {
                        $content = @json_decode($content, true);
                    }
                    if(!empty($content['id'])) {
                        $event->getTimesheet()->getMetaField('worklog_id')->setValue($content['id']);
                        $this->entityManager->persist($event->getTimesheet()->getMetaField('worklog_id'));
                        $this->entityManager->flush();
                    }
                }
            }
        }
    }
}