<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_health_endpoint_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_protected_routes_require_auth(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/bid-workspaces')->assertUnauthorized();
        $this->getJson('/api/ai-models')->assertUnauthorized();
    }
}
