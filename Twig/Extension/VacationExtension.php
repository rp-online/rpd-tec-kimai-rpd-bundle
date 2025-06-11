<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\RPDBundle\Twig\Extension;

use KimaiPlugin\RPDBundle\Twig\Runtime\VacationExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class VacationExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
//            new TwigFilter('filter_name', [VacationExtensionRuntime::class, 'doSomething']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getVacationDuration', [VacationExtensionRuntime::class, 'getVacationDuration']),
            new TwigFunction('isPublicHoliday', [VacationExtensionRuntime::class, 'isPublicHoliday']),
            new TwigFunction('getPublicHolidayLabel', [VacationExtensionRuntime::class, 'getPublicHolidayLabel']),
        ];
    }
}
