<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class SeasonCalendar
{
    /**
     * League-night dates: weekly from the start date, skipping weeks off.
     *
     * @param  list<string>  $weeksOff  Y-m-d
     * @return list<CarbonImmutable>
     */
    public static function dates(CarbonInterface $start, int $weeks, array $weeksOff): array
    {
        $off = array_flip($weeksOff);
        $dates = [];
        $d = CarbonImmutable::parse($start->toDateString());
        while (count($dates) < $weeks) {
            if (! isset($off[$d->toDateString()])) {
                $dates[] = $d;
            }
            $d = $d->addWeek();
        }

        return $dates;
    }

    /** Weekly dates from the start, to offer as "no league night" choices @return list<CarbonImmutable> */
    public static function weekly(CarbonInterface $start, int $count): array
    {
        $d = CarbonImmutable::parse($start->toDateString());

        return array_map(fn ($i) => $d->addWeeks($i), range(0, $count - 1));
    }
}
