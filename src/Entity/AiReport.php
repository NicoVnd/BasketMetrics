<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AiReport
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private Team $team;
    #[ORM\Column(length: 16)]
    private string $scope;
    #[ORM\Column]
    private int $target;
    #[ORM\Column]
    private int $season;
    #[ORM\Column(length: 100)]
    private string $model;
    #[ORM\Column(length: 32)]
    private string $promptVersion;
    #[ORM\Column(length: 64)]
    private string $dataHash;
    #[ORM\Column(type: 'text')]
    private string $content;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $generatedAt;

    public function __construct(Team $team, string $scope, int $target, int $season, string $model, string $promptVersion, string $dataHash, string $content)
    {
        $this->team=$team; $this->scope=$scope; $this->target=$target; $this->season=$season;
        $this->model=$model; $this->promptVersion=$promptVersion; $this->dataHash=$dataHash;
        $this->content=$content; $this->generatedAt=new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getModel(): string { return $this->model; }
    public function getPromptVersion(): string { return $this->promptVersion; }
    public function getDataHash(): string { return $this->dataHash; }
    public function getContent(): string { return $this->content; }
    public function getGeneratedAt(): \DateTimeImmutable { return $this->generatedAt; }
}
