<?php

namespace App\Providers;

use App\Contracts\NexusServerGateway;
use App\Services\NexusTheme\PterodactylWingsGateway;
use Illuminate\Support\ServiceProvider;

class NexusThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NexusServerGateway::class, PterodactylWingsGateway::class);
    }
}