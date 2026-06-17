<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Message\TranscribeCallMessage;
use App\Infrastructure\Doctrine\Repository\CallRepository;
use App\Infrastructure\Doctrine\Repository\CallScoreRepository;
use App\Infrastructure\Doctrine\Repository\TranscriptRepository;
use App\Infrastructure\Doctrine\Repository\UtteranceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Re-runs the pipeline for an existing call (e.g. after a parser/provider fix):
 * drops its transcript, utterances and scores, resets it to `received`, and
 * re-dispatches transcription. Requires the audio to still be present.
 */
#[AsCommand(name: 'app:call:reprocess', description: 'Re-transcribe & re-score an existing call by id.')]
final class ReprocessCallCommand extends Command
{
    public function __construct(
        private readonly CallRepository $calls,
        private readonly TranscriptRepository $transcripts,
        private readonly UtteranceRepository $utterances,
        private readonly CallScoreRepository $callScores,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'The call UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (string) $input->getArgument('id');
        if (!Uuid::isValid($id)) {
            $io->error('Invalid call id.');

            return Command::INVALID;
        }

        $call = $this->calls->get(Uuid::fromString($id));
        if ($call === null) {
            $io->error('Call not found.');

            return Command::FAILURE;
        }
        if (!$call->isAudioAvailable()) {
            $io->error('Audio is no longer stored (retention deleted it) — cannot reprocess.');

            return Command::FAILURE;
        }

        // Drop derived data (criterion scores cascade from the call score).
        if ($transcript = $this->transcripts->findForCall($call)) {
            $this->em->remove($transcript);
        }
        foreach ($this->utterances->findForCall($call) as $utterance) {
            $this->em->remove($utterance);
        }
        if ($score = $this->callScores->findForCall($call)) {
            $this->em->remove($score);
        }
        $call->setStatus('received');
        $this->em->flush();

        $this->bus->dispatch(new TranscribeCallMessage((string) $call->id()));
        $io->success(sprintf('Reprocessing dispatched for call %s (%s).', $call->externalId(), $id));

        return Command::SUCCESS;
    }
}
