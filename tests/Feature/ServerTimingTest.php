<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * On a local machine every response says where its time went, so a slow
 * page can be read in the browser's Network > Timing. Nowhere else.
 */
class ServerTimingTest extends TestCase
{
    public function test_a_local_response_says_where_its_time_went(): void
    {
        $this->app['env'] = 'local';

        $timing = $this->get('/up')->assertOk()->headers->get('Server-Timing');

        $this->assertNotNull($timing);
        foreach (['boot;dur=', 'db;dur=', 'php;dur=', 'total;dur=', 'opcache;desc=', 'xdebug;desc='] as $part) {
            $this->assertStringContainsString($part, $timing);
        }
    }

    public function test_outside_a_local_machine_nothing_is_added(): void
    {
        $this->app['env'] = 'production';

        $this->assertFalse($this->get('/up')->assertOk()->headers->has('Server-Timing'));
    }
}
