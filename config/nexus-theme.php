<?php

return [
    /*
     * Keep third-party credentials in the panel environment, never in this
     * repository. Modrinth, Hangar, PaperMC, and Geyser do not need a key.
     */
    'curseforge_api_key' => env('CURSEFORGE_API_KEY'),

    /*
     * These two URLs are the host adapter's escape hatch for platforms whose
     * release APIs vary. Leave null to use the built-in provider discovery.
     */
    'platform_downloads' => [
        'fabric' => env('NEXUS_FABRIC_DOWNLOAD_URL'),
        'forge' => env('NEXUS_FORGE_DOWNLOAD_URL'),
        'pufferfish' => env('NEXUS_PUFFERFISH_DOWNLOAD_URL'),
    ],
];