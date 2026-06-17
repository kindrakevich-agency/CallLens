<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use App\Domain\Audit\AuditLog;
use App\Domain\User\User;
use App\Infrastructure\Doctrine\Repository\AuditLogRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Records a successful sign-in in the audit log (spec §16).
 */
final class LoginAuditListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuditLogRepository $auditLogs,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->auditLogs->save(new AuditLog(
            action: 'user.login',
            tenant: $user->tenant(),
            user: $user,
            target: $user->email(),
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
        ));
    }
}
