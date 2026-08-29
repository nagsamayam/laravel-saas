<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_is_reachable(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertSuccessful();

        $response->assertJsonStructure([
            'status',
            'service',
            'timestamp',
            'checks' => [
                'database' => [
                    'status',
                ],
                'redis' => [
                    'status',
                ],
                'cache' => [
                    'status',
                ],
            ],
        ]);

        $response->assertJsonPath('status', 'ok');
    }
}
