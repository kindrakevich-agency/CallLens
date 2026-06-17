<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617120736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent (id UUID NOT NULL, external_id VARCHAR(120) DEFAULT NULL, name VARCHAR(120) NOT NULL, is_active BOOLEAN NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_268B9C9D9033212A ON agent (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_tenant_external ON agent (tenant_id, external_id)');
        $this->addSql('CREATE TABLE call (id UUID NOT NULL, external_id VARCHAR(190) NOT NULL, source VARCHAR(40) NOT NULL, audio_object_key VARCHAR(255) DEFAULT NULL, audio_deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, channels VARCHAR(255) NOT NULL, language VARCHAR(12) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, duration_sec INT DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, agent_id UUID DEFAULT NULL, scorecard_version_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CC8E2F3E9033212A ON call (tenant_id)');
        $this->addSql('CREATE INDEX IDX_CC8E2F3E3414710B ON call (agent_id)');
        $this->addSql('CREATE INDEX IDX_CC8E2F3EB55D4A1B ON call (scorecard_version_id)');
        $this->addSql('CREATE INDEX idx_call_tenant_status ON call (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_call_started ON call (started_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_call_tenant_external ON call (tenant_id, external_id)');
        $this->addSql('CREATE TABLE call_score (id UUID NOT NULL, overall_score DOUBLE PRECISION NOT NULL, model VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, call_id UUID NOT NULL, scorecard_version_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5073ABE950A89B2C ON call_score (call_id)');
        $this->addSql('CREATE INDEX IDX_5073ABE9B55D4A1B ON call_score (scorecard_version_id)');
        $this->addSql('CREATE TABLE criterion (id UUID NOT NULL, criterion_key VARCHAR(60) NOT NULL, title VARCHAR(160) NOT NULL, weight DOUBLE PRECISION NOT NULL, max_score INT NOT NULL, guidance TEXT DEFAULT NULL, scorecard_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7C82227150253A7D ON criterion (scorecard_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_criterion_scorecard_key ON criterion (scorecard_id, criterion_key)');
        $this->addSql('CREATE TABLE criterion_score (id UUID NOT NULL, criterion_key VARCHAR(60) NOT NULL, score DOUBLE PRECISION NOT NULL, max_score INT NOT NULL, evidence_quote TEXT DEFAULT NULL, rationale TEXT DEFAULT NULL, call_score_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D9659649448D6BEB ON criterion_score (call_score_id)');
        $this->addSql('CREATE TABLE processing_event (id UUID NOT NULL, step VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, attempt INT NOT NULL, error TEXT DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, call_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_processing_call ON processing_event (call_id)');
        $this->addSql('CREATE TABLE scorecard (id UUID NOT NULL, name VARCHAR(120) NOT NULL, version INT NOT NULL, is_default BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_scorecard_tenant ON scorecard (tenant_id)');
        $this->addSql('CREATE TABLE transcript (id UUID NOT NULL, language VARCHAR(12) NOT NULL, full_text TEXT NOT NULL, provider VARCHAR(40) NOT NULL, model VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, call_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A8F617C350A89B2C ON transcript (call_id)');
        $this->addSql('CREATE TABLE utterance (id UUID NOT NULL, speaker VARCHAR(255) NOT NULL, start_ms INT NOT NULL, end_ms INT NOT NULL, text TEXT NOT NULL, embedded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, call_id UUID NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_utterance_call ON utterance (call_id)');
        $this->addSql('CREATE INDEX idx_utterance_tenant ON utterance (tenant_id)');
        $this->addSql('CREATE TABLE webhook_endpoint (id UUID NOT NULL, signing_secret VARCHAR(80) NOT NULL, source_type VARCHAR(40) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_webhook_tenant ON webhook_endpoint (tenant_id)');
        $this->addSql('ALTER TABLE agent ADD CONSTRAINT FK_268B9C9D9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE call ADD CONSTRAINT FK_CC8E2F3E9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE call ADD CONSTRAINT FK_CC8E2F3E3414710B FOREIGN KEY (agent_id) REFERENCES agent (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE call ADD CONSTRAINT FK_CC8E2F3EB55D4A1B FOREIGN KEY (scorecard_version_id) REFERENCES scorecard (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE call_score ADD CONSTRAINT FK_5073ABE950A89B2C FOREIGN KEY (call_id) REFERENCES call (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE call_score ADD CONSTRAINT FK_5073ABE9B55D4A1B FOREIGN KEY (scorecard_version_id) REFERENCES scorecard (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE criterion ADD CONSTRAINT FK_7C82227150253A7D FOREIGN KEY (scorecard_id) REFERENCES scorecard (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE criterion_score ADD CONSTRAINT FK_D9659649448D6BEB FOREIGN KEY (call_score_id) REFERENCES call_score (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE processing_event ADD CONSTRAINT FK_45DCBFCA50A89B2C FOREIGN KEY (call_id) REFERENCES call (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE scorecard ADD CONSTRAINT FK_2CD563D29033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transcript ADD CONSTRAINT FK_A8F617C350A89B2C FOREIGN KEY (call_id) REFERENCES call (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE utterance ADD CONSTRAINT FK_5F00FDF50A89B2C FOREIGN KEY (call_id) REFERENCES call (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE utterance ADD CONSTRAINT FK_5F00FDF9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE webhook_endpoint ADD CONSTRAINT FK_3AB889539033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE agent DROP CONSTRAINT FK_268B9C9D9033212A');
        $this->addSql('ALTER TABLE call DROP CONSTRAINT FK_CC8E2F3E9033212A');
        $this->addSql('ALTER TABLE call DROP CONSTRAINT FK_CC8E2F3E3414710B');
        $this->addSql('ALTER TABLE call DROP CONSTRAINT FK_CC8E2F3EB55D4A1B');
        $this->addSql('ALTER TABLE call_score DROP CONSTRAINT FK_5073ABE950A89B2C');
        $this->addSql('ALTER TABLE call_score DROP CONSTRAINT FK_5073ABE9B55D4A1B');
        $this->addSql('ALTER TABLE criterion DROP CONSTRAINT FK_7C82227150253A7D');
        $this->addSql('ALTER TABLE criterion_score DROP CONSTRAINT FK_D9659649448D6BEB');
        $this->addSql('ALTER TABLE processing_event DROP CONSTRAINT FK_45DCBFCA50A89B2C');
        $this->addSql('ALTER TABLE scorecard DROP CONSTRAINT FK_2CD563D29033212A');
        $this->addSql('ALTER TABLE transcript DROP CONSTRAINT FK_A8F617C350A89B2C');
        $this->addSql('ALTER TABLE utterance DROP CONSTRAINT FK_5F00FDF50A89B2C');
        $this->addSql('ALTER TABLE utterance DROP CONSTRAINT FK_5F00FDF9033212A');
        $this->addSql('ALTER TABLE webhook_endpoint DROP CONSTRAINT FK_3AB889539033212A');
        $this->addSql('DROP TABLE agent');
        $this->addSql('DROP TABLE call');
        $this->addSql('DROP TABLE call_score');
        $this->addSql('DROP TABLE criterion');
        $this->addSql('DROP TABLE criterion_score');
        $this->addSql('DROP TABLE processing_event');
        $this->addSql('DROP TABLE scorecard');
        $this->addSql('DROP TABLE transcript');
        $this->addSql('DROP TABLE utterance');
        $this->addSql('DROP TABLE webhook_endpoint');
    }
}
