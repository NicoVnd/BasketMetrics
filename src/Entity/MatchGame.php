<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_team_import_key', columns: ['team_id', 'import_key'])]
class MatchGame
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private string $opponent = '';

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $playedAt = null;

    #[ORM\Column]
    private bool $home = true;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['scheduled', 'finished'])]
    private string $status = 'scheduled';

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 1000)]
    private ?int $teamScore = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 1000)]
    private ?int $opponentScore = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $importKey = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $importMetadata = null;

    public function getId(): ?int { return $this->id; }
    public function getImportKey(): ?string { return $this->importKey; }
    public function setImportKey(?string $key): self { $this->importKey = $key; return $this; }
    public function getExternalReference(): ?string { return $this->externalReference; }
    public function setExternalReference(?string $reference): self { $this->externalReference = $reference; return $this; }
    public function getImportMetadata(): ?array { return $this->importMetadata; }
    public function setImportMetadata(?array $metadata): self { $this->importMetadata = $metadata; return $this; }
    public function getTeam(): Team { return $this->team; }
    public function setTeam(Team $team): self { $this->team = $team; return $this; }
    public function getOpponent(): string { return $this->opponent; }
    public function setOpponent(string $opponent): self { $this->opponent = trim($opponent); return $this; }
    public function getPlayedAt(): ?\DateTimeImmutable { return $this->playedAt; }
    public function setPlayedAt(?\DateTimeImmutable $date): self { $this->playedAt = $date; return $this; }
    public function isHome(): bool { return $this->home; }
    public function setHome(bool $home): self { $this->home = $home; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(?string $status): self { $this->status = $status ?? ''; return $this; }
    public function getTeamScore(): ?int { return $this->teamScore; }
    public function setTeamScore(?int $score): self { $this->teamScore = $score; return $this; }
    public function getOpponentScore(): ?int { return $this->opponentScore; }
    public function setOpponentScore(?int $score): self { $this->opponentScore = $score; return $this; }

    #[Assert\Callback]
    public function validateScore(ExecutionContextInterface $context): void
    {
        if ($this->status === 'finished') {
            foreach (['teamScore', 'opponentScore'] as $field) {
                if ($this->$field === null) {
                    $context->buildViolation('Renseignez le score d’un match terminé.')->atPath($field)->addViolation();
                }
            }
        }
    }
}
