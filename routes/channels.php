<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('imports.{importId}', function ($user, int $importId) {
    return Gate::forUser($user)->allows('admin');
});
