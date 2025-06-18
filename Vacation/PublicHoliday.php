<?php

namespace KimaiPlugin\RPDBundle\Vacation;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;

class PublicHoliday
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    private static array $publicHolidays;

    public function load(\DateTime|\DateTimeImmutable $date): void
    {
        if(!empty(self::$publicHolidays[$date->format('Y')])) {
            return;
        }
        $file = $this->kernel->getCacheDir() . '/public_holidays_' . $date->format('Y') . '.json';
        if(!file_exists($file)) {
            $client = HttpClient::create();
            $response = $client->request('GET', 'https://feiertage-api.de/api/?jahr=' . $date->format('Y') . '&nur_land=NW');
            $content = $response->getContent();
            file_put_contents($file, $content);
        } else {
            $content = file_get_contents($file);
        }
        if($content !== false) {
            $holidays = @json_decode($content, true);

            if(is_array($holidays)) {
                foreach($holidays as $name => $holiday) {
                    if(!empty($holiday['datum'])) {
                        self::$publicHolidays[$date->format('Y')][$holiday['datum']] = $name;
                    }
                }
            }
        }
    }

    public function isPublicHoliday(\DateTime|\DateTimeImmutable $dateTime): bool
    {
        $this->load($dateTime);

        return !empty(self::$publicHolidays[$dateTime->format('Y')][$dateTime->format('Y-m-d')]);
    }

    public function getPublicHolidayLabel(\DateTime|\DateTimeImmutable $date): string
    {
        $this->load($date);

        return self::$publicHolidays[$date->format('Y')][$date->format('Y-m-d')];
    }

    public function getAll(\DateTime $dateTime): array
    {
        return self::$publicHolidays[$dateTime->format('Y')] ?? [];
    }
}