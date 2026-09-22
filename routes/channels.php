<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Canal privado por usuario: solo el propio usuario puede suscribirse.
| Angular utilizará este canal para recibir actualizaciones en tiempo real
| de sus picks y parlays via Laravel Echo + Laravel Reverb.
|
| Uso en Angular:
|   this.echo.private(`user.${userId}`)
|     .listen('.pick.progress.updated', (data) => { ... })
|
*/

Broadcast::channel('user.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

