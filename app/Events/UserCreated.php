<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Domain\Entities\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserCreated
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
