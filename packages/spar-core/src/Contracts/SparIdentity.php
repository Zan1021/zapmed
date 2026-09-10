<?php

namespace Zapmed\SparCore\Contracts;

/**
 * Immutable read-model of a SPAR actor's identity (spec FR-2).
 *
 * Lets SPAR code and views read name/contact WITHOUT touching the ZapMed
 * `User` model directly — so the same code works standalone (identity comes
 * from SPAR-owned records) and integrated (identity comes from `User`).
 */
final class SparIdentity
{
    public function __construct(
        public readonly ?int $userId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $phone,
        public readonly ?string $email,
    ) {
    }

    public function fullName(): string
    {
        $name = trim($this->firstName . ' ' . $this->lastName);

        return $name !== '' ? $name : 'Patient';
    }

    public function hasContactChannel(): bool
    {
        return !empty($this->phone) || !empty($this->email);
    }
}
