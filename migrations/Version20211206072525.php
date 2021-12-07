<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211206072525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE actor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, birthdate DATE DEFAULT NULL, died DATE DEFAULT NULL, born VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE director (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, birthdate DATE DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE film (id INT AUTO_INCREMENT NOT NULL, imdb_title_id VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, date_published BIGINT DEFAULT NULL, genre VARCHAR(255) DEFAULT NULL, duration SMALLINT DEFAULT NULL, production_company VARCHAR(255) DEFAULT NULL, INDEX search_idx (title, genre, production_company), INDEX find_one_idx (imdb_title_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE film_actor (film_id INT NOT NULL, actor_id INT NOT NULL, INDEX IDX_DD19A8A9567F5183 (film_id), INDEX IDX_DD19A8A910DAF24A (actor_id), PRIMARY KEY(film_id, actor_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE film_director (film_id INT NOT NULL, director_id INT NOT NULL, INDEX IDX_BC171C99567F5183 (film_id), INDEX IDX_BC171C99899FB366 (director_id), PRIMARY KEY(film_id, director_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE film_actor ADD CONSTRAINT FK_DD19A8A9567F5183 FOREIGN KEY (film_id) REFERENCES film (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE film_actor ADD CONSTRAINT FK_DD19A8A910DAF24A FOREIGN KEY (actor_id) REFERENCES actor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE film_director ADD CONSTRAINT FK_BC171C99567F5183 FOREIGN KEY (film_id) REFERENCES film (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE film_director ADD CONSTRAINT FK_BC171C99899FB366 FOREIGN KEY (director_id) REFERENCES director (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE film_actor DROP FOREIGN KEY FK_DD19A8A910DAF24A');
        $this->addSql('ALTER TABLE film_director DROP FOREIGN KEY FK_BC171C99899FB366');
        $this->addSql('ALTER TABLE film_actor DROP FOREIGN KEY FK_DD19A8A9567F5183');
        $this->addSql('ALTER TABLE film_director DROP FOREIGN KEY FK_BC171C99567F5183');
        $this->addSql('DROP TABLE actor');
        $this->addSql('DROP TABLE director');
        $this->addSql('DROP TABLE film');
        $this->addSql('DROP TABLE film_actor');
        $this->addSql('DROP TABLE film_director');
    }
}
