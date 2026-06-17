<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Provider\ObjectStorage;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToGenerateTemporaryUrl;

/**
 * S3-backed object storage via Flysystem (AsyncAws adapter): Hetzner Object
 * Storage in prod, MinIO in dev. Keys are namespaced
 * `tenants/{tenantId}/calls/{callId}/audio.{ext}` (spec §7.2).
 */
final class FlysystemObjectStorage implements ObjectStorage
{
    public function __construct(private readonly FilesystemOperator $audioStorage)
    {
    }

    public function put(string $key, string $contents, string $contentType = 'application/octet-stream'): void
    {
        $this->audioStorage->write($key, $contents, ['ContentType' => $contentType]);
    }

    public function get(string $key): string
    {
        return $this->audioStorage->read($key);
    }

    public function exists(string $key): bool
    {
        return $this->audioStorage->fileExists($key);
    }

    public function delete(string $key): void
    {
        // Idempotent: ignore an already-missing object.
        try {
            $this->audioStorage->delete($key);
        } catch (FilesystemException) {
            // no-op
        }
    }

    public function presignedUrl(string $key, int $ttlSeconds = 600): string
    {
        try {
            return $this->audioStorage->temporaryUrl($key, new \DateTimeImmutable("+{$ttlSeconds} seconds"));
        } catch (UnableToGenerateTemporaryUrl|FilesystemException) {
            // Adapter without signed-URL support — cabinet audio streaming is M6.
            return '';
        }
    }
}
