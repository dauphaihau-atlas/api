<?php

namespace App\Core\Application\UseCases\User\ExportUsers;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Domain\Entities\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExportUsersUseCase
{
    private const ALL_FIELDS = ['id', 'name', 'email', 'roles', 'created_at'];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * @throws RuntimeException When storage write fails
     */
    public function execute(ExportUsersRequest $request): ExportUsersResponse
    {
        $diskName = config('filesystems.exports_disk', 'local');
        $disk = Storage::disk($diskName);

        $date = date('Y-m-d_His');
        $path = "exports/users-{$date}.csv";

        Log::info('ExportUsers: start', [
            'path' => $path,
            'disk' => $diskName,
            'date_from' => $request->dateFrom?->format('Y-m-d'),
            'date_to' => $request->dateTo?->format('Y-m-d'),
            'fields' => $request->fields,
        ]);

        $content = $this->buildCsvContent($request->dateFrom, $request->dateTo, $request->fields);
        $contentBytes = strlen($content);
        Log::info('ExportUsers: CSV content built', ['path' => $path, 'content_bytes' => $contentBytes]);

        $written = $disk->put($path, $content);

        if ($written === false) {
            throw new RuntimeException('Failed to write export file to storage.');
        }

        Log::info('ExportUsers: complete', ['path' => $path]);

        return new ExportUsersResponse($path, null, null);
    }

    /**
     * @param  string[]|null  $fields
     */
    private function buildCsvContent(
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
        ?array $fields,
    ): string {
        $activeFields = $fields !== null
            ? array_values(array_intersect(self::ALL_FIELDS, $fields))
            : self::ALL_FIELDS;

        $users = $this->userRepository->findAllWithDateRange($dateFrom, $dateTo);

        Log::info('ExportUsers: users loaded', ['count' => count($users)]);

        $allFieldValues = array_flip(self::ALL_FIELDS);
        $fieldIndices = array_map(fn (string $f) => $allFieldValues[$f], $activeFields);

        $rows = [$activeFields];
        foreach ($users as $user) {
            $full = $this->userToFullRow($user);
            $rows[] = array_values(array_intersect_key($full, array_flip($fieldIndices)));
        }

        $lines = array_map(function (array $row): string {
            return implode(',', array_map($this->escapeCsvField(...), $row));
        }, $rows);

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function userToFullRow(User $user): array
    {
        return [
            $user->getId() !== null ? (string) $user->getId() : '',
            $user->getName(),
            $user->getEmail()->getValue(),
            implode('|', array_map(fn ($r) => $r->getSlug(), $user->getRoles())),
            $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function escapeCsvField(string $value): string
    {
        if (str_contains($value, '"') || str_contains($value, ',') || str_contains($value, "\n") || str_contains($value, "\r")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
