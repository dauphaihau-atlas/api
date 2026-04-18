<?php

namespace App\Core\Application\UseCases\User\ExportUsers;

use DateTimeImmutable;

class ExportUsersRequest
{
    /**
     * @param  string[]|null  $fields
     */
    public function __construct(
        public readonly int $adminId,
        public readonly ?DateTimeImmutable $dateFrom = null,
        public readonly ?DateTimeImmutable $dateTo = null,
        public readonly ?array $fields = null,
    ) {}
}
