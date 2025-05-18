<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250517184827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commandes ADD montant_total NUMERIC(10, 2) DEFAULT NULL, ADD sous_total NUMERIC(10, 2) DEFAULT NULL, ADD frais_livraison NUMERIC(10, 2) DEFAULT NULL, ADD tva NUMERIC(10, 2) DEFAULT NULL, ADD methode_paiement VARCHAR(255) DEFAULT NULL, ADD date_paiement DATETIME DEFAULT NULL, ADD email VARCHAR(255) DEFAULT NULL, ADD prenom VARCHAR(255) DEFAULT NULL, ADD nom VARCHAR(255) DEFAULT NULL, ADD telephone VARCHAR(255) DEFAULT NULL, ADD adresse VARCHAR(255) DEFAULT NULL, ADD code_postal VARCHAR(255) DEFAULT NULL, ADD ville VARCHAR(255) DEFAULT NULL, ADD gouvernorat VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commandes DROP montant_total, DROP sous_total, DROP frais_livraison, DROP tva, DROP methode_paiement, DROP date_paiement, DROP email, DROP prenom, DROP nom, DROP telephone, DROP adresse, DROP code_postal, DROP ville, DROP gouvernorat');
    }
}
