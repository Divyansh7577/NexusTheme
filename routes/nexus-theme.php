<?php

/*
 * NexusTheme routes.
 *
 * Register this file from the panel's RouteServiceProvider after the normal
 * authenticated client routes. The controller keeps provider credentials and
 * privileged file operations on the server; the browser never receives them.
 */

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Pterodactyl\Models\Server;
use App\Http\Controllers\NexusThemeController;

Route::model('server', Server::class);

Route::middleware(['auth', RequireTwoFactorAuthentication::class])
    ->prefix('api/client/servers/{server}/nexus')
    ->where('server', '[a-zA-Z0-9-]+')
    ->group(function () {
        Route::get('/plugins/search', [NexusThemeController::class, 'searchPlugins']);
        Route::post('/plugins/install', [NexusThemeController::class, 'installPlugin']);
        Route::get('/versions', [NexusThemeController::class, 'versions']);
        Route::post('/versions/update', [NexusThemeController::class, 'updateVersion']);
        Route::get('/geyser/releases', [NexusThemeController::class, 'geyserReleases']);
        Route::post('/geyser/update', [NexusThemeController::class, 'updateGeyser']);
        Route::post('/assistant', [NexusThemeController::class, 'assistant']);
    });