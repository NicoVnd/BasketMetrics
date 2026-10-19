<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Rapports Gemini : portée, saison, modèle, prompt et empreinte des statistiques.'; }
    public function up(Schema $schema): void
    {
        $new=new Schema();
        $table=$new->createTable('ai_report');
        $table->addColumn('id','integer',['autoincrement'=>true]);
        $table->addColumn('team_id','integer');
        $table->addColumn('scope','string',['length'=>16]);
        $table->addColumn('target','integer');
        $table->addColumn('season','integer');
        $table->addColumn('model','string',['length'=>100]);
        $table->addColumn('prompt_version','string',['length'=>32]);
        $table->addColumn('data_hash','string',['length'=>64]);
        $table->addColumn('content','text');
        $table->addColumn('generated_at','datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['team_id']);
        $table->addForeignKeyConstraint('team',['team_id'],['id']);
        foreach ($new->toSql($this->connection->getDatabasePlatform()) as $sql) { $this->addSql($sql); }
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE ai_report'); }
}
