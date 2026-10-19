<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_team_source', columns: ['team_id', 'source_hash'])]
class ImportJob
{
    #[ORM\Id, ORM\Column(length: 32)]
    private string $id;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private Team $team;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    private ?MatchGame $matchGame = null;
    #[ORM\Column(length: 64)]
    private string $sourceHash;
    #[ORM\Column(length: 20)]
    private string $status = 'pending';
    #[ORM\Column(type: 'json')]
    private array $payload = [];
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct() { $this->id = bin2hex(random_bytes(16)); $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): string { return $this->id; }
    public function getTeam(): Team { return $this->team; }
    public function setTeam(Team $team): self { $this->team = $team; return $this; }
    public function getMatchGame(): ?MatchGame { return $this->matchGame; }
    public function setMatchGame(MatchGame $game): self { $this->matchGame = $game; return $this; }
    public function getSourceHash(): string { return $this->sourceHash; }
    public function setSourceHash(string $hash): self { $this->sourceHash = $hash; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getPayload(): array { return $this->payload; }
    public function setPayload(array $payload): self { $this->payload = $payload; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
