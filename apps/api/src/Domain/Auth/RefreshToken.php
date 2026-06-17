<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Persisted refresh token (rotating, single-use — reuse detection in spec §11).
 * Extends the gesdinet base; rotation invalidates the old token on each refresh.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_token')]
class RefreshToken extends BaseRefreshToken
{
}
