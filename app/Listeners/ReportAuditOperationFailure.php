<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Tsitsishvili\ElasticAudit\Events\AuditOperationFailed;

/**
 * Mirrors elastic-audit's own failures onto the dedicated `audit` log channel.
 * Wired by Laravel's listener auto-discovery — the typed `handle()` argument is
 * the registration.
 *
 * The package never lets an audit failure break the surrounding request, so a
 * broken capture or indexing path is otherwise only visible in the default log
 * stream, mixed in with everything else, until the hourly
 * `elastic-audit:health` run notices it. The event payload is sanitized and
 * shallow by design — no exceptions, headers, payloads, or model changes — so
 * it is safe to log as-is.
 */
class ReportAuditOperationFailure
{
    public function handle(AuditOperationFailed $event): void
    {
        Log::channel('audit')->warning('Elastic audit operation failed', [
            'subsystem' => $event->subsystem,
            'stage' => $event->stage,
            'exception' => $event->exceptionClass,
            'message' => $event->message,
            'context' => $event->context,
        ]);
    }
}
