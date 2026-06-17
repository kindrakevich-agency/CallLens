<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Provider\ObjectStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Round-trips a test object through object storage (put → get → delete). Used as
 * an ops/health check that S3 (MinIO/Hetzner) is reachable and writable.
 */
#[AsCommand(name: 'app:storage:check', description: 'Verify object storage is reachable (put/get/delete).')]
final class StorageCheckCommand extends Command
{
    public function __construct(private readonly ObjectStorage $storage)
    {
        parent::__construct();
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = 'healthcheck/' . bin2hex(random_bytes(6)) . '.txt';
        $payload = 'calllens-storage-ok';

        try {
            $this->storage->put($key, $payload, 'text/plain');
            $read = $this->storage->get($key);
            $exists = $this->storage->exists($key);
            $this->storage->delete($key);

            if ($read !== $payload || !$exists) {
                $io->error('Storage round-trip mismatch.');

                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $io->error('Storage error: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Object storage OK (put/get/delete round-trip).');

        return Command::SUCCESS;
    }
}
