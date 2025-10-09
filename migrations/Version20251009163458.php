<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавление комментариев к полям таблиц БД
 */
final class Version20251009163458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавление комментариев к полям таблиц user, chat, message';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('COMMENT ON COLUMN chat.id IS \'Идентификатор чата\'');
        $this->addSql('COMMENT ON COLUMN chat.user_id IS \'Идентификатор пользователя\'');
        $this->addSql('COMMENT ON COLUMN chat.created_at IS \'Дата создания чата(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN chat.updated_at IS \'Дата последнего обновления чата(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN message.id IS \'Идентификатор сообщения\'');
        $this->addSql('COMMENT ON COLUMN message.chat_id IS \'Идентификатор чата\'');
        $this->addSql('COMMENT ON COLUMN message.role IS \'Роль отправителя (user/assistant)\'');
        $this->addSql('COMMENT ON COLUMN message.content IS \'Содержимое сообщения\'');
        $this->addSql('COMMENT ON COLUMN message.created_at IS \'Дата создания сообщения(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".id IS \'Идентификатор пользователя\'');
        $this->addSql('COMMENT ON COLUMN "user".login IS \'Логин пользователя\'');
        $this->addSql('COMMENT ON COLUMN "user".password IS \'Хэш пароля\'');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'Дата создания пользователя(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('COMMENT ON COLUMN message.id IS NULL');
        $this->addSql('COMMENT ON COLUMN message.chat_id IS NULL');
        $this->addSql('COMMENT ON COLUMN message.role IS NULL');
        $this->addSql('COMMENT ON COLUMN message.content IS NULL');
        $this->addSql('COMMENT ON COLUMN message.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN chat.id IS NULL');
        $this->addSql('COMMENT ON COLUMN chat.user_id IS NULL');
        $this->addSql('COMMENT ON COLUMN chat.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN chat.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".id IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".login IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".password IS NULL');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
