<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds baseline security headers to every API response (spec §16). Applied in
 * the app (not just Nginx) so they hold regardless of the front proxy. HSTS is
 * only sent over HTTPS. A strict CSP is applied to non-HTML responses; HTML
 * responses (e.g. the ReDoc page) may set their own CSP, which is preserved.
 */
final class SecurityHeadersListener implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.environment%')] private readonly string $env,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse', -100]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // HSTS only over HTTPS (prod behind aaPanel-managed TLS).
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Strict CSP for API payloads; leave HTML responses (which set their own) alone.
        $isHtml = str_contains((string) $headers->get('Content-Type'), 'text/html');
        if (!$isHtml && !$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        }
    }
}
