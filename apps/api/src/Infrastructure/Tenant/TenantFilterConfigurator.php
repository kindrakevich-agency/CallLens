<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\User\User;
use App\Infrastructure\Doctrine\Filter\TenantFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * After the firewall has authenticated the request, resolve the active tenant
 * from the principal and enable the Doctrine tenant filter for it. Runs at a
 * priority below the firewall so the auth/user-provider query is never scoped.
 */
final class TenantFilterConfigurator implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Firewall listens at priority 8; run after it so the user is resolved.
        return [KernelEvents::REQUEST => ['onKernelRequest', 6]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $tenantId = $user->tenant()->id();
        $this->tenantContext->setTenantId($tenantId);

        $filters = $this->em->getFilters();
        $filter = $filters->isEnabled('tenant')
            ? $filters->getFilter('tenant')
            : $filters->enable('tenant');

        \assert($filter instanceof TenantFilter);
        $filter->setParameter('tenant_id', (string) $tenantId);
    }
}
