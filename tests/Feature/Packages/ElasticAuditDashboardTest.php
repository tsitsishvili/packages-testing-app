<?php

declare(strict_types=1);

namespace Tests\Feature\Packages;

use Tsitsishvili\ElasticAudit\Dashboard\Dashboard;

/**
 * Covers the elastic-audit log dashboards as mounted in this app (under the
 * /logger prefix) and the authorization gate that guards them.
 */
class ElasticAuditDashboardTest extends ElasticAuditTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Testing env is not "local", so the dashboard's default gate would deny;
        // open it explicitly for the rendering tests.
        Dashboard::auth(fn () => true);
    }

    protected function tearDown(): void
    {
        Dashboard::auth(null);

        parent::tearDown();
    }

    public function test_http_log_dashboard_renders(): void
    {
        $this->get(route('http-logs.overview'))->assertOk();
        $this->get(route('http-logs.logs.index'))->assertOk();
    }

    public function test_activity_dashboard_renders(): void
    {
        $this->get(route('activity-logs.overview'))->assertOk();
        $this->get(route('activity-logs.logs.index'))->assertOk();
    }

    public function test_dashboard_is_forbidden_without_authorization(): void
    {
        Dashboard::auth(fn () => false);

        $this->get(route('http-logs.overview'))->assertForbidden();
        $this->get(route('activity-logs.overview'))->assertForbidden();
    }
}
