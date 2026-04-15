<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// This is likely the channel your other working component is using.
// We'll leave it here for reference.
Broadcast::channel('scheduleImport', function ($user) {
    // This makes it a private channel that any authenticated user can join.
    // If it were truly public, you might not define it here, or just return true.
    return $user != null;
});
