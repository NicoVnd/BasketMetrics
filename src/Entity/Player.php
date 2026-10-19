<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_team_license', columns: ['team_id', 'license_number'])]
class Player
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank, Assert\Length(max: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank, Assert\Length(max: 80)]
    private string $lastName = '';

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 99)]
    private ?int $jerseyNumber = null;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $licenseNumber = null;

    public function getId(): ?int { return $this->id; }
    public function getLicenseNumber(): ?string { return $this->licenseNumber; }
    public function setLicenseNumber(?string $number): self { $this->licenseNumber = $number === null ? null : strtoupper(trim($number)); return $this; }
    public function getTeam(): Team { return $this->team; }
    public function setTeam(Team $team): self { $this->team = $team; return $this; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $name): self { $this->firstName = trim($name); return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $name): self { $this->lastName = trim($name); return $this; }
    public function getFullName(): string { return $this->firstName.' '.$this->lastName; }
    public function getJerseyNumber(): ?int { return $this->jerseyNumber; }
    public function setJerseyNumber(?int $number): self { $this->jerseyNumber = $number; return $this; }
}
