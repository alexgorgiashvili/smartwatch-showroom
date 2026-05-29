<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Auth;

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

/**
 * Private admin inbox channel
 * Only authenticated users can listen
 */
Broadcast::channel('inbox', function ($user) {
    if (app()->environment('local')) {
        return true;
    }

    return Auth::check();
});

Broadcast::channel('inbox.conversation.{id}', function ($user, $id) {
    if (app()->environment('local')) {
        return true;
    }

    return Auth::check();
});

Broadcast::channel('social.comments', function ($user) {
    if (app()->environment('local')) {
        return true;
    }

    return Auth::check();
});
