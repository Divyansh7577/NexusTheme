<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Contracts\NexusServerGateway;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Throwable;

class NexusThemeController extends Controller
{
    public function __construct(private readonly NexusServerGateway $gateway)
    {
    }

    public function searchPlugins(Request $request, Server $server): JsonResponse
    {
        $query = trim((string) $request->query('q'));
        $source = strtolower((string) $request->query('source', 'modrinth'));

        if ($query === '') {
            return response()->json(['message' => 'A plugin search query is required.'], 422);
        }

        try {
            $items = match ($source) {
                'hangar' => $this->searchHangar($query),
                'curseforge' => $this->searchCurseForge($query),
                default => $this->searchModrinth($query),
            };

            return response()->json(['data' => $items]);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'The provider could not be reached.'], 502);
        }
    }

    public function installPlugin(Request $request, Server $server): JsonResponse
    {
        $plugin = (array) $request->input('plugin', []);
        $downloadUrl = filter_var($plugin['downloadUrl'] ?? null, FILTER_VALIDATE_URL);
        $fileName = basename((string) ($plugin['fileName'] ?? $plugin['name'] ?? 'plugin.jar'));

        if (!$downloadUrl || !str_ends_with(strtolower($fileName), '.jar')) {
            return response()->json(['message' => 'This provider result is not an installable jar.'], 422);
        }

        $this->gateway->replaceRemoteFileFromUrl($server, 'plugins/' . $fileName, $downloadUrl);

        return response()->json(['message' => $fileName . ' installed in the plugins directory.']);
    }

    public function versions(Request $request, Server $server): JsonResponse
    {
        $platform = strtolower((string) $request->query('platform', 'paper'));
        $allowed = ['paper', 'pufferfish', 'purpur', 'fabric', 'forge'];

        if (!in_array($platform, $allowed, true)) {
            return response()->json(['message' => 'Unsupported server platform.'], 422);
        }

        $releases = $this->platformReleases($platform);
        return response()->json(['data' => $releases]);
    }

    public function updateVersion(Request $request, Server $server): JsonResponse
    {
        $platform = strtolower((string) $request->input('platform', 'paper'));
        $release = (array) $request->input('release', []);
        $downloadUrl = filter_var($release['downloadUrl'] ?? null, FILTER_VALIDATE_URL);
        $jarName = basename((string) ($release['fileName'] ?? $platform . '.jar'));

        if (!$downloadUrl || !in_array($platform, ['paper', 'pufferfish', 'purpur', 'fabric', 'forge'], true)) {
            return response()->json(['message' => 'Choose a valid platform release first.'], 422);
        }

        $this->gateway->replaceRemoteFileFromUrl($server, $jarName, $downloadUrl);
        return response()->json(['message' => ucfirst($platform) . ' server jar updated successfully.']);
    }

    public function geyserReleases(Request $request, Server $server): JsonResponse
    {
        $payload = Http::timeout(15)
            ->get('https://download.geysermc.org/v2/projects/geyser/versions')
            ->throw()
            ->json();

        $versions = is_array($payload) ? $payload : [];
        $latestVersion = end($versions);
        $builds = Http::timeout(15)
            ->get('https://download.geysermc.org/v2/projects/geyser/versions/' . rawurlencode((string) $latestVersion) . '/builds')
            ->throw()
            ->json();
        $latestBuild = is_array($builds) && $builds !== [] ? end($builds) : null;

        return response()->json(['data' => [[
            'version' => $latestVersion,
            'build' => $latestBuild,
            'downloadUrl' => 'https://download.geysermc.org/v2/projects/geyser/versions/' .
                rawurlencode((string) $latestVersion) . '/builds/' . rawurlencode((string) $latestBuild) . '/downloads/spigot',
            'fileName' => 'Geyser-Spigot.jar',
        ]]]);
    }

    public function updateGeyser(Request $request, Server $server): JsonResponse
    {
        $release = (array) $request->input('release', []);
        $downloadUrl = filter_var($release['downloadUrl'] ?? null, FILTER_VALIDATE_URL);

        if (!$downloadUrl) {
            return response()->json(['message' => 'Check for an official Geyser release before updating.'], 422);
        }

        $this->gateway->replaceRemoteFileFromUrl($server, 'plugins/Geyser-Spigot.jar', $downloadUrl);
        return response()->json(['message' => 'Geyser-Spigot.jar replaced with the latest official build.']);
    }

    public function assistant(Request $request, Server $server): JsonResponse
    {
        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return response()->json(['message' => 'Tell Nexus what you want to do.'], 422);
        }

        $normalized = strtolower($message);
        if (str_contains($normalized, 'geyser')) {
            return $this->updateGeyser($request->merge(['release' => $this->latestGeyserRelease()]), $server);
        }

        if (preg_match('/install\s+([a-z0-9 _-]+)/i', $message, $matches)) {
            $pluginName = trim($matches[1]);
            $results = $this->searchModrinth($pluginName);
            $plugin = $results[0] ?? null;
            if (!$plugin || empty($plugin['downloadUrl'])) {
                return response()->json(['message' => 'I could not find a downloadable Modrinth release for "' . $pluginName . '".']);
            }
            $this->gateway->replaceRemoteFileFromUrl(
                $server,
                'plugins/' . basename((string) ($plugin['fileName'] ?? $pluginName . '.jar')),
                $plugin['downloadUrl']
            );
            return response()->json(['message' => $plugin['name'] . ' was installed from Modrinth.']);
        }

        if (str_contains($normalized, 'log')) {
            $logs = $this->gateway->serverLog($server);
            return response()->json([
                'message' => $this->summarizeLogs($logs),
                'logs' => $logs,
            ]);
        }

        return response()->json(['message' => 'Try “Update Geyser”, “Install Spark”, or “Analyze server logs”.']);
    }

    private function provider(string $baseUrl): PendingRequest
    {
        return Http::acceptJson()->timeout(15)->baseUrl($baseUrl);
    }

    private function searchModrinth(string $query): array
    {
        $payload = $this->provider('https://api.modrinth.com/v2')
            ->get('/search', ['query' => $query, 'facets' => '[["project_type:plugin"]]'])
            ->throw()->json();

        return collect($payload['hits'] ?? [])->take(8)->map(function (array $item): array {
            $version = $this->provider('https://api.modrinth.com/v2')
                ->get('/project/' . rawurlencode($item['project_id']) . '/version', ['loaders' => 'paper'])
                ->throw()->json();
            $file = is_array($version[0]['files'] ?? null) && $version[0]['files'] !== []
                ? $version[0]['files'][0]
                : [];

            return [
                'name' => $item['title'] ?? $item['project_id'],
                'description' => $item['description'] ?? '',
                'provider' => 'Modrinth',
                'projectId' => $item['project_id'],
                'downloadUrl' => $file['url'] ?? null,
                'fileName' => $file['filename'] ?? null,
            ];
        })->filter(fn (array $item): bool => !empty($item['downloadUrl']))->values()->all();
    }

    private function searchHangar(string $query): array
    {
        $payload = $this->provider('https://hangar.papermc.io/api/v1')
            ->get('/projects', ['query' => $query, 'limit' => 8])
            ->throw()->json();

        return collect($payload['result'] ?? [])->map(function (array $item): array {
            $namespace = $item['namespace']['name'] ?? 'Hangar';
            $slug = $item['namespace']['slug'] ?? $item['name'] ?? '';
            $versions = $this->provider('https://hangar.papermc.io/api/v1')
                ->get('/projects/' . rawurlencode($namespace) . '/' . rawurlencode($slug) . '/versions', ['limit' => 1])
                ->throw()->json();
            $latest = $versions['result'][0] ?? [];
            $download = $latest['downloads']['PAPER'] ?? null;

            return [
                'name' => $item['name'] ?? $namespace . '/' . $slug,
                'description' => $item['description'] ?? '',
                'provider' => 'Hangar',
                'projectId' => $namespace . '/' . $slug,
                'downloadUrl' => is_string($download) ? $download : null,
                'fileName' => $slug . '.jar',
            ];
        })->filter(fn (array $item): bool => !empty($item['downloadUrl']))->values()->all();
    }

    private function searchCurseForge(string $query): array
    {
        $key = (string) config('nexus-theme.curseforge_api_key', env('CURSEFORGE_API_KEY'));
        if ($key === '') {
            return [];
        }

        $payload = Http::withHeaders(['x-api-key' => $key])->acceptJson()->timeout(15)
            ->get('https://api.curseforge.com/v1/mods/search', [
                'gameId' => 432,
                'classId' => 5,
                'searchFilter' => $query,
                'pageSize' => 8,
            ])->throw()->json();

        return collect($payload['data'] ?? [])->map(function (array $item): array {
            $latestFile = $item['latestFiles'][0] ?? [];
            return [
                'name' => $item['name'] ?? 'CurseForge project',
                'description' => $item['summary'] ?? '',
                'provider' => 'CurseForge',
                'projectId' => $item['id'] ?? null,
                'downloadUrl' => $latestFile['downloadUrl'] ?? null,
                'fileName' => $latestFile['fileName'] ?? null,
            ];
        })->filter(fn (array $item): bool => !empty($item['downloadUrl']))->values()->all();
    }

    private function platformReleases(string $platform): array
    {
        $configuredUrl = config('nexus-theme.platform_downloads.' . $platform);
        if (is_string($configuredUrl) && $configuredUrl !== '') {
            return [[
                'name' => ucfirst($platform) . ' configured release',
                'version' => 'configured',
                'channel' => 'Host configured',
                'releaseDate' => 'Latest from your provider',
                'downloadUrl' => $configuredUrl,
                'fileName' => $platform . '.jar',
            ]];
        }

        $payload = match ($platform) {
            'paper' => $this->provider('https://api.papermc.io/v2')->get('/projects/paper')->throw()->json(),
            'purpur' => $this->provider('https://api.purpurmc.org/v2')->get('/purpur')->throw()->json(),
            default => ['versions' => ['latest']],
        };

        $version = is_array($payload['versions'] ?? null) ? end($payload['versions']) : 'latest';
        $downloadUrl = null;
        $build = null;
        if ($platform === 'paper') {
            $builds = $this->provider('https://api.papermc.io/v2')
                ->get('/projects/paper/versions/' . rawurlencode((string) $version))
                ->throw()->json();
            $build = is_array($builds['builds'] ?? null) ? end($builds['builds']) : null;
            $downloadUrl = $build ? 'https://api.papermc.io/v2/projects/paper/versions/' . rawurlencode((string) $version) .
                '/builds/' . rawurlencode((string) $build) . '/downloads/paper-' . $version . '-' . $build . '.jar' : null;
        } elseif ($platform === 'purpur') {
            $builds = $this->provider('https://api.purpurmc.org/v2')
                ->get('/purpur/' . rawurlencode((string) $version))
                ->throw()->json();
            $build = $builds['builds']['latest'] ?? null;
            $downloadUrl = $build ? 'https://api.purpurmc.org/v2/purpur/' . rawurlencode((string) $version) .
                '/' . rawurlencode((string) $build) . '/download' : null;
        }

        return [[
            'name' => ucfirst($platform) . ' ' . $version,
            'version' => $version,
            'build' => $build,
            'channel' => $platform === 'paper' || $platform === 'purpur' ? 'Stable' : 'Host configured',
            'releaseDate' => 'Latest available',
            'downloadUrl' => $downloadUrl,
            'fileName' => $platform . '.jar',
        ]];
    }

    private function latestGeyserRelease(): array
    {
        $version = Http::timeout(15)->get('https://download.geysermc.org/v2/projects/geyser/versions')->throw()->json();
        $latestVersion = is_array($version) ? end($version) : null;
        $builds = Http::timeout(15)->get('https://download.geysermc.org/v2/projects/geyser/versions/' . rawurlencode((string) $latestVersion) . '/builds')->throw()->json();
        $latestBuild = is_array($builds) && $builds !== [] ? end($builds) : null;

        return [
            'version' => $latestVersion,
            'build' => $latestBuild,
            'downloadUrl' => 'https://download.geysermc.org/v2/projects/geyser/versions/' .
                rawurlencode((string) $latestVersion) . '/builds/' . rawurlencode((string) $latestBuild) . '/downloads/spigot',
        ];
    }

    private function summarizeLogs(string $logs): string
    {
        $lines = preg_split('/\R/', $logs, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $errors = count(array_filter($lines, fn (string $line): bool => preg_match('/\b(error|exception|fatal)\b/i', $line) === 1));
        $warnings = count(array_filter($lines, fn (string $line): bool => preg_match('/\bwarn(ing)?\b/i', $line) === 1));

        if ($errors === 0 && $warnings === 0) {
            return 'Log analysis complete: no errors or warnings found in the latest ' . count($lines) . ' lines.';
        }

        return 'Log analysis complete: ' . $errors . ' error(s) and ' . $warnings .
            ' warning(s) found in the latest ' . count($lines) . ' lines.';
    }
}