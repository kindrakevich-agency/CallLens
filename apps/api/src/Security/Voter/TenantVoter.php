<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Domain\Tenant\TenantOwned;
use App\Domain\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Defense-in-depth on top of the Doctrine tenant filter: a user may only act on
 * a TenantOwned record from their OWN workspace, and writes require manager+.
 *
 * @extends Voter<string, TenantOwned>
 */
final class TenantVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof TenantOwned;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof TenantOwned) {
            return false;
        }

        // Hard tenant boundary.
        if (!$subject->tenant()->id()->equals($user->tenant()->id())) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT, self::DELETE => $this->security->isGranted('ROLE_MANAGER'),
            default => false,
        };
    }
}
