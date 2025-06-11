<?php

namespace KimaiPlugin\RPDBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class MenuSubscriber
{
    public function __invoke(ConfigureMainMenuEvent $event): void
    {
        $mainMenuItem = new MenuItemModel('vacation', 'Urlaub', 'vacation_overview', [], 'fa-solid fa-umbrella-beach');
        $contract = $event->getMenu()->getChild('contract');
        $contract?->addChild($mainMenuItem);
    }
}