<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CSRF defense-in-depth (spec §16). The JWT lives in a SameSite=Lax cookie (the
 * primary defense); this additionally rejects state-changing requests that carry
 * the auth cookie but come from a disallowed Origin — blocking the classic
 * cross-origin CSRF. Webhook ingestion (HMAC, no cookie) and pre-auth requests
 * (login/register, no cookie yet) are naturally exempt.
 */
final class CsrfOriginListener implements EventSubscriberInterface
{
    private const UNSAFE = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var string[] */
    private array $allowedOrigins;

    public function __construct(
        #[Autowire('%env(WEB_URL)%')] string $webUrl,
        #[Autowire('%env(APP_URL)%')] string $appUrl,
    ) {
        $this->allowedOrigins = array_values(array_filter(array_map(
            static fn (string $u) => rtrim(trim($u), '/'),
            [$webUrl, $appUrl],
        )));
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 16]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array($request->getMethod(), self::UNSAFE, true)) {
            return;
        }
        // Only guard cookie-authenticated requests (the CSRF-prone case).
        if (!$request->cookies->has('access_token')) {
            return;
        }

        $origin = $this->origin($request);
        // No Origin/Referer at all → rely on SameSite (non-browser clients).
        if ($origin !== null && !\in_array($origin, $this->allowedOrigins, true)) {
            throw new AccessDeniedHttpException('Cross-origin request blocked.');
        }
    }

    private function origin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && $origin !== '') {
            return rtrim($origin, '/');
        }

        $referer = $request->headers->get('Referer');
        if ($referer !== null && $referer !== '') {
            $parts = parse_url($referer);
            if (isset($parts['scheme'], $parts['host'])) {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';

                return sprintf('%s://%s%s', $parts['scheme'], $parts['host'], $port);
            }
        }

        return null;
    }
}
