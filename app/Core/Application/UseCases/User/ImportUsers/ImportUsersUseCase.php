<?php

namespace App\Core\Application\UseCases\User\ImportUsers;

use App\Core\Application\Contracts\UserRepositoryInterface;
use App\Core\Domain\Entities\User;
use App\Core\Domain\ValueObjects\Email;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ImportUsersUseCase
{
    private const REQUIRED_HEADERS = ['name', 'email', 'password'];

    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(ImportUsersRequest $request): ImportUsersResponse
    {
        $disk = Storage::disk(config('filesystems.imports_disk', 'local'));
        $content = $disk->get($request->path);

        if ($content === null || $content === '') {
            return new ImportUsersResponse(0, 0, []);
        }

        // Use an in-memory stream so we can parse the CSV with fgetcsv(). fgetcsv() requires
        // a stream resource; we write the file content into php://memory, rewind, then read
        // line-by-line without writing a temp file to disk. Mode "r+" (read and write) is
        // needed because the code writes the file content into the stream, then rewinds and reads it.
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return new ImportUsersResponse(0, 0, [['row' => 0, 'message' => 'Failed to read import file.']]);
        }
        // Write CSV content into the stream, then rewind so fgetcsv() reads from the start.
        fwrite($stream, $content);
        rewind($stream); // Reset stream position to byte 0 so the next read starts at the beginning.

        $headerRow = fgetcsv($stream);
        if ($headerRow === false) {
            fclose($stream);

            return new ImportUsersResponse(0, 0, []);
        }

        $headerMap = $this->normalizeAndValidateHeaders($headerRow);
        if ($headerMap === null) {
            fclose($stream);

            return new ImportUsersResponse(0, 0, [['row' => 1, 'message' => 'Invalid or missing CSV headers. Required: name, email, password.']]);
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $rowIndex = 1;

        while (($row = fgetcsv($stream)) !== false) {
            $rowIndex++;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $name = trim((string) ($row[$headerMap['name']] ?? ''));
            $email = trim((string) ($row[$headerMap['email']] ?? ''));
            $password = trim((string) ($row[$headerMap['password']] ?? ''));

            $rowError = $this->validateRow($name, $email, $password, $rowIndex);
            if ($rowError !== null) {
                $errors[] = $rowError;
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowIndex, 'message' => 'Invalid email.'];
                continue;
            }

            $existingUser = $this->userRepository->findByEmail($email);

            if ($existingUser !== null) {
                $existingUser->updateName($name);
                $existingUser->updatePassword(Hash::make($password));
                $this->userRepository->save($existingUser);
                $updated++;
            } else {
                try {
                    $emailVo = new Email($email);
                } catch (\Throwable) {
                    $errors[] = ['row' => $rowIndex, 'message' => 'Invalid email.'];
                    continue;
                }
                $user = new User(
                    id: null,
                    name: $name,
                    email: $emailVo,
                    password: Hash::make($password)
                );
                $this->userRepository->save($user);
                $created++;
            }
        }

        fclose($stream);

        return new ImportUsersResponse($created, $updated, $errors);
    }

    /**
     * @param  array<int, string>  $headerRow
     * @return array{name: int, email: int, password: int}|null
     */
    private function normalizeAndValidateHeaders(array $headerRow): ?array
    {
        $map = [];
        foreach ($headerRow as $index => $cell) {
            $key = strtolower(trim((string) $cell));
            if ($key !== '') {
                $map[$key] = $index;
            }
        }
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! isset($map[$required])) {
                return null;
            }
        }

        return [
            'name' => $map['name'],
            'email' => $map['email'],
            'password' => $map['password'],
        ];
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{row: int, message: string}|null
     */
    private function validateRow(string $name, string $email, string $password, int $rowIndex): ?array
    {
        if ($name === '') {
            return ['row' => $rowIndex, 'message' => 'Name is required.'];
        }
        if ($email === '') {
            return ['row' => $rowIndex, 'message' => 'Email is required.'];
        }
        if ($password === '') {
            return ['row' => $rowIndex, 'message' => 'Password is required.'];
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return ['row' => $rowIndex, 'message' => 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'];
        }

        return null;
    }
}
