<?php

namespace Pterodactyl\Providers;

use Pterodactyl\Contracts\NexusServerGateway;
use Pterodactyl\Services\NexusTheme\PterodactylWingsGateway;
use Illuminate\Support\ServiceProvider;

class NexusThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NexusServerGateway::class, PterodactylWingsGateway::class);
    }
}