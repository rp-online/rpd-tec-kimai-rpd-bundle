<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UserPreferenceSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserPreferenceEvent::class => ['loadUserPreferences', 200],
        ];
    }

    public function loadUserPreferences(UserPreferenceEvent $event): void
    {
        $event->addPreference(
            (new UserPreference('jira_username', ''))
                ->setOrder(900)
                ->setType(TextType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Jira username for jira connection', 'label' => 'Jira: Username'])
                ->setSection('jira')
        );
        $event->addPreference(
            (new UserPreference('jira_password', ''))
                ->setOrder(900)
                ->setType(TextType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Jira API Key for jira connection. You can manage the api keys here: https://id.atlassian.com/manage-profile/security/api-tokens', 'label' => 'Jira: API Key'])
                ->setSection('jira')
        );
        $event->addPreference(
            (new UserPreference('team', 'Tech & Services'))
            ->setOrder(100)
            ->setType(ChoiceType::class)
            ->setEnabled(true)
            ->setOptions([
                'choices' => [
                    'Tech & Services' => 'Tech & Services'
                ],
                'label' => 'Teamzugehörigkeit',
                'help' => 'Wähle deine Teamzugehörigkeit aus. DIes ist relevant für die Urlaubsanträge und die Abwesenheitsplanung.',
            ])
            ->setSection('general')
        );
        $event->addPreference(
            (new UserPreference('cost_center', ))
            ->setOrder(100)
            ->setType(TextType::class)
            ->setEnabled(true)
            ->setOptions([
                'label' => 'Kostenstelle',
                'help' => 'Gib deine Kostenstelle an. Im Zweifel kannst du diese bei deinem Vorgesetzten erfragen.',
            ])
            ->setSection('general')
        );
    }
}