<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Casts;

use App\Core\Domain\ValueObjects\Email;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EmailCast implements CastsAttributes
{
    /**
     * Cast the given value to an Email value object.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Email
    {
        if ($value === null) {
            return null;
        }

        return new Email($value);
    }

    /**
     * Prepare the Email value object for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            // Allow setting as string, will be validated on next get
            return $value;
        }

        if (! $value instanceof Email) {
            throw new InvalidArgumentException('The given value is not an Email instance.');
        }

        return $value->getValue();
    }
}
