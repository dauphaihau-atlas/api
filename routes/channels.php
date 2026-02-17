<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('imports.{importId}', function ($user, int $importId) {
    return $user->role === 'admin';
});
