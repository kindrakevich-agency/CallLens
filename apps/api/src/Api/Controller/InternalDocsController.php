<?php

declare(strict_types=1);

namespace App\Api\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Internal API documentation (spec §12.2). Renders the committed OpenAPI 3.1 spec
 * with ReDoc. Lives under /internal/* and is NOT internet-exposed in production
 * (the host firewall only opens 80/443; ops routes are network-restricted).
 */
final class InternalDocsController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    #[Route('/internal/openapi.json', name: 'internal_openapi', methods: ['GET'])]
    public function spec(): Response
    {
        $path = $this->projectDir . '/openapi.json';
        if (!is_file($path)) {
            return new JsonResponse(['error' => 'OpenAPI spec not found.'], Response::HTTP_NOT_FOUND);
        }

        return new Response((string) file_get_contents($path), Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ]);
    }

    #[Route('/internal/docs', name: 'internal_docs', methods: ['GET'])]
    public function redoc(): Response
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="utf-8" />
              <meta name="viewport" content="width=device-width, initial-scale=1" />
              <meta name="robots" content="noindex,nofollow" />
              <title>CallLens API — Reference</title>
              <style>body { margin: 0; }</style>
            </head>
            <body>
              <redoc spec-url="/internal/openapi.json" theme='{"colors":{"primary":{"main":"#0F766E"}}}'></redoc>
              <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
            </body>
            </html>
            HTML;

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }
}
