<?php

namespace App\Services\Compliance;

use App\Models\CrmAuditEvent;
use Illuminate\Support\Facades\DB;

/**
 * Append-only audit trail for CRM domains. record() is the ONLY write path; it links each row into a
 * tamper-evident hash chain (hash = sha256(prev_hash + canonical payload)). verifyChain() walks the
 * chain and reports the first broken link, if any.
 *
 * This complements ClinicalAuditLogger (which logs clinical READ access to a Monolog channel) — this
 * is the queryable DB trail for CRM-domain state changes.
 */
class AuditTrail
{
    /**
     * Append an audit event. Actor + request context default from the current request when available.
     *
     * @param  array<string,mixed>|null  $before
     * @param  array<string,mixed>|null  $after
     */
    public function record(
        string $domain,
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $before = null,
        ?array $after = null,
        ?int $actorId = null,
        ?string $actorLabel = null,
    ): CrmAuditEvent {
        return DB::transaction(function () use ($domain, $action, $subjectType, $subjectId, $before, $after, $actorId, $actorLabel) {
            // Lock the tail so concurrent writers can't fork the chain.
            $prev = CrmAuditEvent::query()->orderByDesc('id')->lockForUpdate()->first();
            $prevHash = $prev?->hash;

            $actorId ??= auth()->id();
            $occurredAt = now();

            $payload = [
                'domain' => $domain,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'before' => $before,
                'after' => $after,
                'actor_id' => $actorId,
                'occurred_at' => $occurredAt->toIso8601String(),
            ];

            $hash = hash('sha256', ($prevHash ?? '') . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return CrmAuditEvent::create([
                'domain' => $domain,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'actor_id' => $actorId,
                'actor_label' => $actorLabel ?? ($actorId ? "user:{$actorId}" : 'system'),
                'before' => $before,
                'after' => $after,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'prev_hash' => $prevHash,
                'hash' => $hash,
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    /**
     * Walk the chain in order; return the id of the first row whose stored hash doesn't recompute from
     * its predecessor (i.e. tampering), or null if the whole chain is intact.
     */
    public function verifyChain(): ?int
    {
        $prevHash = null;

        foreach (CrmAuditEvent::query()->orderBy('id')->cursor() as $event) {
            $payload = [
                'domain' => $event->domain,
                'action' => $event->action,
                'subject_type' => $event->subject_type,
                'subject_id' => $event->subject_id,
                'before' => $event->before,
                'after' => $event->after,
                'actor_id' => $event->actor_id,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ];
            $expected = hash('sha256', ($prevHash ?? '') . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            if (! hash_equals($expected, (string) $event->hash) || (string) ($event->prev_hash ?? '') !== (string) ($prevHash ?? '')) {
                return $event->id;
            }
            $prevHash = $event->hash;
        }

        return null;
    }
}
