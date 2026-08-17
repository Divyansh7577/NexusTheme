<?php

namespace App\Contracts;

use Pterodactyl\Models\Server;

/**
 * Bridge between NexusTheme and the panel's existing Wings client.
 *
 * The theme intentionally does not write to the host filesystem. Bind this
 * contract to a small adapter around the Pterodactyl/Wings client in the host
 * panel so permissions, audit logging, and node connectivity remain centralised.
 */
interface NexusServerGateway
{
    public function putRemoteFile(Server $server, string $path, string $contents): void;

    public function replaceRemoteFileFromUrl(Server $server, string $path, string $downloadUrl): void;

    public function serverLog(Server $server, int $lines = 80): string;
}