<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestSendMessageRouteTest extends TestCase
{
    public function test_test_send_message_route_is_not_available_outside_local_environment(): void
    {
        $this->get('/test/send-message')->assertNotFound();
    }
}
