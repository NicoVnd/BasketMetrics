<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015093711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Équipes privées, joueurs, matchs et statistiques individuelles uniques par match.';
    }

    public function up(Schema $schema): void
    {
        // Générer le SQL avec DBAL pour SQLite local et PostgreSQL/Supabase.
        $new = new Schema();
        $team = $new->createTable('team');
        $team->addColumn('id', 'integer', ['autoincrement' => true]);
        $team->addColumn('name', 'string', ['length' => 100]);
        $team->addColumn('category', 'string', ['length' => 60, 'notnull' => false]);
        $team->addColumn('owner_id', 'integer');
        $team->setPrimaryKey(['id']);
        $team->addIndex(['owner_id'], 'IDX_C4E0A61F7E3C61F9');
        $team->addForeignKeyConstraint('app_user', ['owner_id'], ['id'], [], 'FK_C4E0A61F7E3C61F9');

        $player = $new->createTable('player');
        $player->addColumn('id', 'integer', ['autoincrement' => true]);
        $player->addColumn('first_name', 'string', ['length' => 80]);
        $player->addColumn('last_name', 'string', ['length' => 80]);
        $player->addColumn('jersey_number', 'integer', ['notnull' => false]);
        $player->addColumn('team_id', 'integer');
        $player->setPrimaryKey(['id']);
        $player->addIndex(['team_id'], 'IDX_98197A65296CD8AE');
        $player->addForeignKeyConstraint('team', ['team_id'], ['id'], [], 'FK_98197A65296CD8AE');

        $game = $new->createTable('match_game');
        $game->addColumn('id', 'integer', ['autoincrement' => true]);
        $game->addColumn('opponent', 'string', ['length' => 100]);
        $game->addColumn('played_at', 'datetime_immutable');
        $game->addColumn('home', 'boolean');
        $game->addColumn('status', 'string', ['length' => 20]);
        $game->addColumn('team_score', 'integer', ['notnull' => false]);
        $game->addColumn('opponent_score', 'integer', ['notnull' => false]);
        $game->addColumn('team_id', 'integer');
        $game->setPrimaryKey(['id']);
        $game->addIndex(['team_id'], 'IDX_424480FE296CD8AE');
        $game->addForeignKeyConstraint('team', ['team_id'], ['id'], [], 'FK_424480FE296CD8AE');

        $stat = $new->createTable('player_match_stat');
        $stat->addColumn('id', 'integer', ['autoincrement' => true]);
        foreach (['points', 'seconds_played', 'fouls', 'two_pt_made', 'three_pt_made', 'free_throws_made'] as $column) {
            $stat->addColumn($column, 'integer', ['notnull' => false]);
        }
        $stat->addColumn('match_game_id', 'integer');
        $stat->addColumn('player_id', 'integer');
        $stat->setPrimaryKey(['id']);
        $stat->addUniqueIndex(['match_game_id', 'player_id'], 'uniq_game_player');
        $stat->addIndex(['match_game_id'], 'IDX_1E72CA6E9329866A');
        $stat->addIndex(['player_id'], 'IDX_1E72CA6E99E6F5DF');
        $stat->addForeignKeyConstraint('match_game', ['match_game_id'], ['id'], [], 'FK_1E72CA6E9329866A');
        $stat->addForeignKeyConstraint('player', ['player_id'], ['id'], [], 'FK_1E72CA6E99E6F5DF');

        foreach ($new->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE player_match_stat');
        $this->addSql('DROP TABLE match_game');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE team');
    }
}
