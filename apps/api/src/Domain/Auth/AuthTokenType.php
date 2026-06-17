<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/** Purpose of a single-use {@see AuthToken}. */
enum AuthTokenType: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
}
