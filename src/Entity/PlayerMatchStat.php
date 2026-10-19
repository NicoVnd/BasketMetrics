<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_game_player', columns: ['match_game_id', 'player_id'])]
class PlayerMatchStat
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private MatchGame $matchGame;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private Player $player;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 1000)]
    private ?int $points = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 86400)]
    private ?int $secondsPlayed = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 5)]
    private ?int $fouls = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 500)]
    private ?int $twoPtMade = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 333)]
    private ?int $threePtMade = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 1000)]
    private ?int $freeThrowsMade = null;

    #[ORM\Column(nullable: true)]
    private ?int $jerseyNumber = null;

    #[ORM\Column(nullable: true)]
    private ?bool $starter = null;

    #[ORM\Column(nullable: true)]
    private ?bool $captain = null;

    #[ORM\Column(nullable: true)]
    private ?int $fieldGoalsMade = null;

    #[ORM\Column(nullable: true)]
    private ?int $twoPtInsideMade = null;

    #[ORM\Column(nullable: true)]
    private ?int $twoPtOutsideMade = null;

    #[ORM\Column(length: 16, options: ['default'=>'unknown'])]
    #[Assert\Choice(choices: ['unknown','played','did_not_play'])]
    private string $participation = 'unknown';

    public function getParticipation(): string { return $this->participation; }
    public function setParticipation(?string $value): self { $this->participation=$value??'unknown'; return $this; }
    public function getEffectiveParticipation(): string
    {
        if ($this->participation !== 'unknown') { return $this->participation; }
        if (($this->secondsPlayed??0)>0 || $this->hasPlayingEvidence()) { return 'played'; }
        return $this->secondsPlayed===0?'did_not_play':'unknown';
    }

    private function hasPlayingEvidence(): bool
    {
        return $this->starter===true || ($this->points??0)>0 || ($this->twoPtMade??0)>0
            || ($this->threePtMade??0)>0 || ($this->freeThrowsMade??0)>0 || ($this->fieldGoalsMade??0)>0;
    }

    public function getId(): ?int { return $this->id; }
    public function getJerseyNumber(): ?int { return $this->jerseyNumber; }
    public function setJerseyNumber(?int $value): self { $this->jerseyNumber = $value; return $this; }
    public function isStarter(): ?bool { return $this->starter; }
    public function setStarter(?bool $value): self { $this->starter = $value; return $this; }
    public function isCaptain(): ?bool { return $this->captain; }
    public function setCaptain(?bool $value): self { $this->captain = $value; return $this; }
    public function getFieldGoalsMade(): ?int { return $this->fieldGoalsMade; }
    public function setFieldGoalsMade(?int $value): self { $this->fieldGoalsMade = $value; return $this; }
    public function getTwoPtInsideMade(): ?int { return $this->twoPtInsideMade; }
    public function setTwoPtInsideMade(?int $value): self { $this->twoPtInsideMade = $value; return $this; }
    public function getTwoPtOutsideMade(): ?int { return $this->twoPtOutsideMade; }
    public function setTwoPtOutsideMade(?int $value): self { $this->twoPtOutsideMade = $value; return $this; }
    public function getMatchGame(): MatchGame { return $this->matchGame; }
    public function setMatchGame(MatchGame $game): self { $this->matchGame = $game; return $this; }
    public function getPlayer(): Player { return $this->player; }
    public function setPlayer(Player $player): self { $this->player = $player; return $this; }
    public function getPoints(): ?int { return $this->points; }
    public function setPoints(?int $value): self { $this->points = $value; return $this; }
    public function getSecondsPlayed(): ?int { return $this->secondsPlayed; }
    public function setSecondsPlayed(?int $value): self { $this->secondsPlayed = $value; return $this; }
    public function getFouls(): ?int { return $this->fouls; }
    public function setFouls(?int $value): self { $this->fouls = $value; return $this; }
    public function getTwoPtMade(): ?int { return $this->twoPtMade; }
    public function setTwoPtMade(?int $value): self { $this->twoPtMade = $value; return $this; }
    public function getThreePtMade(): ?int { return $this->threePtMade; }
    public function setThreePtMade(?int $value): self { $this->threePtMade = $value; return $this; }
    public function getFreeThrowsMade(): ?int { return $this->freeThrowsMade; }
    public function setFreeThrowsMade(?int $value): self { $this->freeThrowsMade = $value; return $this; }

    public function getWarnings(): array
    {
        $warnings=[];
        if ($this->points !== null && $this->twoPtMade !== null && $this->threePtMade !== null && $this->freeThrowsMade !== null
            && $this->points !== 2 * $this->twoPtMade + 3 * $this->threePtMade + $this->freeThrowsMade) {
            $warnings[]=$this->player->getFullName().' : les points ne correspondent pas aux paniers renseignés.';
        }
        if ($this->twoPtMade!==null && $this->twoPtInsideMade!==null && $this->twoPtOutsideMade!==null && $this->twoPtMade!==$this->twoPtInsideMade+$this->twoPtOutsideMade) {
            $warnings[]=$this->player->getFullName().' : le détail intérieur/extérieur importé ne correspond plus au total à 2 points.';
        }
        if ($this->fieldGoalsMade!==null && $this->twoPtMade!==null && $this->threePtMade!==null && $this->fieldGoalsMade!==$this->twoPtMade+$this->threePtMade) {
            $warnings[]=$this->player->getFullName().' : le nombre de paniers importé ne correspond pas au total des 2 et 3 points.';
        }
        return $warnings;
    }

    #[Assert\Callback]
    public function validateTeam(ExecutionContextInterface $context): void
    {
        if ($this->participation==='did_not_play' && (($this->secondsPlayed??0)>0 || $this->hasPlayingEvidence())) {
            $context->buildViolation('Un joueur déclaré non entré en jeu ne peut pas avoir un temps positif, des points, des paniers ou être titulaire.')->atPath('participation')->addViolation();
        }
        if (isset($this->matchGame, $this->player) && $this->matchGame->getTeam() !== $this->player->getTeam()) {
            $context->buildViolation('Ce joueur ne fait pas partie de l’équipe du match.')->addViolation();
        }
    }
}
