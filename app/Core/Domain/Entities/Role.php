<?php

declare(strict_types=1);

namespace App\Core\Domain\Entities;

class Role
{
    public function __construct(
        private int $id,
        private string $name,
        private string $slug,
        private ?string $description = null,
    ) {}

    public function getId(): int
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

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
