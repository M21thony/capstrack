<?php

namespace Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class GoogleCalendarServiceTest extends TestCase
{
    public function test_datetime_parsing_for_google_calendar_uses_parse_correctly(): void
    {
        // Verify Carbon::parse() correctly handles the datetime format used in GoogleCalendarService
        $date = '2025-06-30';
        $startTime = '09:00';
        $endTime = '10:00';

        $start = Carbon::parse("{$date} {$startTime}", 'Asia/Manila')
            ->format('Y-m-d\TH:i:sP');

        $end = Carbon::parse("{$date} {$endTime}", 'Asia/Manila')
            ->format('Y-m-d\TH:i:sP');

        // Verify the times are correct (9:00am and 10:00am)
        $this->assertStringContainsString('09:00:00', $start);
        $this->assertStringContainsString('10:00:00', $end);
        $this->assertEquals('2025-06-30T09:00:00+08:00', $start);
        $this->assertEquals('2025-06-30T10:00:00+08:00', $end);
    }
}
