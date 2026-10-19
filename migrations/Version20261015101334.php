<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015101334 extends AbstractMigration
{
    public function getDescription(): string { return 'Imports privés FFBB, licences uniques par équipe, périodes et numéros de maillot par match.'; }
    public function up(Schema $schema): void
    {
        $new=new Schema();
        $job=$new->createTable('import_job');
        $job->addColumn('id','string',['length'=>32]);
        $job->addColumn('source_hash','string',['length'=>64]);
        $job->addColumn('status','string',['length'=>20]);
        $job->addColumn('payload','json');
        $job->addColumn('created_at','datetime_immutable');
        $job->addColumn('team_id','integer');
        $job->addColumn('match_game_id','integer',['notnull'=>false]);
        $job->setPrimaryKey(['id']);
        $job->addUniqueIndex(['team_id','source_hash'],'uniq_team_source');
        $job->addIndex(['team_id'],'IDX_6FB54078296CD8AE');
        $job->addIndex(['match_game_id'],'IDX_6FB540789329866A');
        $job->addForeignKeyConstraint('team',['team_id'],['id'],[],'FK_6FB54078296CD8AE');
        $job->addForeignKeyConstraint('match_game',['match_game_id'],['id'],[],'FK_6FB540789329866A');
        $period=$new->createTable('period_score');
        $period->addColumn('id','integer',['autoincrement'=>true]);
        $period->addColumn('period_number','integer');
        $period->addColumn('team_points','integer',['notnull'=>false]);
        $period->addColumn('opponent_points','integer',['notnull'=>false]);
        $period->addColumn('match_game_id','integer');
        $period->setPrimaryKey(['id']);
        $period->addUniqueIndex(['match_game_id','period_number'],'uniq_match_period');
        $period->addIndex(['match_game_id'],'IDX_331BF349329866A');
        $period->addForeignKeyConstraint('match_game',['match_game_id'],['id'],[],'FK_331BF349329866A');
        foreach ($new->toSql($this->connection->getDatabasePlatform()) as $sql) { $this->addSql($sql); }
        $json=$this->connection->getDatabasePlatform()->getJsonTypeDeclarationSQL([]);
        // Nullable columns + independent indexes avoid rebuilding existing SQLite tables.
        $this->addSql('ALTER TABLE player ADD COLUMN license_number VARCHAR(24) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_team_license ON player (team_id, license_number)');
        $this->addSql('ALTER TABLE match_game ADD COLUMN import_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE match_game ADD COLUMN external_reference VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE match_game ADD COLUMN import_metadata '.$json.' DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_team_import_key ON match_game (team_id, import_key)');
        foreach (['jersey_number'=>'INTEGER','starter'=>'BOOLEAN','captain'=>'BOOLEAN','field_goals_made'=>'INTEGER','two_pt_inside_made'=>'INTEGER','two_pt_outside_made'=>'INTEGER'] as $column=>$type) {
            $this->addSql('ALTER TABLE player_match_stat ADD COLUMN '.$column.' '.$type.' DEFAULT NULL');
        }
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE import_job'); $this->addSql('DROP TABLE period_score');
        $this->addSql('DROP INDEX uniq_team_license'); $this->addSql('DROP INDEX uniq_team_import_key');
        $this->addSql('ALTER TABLE player DROP COLUMN license_number');
        foreach (['import_key','external_reference','import_metadata'] as $column) { $this->addSql('ALTER TABLE match_game DROP COLUMN '.$column); }
        foreach (['jersey_number','starter','captain','field_goals_made','two_pt_inside_made','two_pt_outside_made'] as $column) { $this->addSql('ALTER TABLE player_match_stat DROP COLUMN '.$column); }
    }
}
