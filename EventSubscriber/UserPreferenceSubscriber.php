<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
                ->setType(PasswordType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Jira password for jira connection', 'label' => 'Jira: Password'])
                ->setSection('jira')
        );
    }
}