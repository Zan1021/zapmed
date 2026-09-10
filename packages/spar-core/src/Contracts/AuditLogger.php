<?php

namespace Zapmed\SparCore\Contracts;

/**
 * POPIA audit logging seam for SPAR (spec FR-5.2). Both hosts satisfy the same
 * contract; ZapMed binds it to the `spar_audit` log channel.
 */
interface AuditLogger
{
    /**
     * Record an audited SPAR event.
     *
     * @param  string  $event    machine event key (e.g. 'consent_granted')
     * @param  string  $message  human-readable description
     * @param  array   $context  structured context (ids, actor, ip, ...)
     */
    public function log(string $event, string $message, array $context = []): void;
}
