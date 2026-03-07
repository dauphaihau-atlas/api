<?php

declare(strict_types=1);

namespace App\Core\Domain\Entities;

use DateTimeImmutable;

class Tenant
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $slug,
        private ?array $settings = null,
        private bool $isActive = true,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function updateSlug(string $slug): void
    {
        $this->slug = $slug;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function updateSettings(?array $settings): void
    {
        $this->settings = $settings;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable;
    }
}
