<?php

namespace Tests\Unit\Support;

use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class WeekCalendarTest extends TestCase
{
    public function test_a_moment_belongs_to_the_calendar_month_of_the_owners_timezone()
    {
        $calendar = new WeekCalendar('Europe/Amsterdam');

        $month = $calendar->monthOf(CarbonImmutable::parse('2026-09-30 22:30:00', 'UTC'));

        $this->assertSame('2026-10-01 00:00:00 Europe/Amsterdam', $month->format('Y-m-d H:i:s e'));
    }

    public function test_a_calendar_day_belongs_to_the_week_of_that_day_in_the_owners_timezone()
    {
        $calendar = new WeekCalendar('America/New_York');

        $week = $calendar->weekOfDay('2026-09-27');

        $this->assertSame('2026-09-21 00:00:00 America/New_York', $week->format('Y-m-d H:i:s e'));
    }

    public function test_weeks_in_a_range_start_at_local_midnight_across_a_daylight_saving_change()
    {
        $calendar = new WeekCalendar('Europe/Amsterdam');

        $weeks = $calendar->weeksBetween(
            CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'),
            CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'),
        );

        $this->assertSame(
            ['2026-10-12 00:00 +02:00', '2026-10-19 00:00 +02:00', '2026-10-26 00:00 +01:00', '2026-11-02 00:00 +01:00'],
            array_map(fn (CarbonImmutable $week) => $week->format('Y-m-d H:i P'), $weeks),
        );
    }

    public function test_weeks_in_a_range_run_across_the_new_year()
    {
        $calendar = new WeekCalendar('Europe/Amsterdam');

        $weeks = $calendar->weeksBetween(
            CarbonImmutable::parse('2026-12-30 12:00:00', 'UTC'),
            CarbonImmutable::parse('2027-01-05 12:00:00', 'UTC'),
        );

        $this->assertSame(
            ['2026-12-28', '2027-01-04'],
            array_map(fn (CarbonImmutable $week) => $week->toDateString(), $weeks),
        );
    }
}
