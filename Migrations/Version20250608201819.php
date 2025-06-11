<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\RPDBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * @version 2.x
 */
final class Version20250608201819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE kimai2_vacation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, approved_by_id INT NOT NULL, start DATE NOT NULL, end DATE NOT NULL, approved TINYINT(1) NOT NULL, approved_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FF367ED7A76ED395 (user_id), INDEX IDX_FF367ED72D234F6A (approved_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE kimai2_vacation ADD CONSTRAINT FK_FF367ED7A76ED395 FOREIGN KEY (user_id) REFERENCES kimai2_users (id)');
        $this->addSql('ALTER TABLE kimai2_vacation ADD CONSTRAINT FK_FF367ED72D234F6A FOREIGN KEY (approved_by_id) REFERENCES kimai2_users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kimai2_vacation DROP FOREIGN KEY FK_FF367ED7A76ED395');
        $this->addSql('ALTER TABLE kimai2_vacation DROP FOREIGN KEY FK_FF367ED72D234F6A');
        $this->addSql(' DROP TABLE kimai2_vacation');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
