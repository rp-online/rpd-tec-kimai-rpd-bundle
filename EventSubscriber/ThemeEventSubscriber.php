<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Event\ThemeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ThemeEventSubscriber implements EventSubscriberInterface
{


    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeEvent::STYLESHEET => ['renderStylesheet', 100],
        ];
    }

    public function renderStylesheet(ThemeEvent $event)
    {
        $path = $this->kernel->getProjectDir() . '/var/plugins/RPDBundle/Resources/assets/css/rpd.css';
        $css = '<style type="text/css">' . @file_get_contents($path) . '</style>';
        $event->addContent($css);
    }
}