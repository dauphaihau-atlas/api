<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\Email;
use DateTimeImmutable;

class User
{
    public function __construct(
        private ?int $id,
        private string $name,
        private Email $email,
        private ?string $password = null,
        private ?string $avatarPath = null,
        private array $roles = [],
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        private ?DateTimeImmutable $deletedAt = null
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable;
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    /**
     * @return Role[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasRole(string $slug): bool
    {
        foreach ($this->roles as $role) {
            if ($role->getSlug() === $slug) {
                return true;
            }
        }

        return false;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function updateAvatarPath(?string $path): void
    {
        $this->avatarPath = $path;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function updateEmail(Email $email): void
    {
        $this->email = $email;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function updatePassword(string $password): void
    {
        $this->password = $password;
        $this->updatedAt = new DateTimeImmutable;
    }
}
