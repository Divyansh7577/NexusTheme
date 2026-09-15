<?php

/*
 * NexusTheme routes.
 *
 * Register this file from the panel's RouteServiceProvider after the normal
 * authenticated client routes. The controller keeps provider credentials and
 * privileged file operations on the server; the browser never receives them.
 */

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\NexusThemeController;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;

Route::middleware([
    'client-api',
    'throttle:api.client',
    ServerSubject::class,
    AuthenticateServerAccess::class,
    ResourceBelongsToServer::class,
])
    ->prefix('/api/client/servers/{server}/nexus')
    ->scopeBindings()
    ->where(['server' => '[a-zA-Z0-9-]+'])
    ->group(function () {
        Route::get('/plugins/search', [NexusThemeController::class, 'searchPlugins']);
        Route::post('/plugins/install', [NexusThemeController::class, 'installPlugin']);
        Route::get('/versions', [NexusThemeController::class, 'versions']);
        Route::post('/versions/update', [NexusThemeController::class, 'updateVersion']);
        Route::get('/geyser/releases', [NexusThemeController::class, 'geyserReleases']);
        Route::post('/geyser/update', [NexusThemeController::class, 'updateGeyser']);
        Route::post('/assistant', [NexusThemeController::class, 'assistant']);
    });