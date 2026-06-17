<?php

declare(strict_types=1);

namespace App\Domain\User;

/**
 * Workspace roles (spec §11). Each user has exactly one; it maps to a Symfony
 * security role and participates in the role hierarchy (owner > admin > manager > viewer).
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Viewer = 'viewer';

    /** The Symfony security role string, e.g. ROLE_ADMIN. */
    public function asSecurityRole(): string
    {
        return 'ROLE_' . strtoupper($this->value);
    }
}
