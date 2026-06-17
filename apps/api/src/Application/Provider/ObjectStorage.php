<?php

declare(strict_types=1);

namespace App\Application\Provider;

/**
 * Audio object storage port (spec §10). Backed by Hetzner Object Storage in prod
 * and MinIO in dev (both S3-compatible). Keys are tenant-namespaced.
 */
interface ObjectStorage
{
    public function put(string $key, string $contents, string $contentType = 'application/octet-stream'): void;

    public function get(string $key): string;

    public function exists(string $key): bool;

    /** Idempotent — tolerant of an already-missing object. */
    public function delete(string $key): void;

    /** A time-limited download URL for the cabinet audio player. */
    public function presignedUrl(string $key, int $ttlSeconds = 600): string;
}
