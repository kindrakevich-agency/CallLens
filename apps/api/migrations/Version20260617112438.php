<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617112438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id UUID NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, google_id VARCHAR(64) DEFAULT NULL, name VARCHAR(120) NOT NULL, role VARCHAR(255) DEFAULT \'viewer\' NOT NULL, email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_user_tenant ON app_user (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON app_user (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_google_id ON app_user (google_id)');
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, action VARCHAR(80) NOT NULL, target VARCHAR(180) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID DEFAULT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F6E1C0F59033212A ON audit_log (tenant_id)');
        $this->addSql('CREATE INDEX IDX_F6E1C0F5A76ED395 ON audit_log (user_id)');
        $this->addSql('CREATE INDEX idx_audit_tenant_created ON audit_log (tenant_id, created_at)');
        $this->addSql('CREATE TABLE tenant (id UUID NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, settings JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4E59C462989D9B62 ON tenant (slug)');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E99033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F59033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP CONSTRAINT FK_88BDF3E99033212A');
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F59033212A');
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F5A76ED395');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE tenant');
    }
}
