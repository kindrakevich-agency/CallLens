<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617124955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pgvector embedding column + HNSW index to utterance';
    }

    public function up(Schema $schema): void
    {
        // pgvector extension (image is pgvector/pgvector:pg17) must exist first.
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql('ALTER TABLE utterance ADD embedding vector(1024) DEFAULT NULL');
        // HNSW index for fast cosine ANN search (spec §7.3).
        $this->addSql('CREATE INDEX idx_utterance_embedding_hnsw ON utterance USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_utterance_embedding_hnsw');
        $this->addSql('ALTER TABLE utterance DROP embedding');
    }
}
