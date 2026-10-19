<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_match_period', columns: ['match_game_id', 'period_number'])]
class PeriodScore
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private MatchGame $matchGame;
    #[ORM\Column]
    private int $periodNumber;
    #[ORM\Column(nullable: true)]
    private ?int $teamPoints = null;
    #[ORM\Column(nullable: true)]
    private ?int $opponentPoints = null;

    public function getId(): ?int { return $this->id; }
    public function getMatchGame(): MatchGame { return $this->matchGame; }
    public function setMatchGame(MatchGame $game): self { $this->matchGame = $game; return $this; }
    public function getPeriodNumber(): int { return $this->periodNumber; }
    public function setPeriodNumber(int $number): self { $this->periodNumber = $number; return $this; }
    public function getTeamPoints(): ?int { return $this->teamPoints; }
    public function setTeamPoints(?int $value): self { $this->teamPoints = $value; return $this; }
    public function getOpponentPoints(): ?int { return $this->opponentPoints; }
    public function setOpponentPoints(?int $value): self { $this->opponentPoints = $value; return $this; }
}
