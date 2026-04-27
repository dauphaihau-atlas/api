<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ImportChunkProcessed;
use App\Events\ImportCompleted;
use App\Events\UserCreated;
use App\Listeners\BroadcastImportCompleted;
use App\Listeners\BroadcastImportProgress;
use App\Listeners\SendUserCreatedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, array<int, class-string>> */
    protected $listen = [
        UserCreated::class => [
            SendUserCreatedNotification::class,
        ],
        ImportCompleted::class => [
            BroadcastImportCompleted::class,
        ],
        ImportChunkProcessed::class => [
            BroadcastImportProgress::class,
        ],
    ];
}
