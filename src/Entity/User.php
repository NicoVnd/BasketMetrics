<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['canonicalUsername'], errorPath: 'username', message: 'Cet identifiant est indisponible.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank(message: 'Choisissez un identifiant.')]
    #[Assert\Length(min: 3, max: 32)]
    #[Assert\Regex(pattern: '/\A[A-Za-z0-9_]+\z/D', message: 'Utilisez des lettres, chiffres ou underscores.')]
    private string $username = '';

    #[ORM\Column(length: 32, unique: true)]
    private string $canonicalUsername = '';

    #[ORM\Column(length: 255)]
    private string $password = '';

    #[ORM\Column]
    private bool $enabled = true;

    public function getId(): ?int { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): self
    {
        $this->username = trim($username);
        $this->canonicalUsername = strtolower($this->username);
        return $this;
    }
    public function getCanonicalUsername(): string { return $this->canonicalUsername; }
    public function getUserIdentifier(): string { return $this->canonicalUsername; }
    public function getRoles(): array { return ['ROLE_USER']; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    #[\Deprecated]
    public function eraseCredentials(): void {}

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
        return $data;
    }
}
