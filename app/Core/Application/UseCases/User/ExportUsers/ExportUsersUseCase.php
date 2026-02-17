<?php

namespace App\Core\Application\UseCases\User\ExportUsers;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Domain\Entities\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ExportUsersUseCase
{
    private const CSV_HEADER = ['id', 'name', 'email', 'role', 'created_at'];

    private const TEMPORARY_URL_EXPIRY_MINUTES = 15;

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

        Log::info('ExportUsers: start', ['path' => $path, 'disk' => $diskName]);

        $content = $this->buildCsvContent();
        $contentBytes = strlen($content);
        Log::info('ExportUsers: CSV content built', ['path' => $path, 'content_bytes' => $contentBytes]);

        $written = $disk->put($path, $content);

        if ($written === false) {
            throw new RuntimeException('Failed to write export file to storage.');
        }

        Log::info('ExportUsers: file written', ['path' => $path, 'written_bytes' => $written]);

        $url = null;
        $expiresAt = null;

        if ($diskName !== 'local') {
            $expiresAt = new DateTimeImmutable('+'.self::TEMPORARY_URL_EXPIRY_MINUTES.' minutes');
            try {
                // temporaryUrl($path, $expiration) is a method on Laravel’s filesystem adapter.
                // It returns a time-limited, pre-signed URL that lets someone download the file from the
                // storage backend without going through your app and without needing your storage credentials.
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $url = $disk->temporaryUrl($path, $expiresAt);
            } catch (Throwable) {
                $url = null;
                $expiresAt = null;
            }
        }

        Log::info('ExportUsers: complete', [
            'path' => $path,
            'has_signed_url' => $url !== null,
            'expires_at' => $expiresAt?->format('c'),
        ]);

        return new ExportUsersResponse($path, $url, $expiresAt);
    }

    private function buildCsvContent(): string
    {
        $rows = [self::CSV_HEADER];
        $users = $this->userRepository->findAll();

        Log::info('ExportUsers: users loaded', ['count' => is_countable($users) ? count($users) : 0]);

        foreach ($users as $user) {
            $rows[] = $this->userToCsvRow($user);
        }

        // $lines: array of CSV line strings (header + one string per user row), each line is comma-separated fields
        $lines = array_map(function (array $row): string {
            // implode in PHP joins an array into a single string.
            return implode(',', array_map($this->escapeCsvField(...), $row));
        }, $rows);

        return implode("\n", $lines);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    private function userToCsvRow(User $user): array
    {
        $id = $user->getId() !== null ? (string) $user->getId() : '';
        $name = $user->getName();
        $email = $user->getEmail()->getValue();
        $role = $user->getRole() ?? '';
        $createdAt = $user->getCreatedAt()->format('Y-m-d H:i:s');

        return [$id, $name, $email, $role, $createdAt];
    }

    private function escapeCsvField(string $value): string
    {
        if (str_contains($value, '"') || str_contains($value, ',') || str_contains($value, "\n") || str_contains($value, "\r")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
