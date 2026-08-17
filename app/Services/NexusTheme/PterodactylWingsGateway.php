<?php

namespace App\Services\NexusTheme;

use App\Contracts\NexusServerGateway;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

/**
 * Uses Pterodactyl's existing Wings client instead of touching host disks.
 */
class PterodactylWingsGateway implements NexusServerGateway
{
    public function __construct(private readonly DaemonFileRepository $files)
    {
    }

    public function putRemoteFile(Server $server, string $path, string $contents): void
    {
        $this->files->setServer($server)->putContent($path, $contents);
    }

    public function replaceRemoteFileFromUrl(Server $server, string $path, string $downloadUrl): void
    {
        $directory = dirname($path);
        $this->files->setServer($server)->pull($downloadUrl, $directory === '.' ? '/' : '/' . trim($directory, '/'), [
            'filename' => basename($path),
            'foreground' => true,
        ]);
    }

    public function serverLog(Server $server, int $lines = 80): string
    {
        $log = $this->files->setServer($server)->getContent('logs/latest.log', 120000);
        $entries = preg_split('/\R/', $log, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(PHP_EOL, array_slice($entries, -abs($lines)));
    }
}