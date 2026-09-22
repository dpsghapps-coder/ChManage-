<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records security-relevant events (logins, role and permission changes, transfers ...).
 * Never throws: an audit failure must not break the action being audited.
 */
class Audit
{
    /** @param array<string, mixed> $properties */
    public static function record(string $event, string $description, ?Model $subject = null, array $properties = [], ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId ?? auth()->id(),
                'event' => $event,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'properties' => $properties ?: null,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
