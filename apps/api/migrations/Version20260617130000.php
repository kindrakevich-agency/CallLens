<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Single-use auth tokens backing email verification and password reset (spec §11).
 */
final class Version20260617130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auth_token table (email verification + password reset)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE auth_token (id UUID NOT NULL, user_id UUID NOT NULL, type VARCHAR(255) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9315F04EA76ED395 ON auth_token (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_auth_token_hash ON auth_token (token_hash)');
        $this->addSql('CREATE INDEX idx_auth_token_user_type ON auth_token (user_id, type)');
        $this->addSql('ALTER TABLE auth_token ADD CONSTRAINT FK_9315F04EA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE auth_token DROP CONSTRAINT FK_9315F04EA76ED395');
        $this->addSql('DROP TABLE auth_token');
    }
}
