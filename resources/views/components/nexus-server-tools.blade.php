{{-- Include this component inside the server overview view. --}}
<section class="nexus-theme-root" data-nexus-tools data-server-uuid="{{ $server->uuidShort ?? $server->uuid }}">
    <div class="nexus-hero">
        <div>
            <p class="nexus-eyebrow">NexusTheme / server control plane</p>
            <h1 class="nexus-title">Upgrade your server.</h1>
            <p class="nexus-subtitle">
                Install plugins, switch server platforms, and keep cross-play tooling current without leaving the panel.
            </p>
        </div>
        <span class="nexus-live-status">Wings connection healthy</span>
    </div>

    <div class="nexus-grid">
        <article class="nexus-tool-card nexus-tool-card--plugins">
            <div class="nexus-card-heading">
                <div>
                    <h2>Advanced plugin installer</h2>
                    <p>Search trusted ecosystems and send a release straight to the server.</p>
                </div>
                <span class="nexus-card-icon" aria-hidden="true">⌁</span>
            </div>

            <div class="nexus-search-tabs" role="tablist" aria-label="Plugin sources">
                <button type="button" role="tab" aria-selected="true" data-provider-tab="modrinth">Modrinth</button>
                <button type="button" role="tab" aria-selected="false" data-provider-tab="hangar">Hangar</button>
                <button type="button" role="tab" aria-selected="false" data-provider-tab="curseforge">CurseForge</button>
            </div>
            <form class="nexus-field-row" data-nexus-plugin-search>
                <div class="nexus-field">
                    <label for="nexus-plugin-query">Search catalog</label>
                    <input id="nexus-plugin-query" name="query" type="search" placeholder="Try Spark, Geyser, LuckPerms…" autocomplete="off">
                </div>
                <input type="hidden" name="provider" value="modrinth">
                <button class="nexus-button nexus-button--primary" type="submit">Search</button>
            </form>
            <div class="nexus-results" data-nexus-results>
                <p class="nexus-empty">Search a catalog to see installable releases.</p>
            </div>
        </article>

        <article class="nexus-tool-card nexus-tool-card--versions">
            <div class="nexus-card-heading">
                <div>
                    <h2>Universal version changer</h2>
                    <p>Move between supported server platforms from one control.</p>
                </div>
                <span class="nexus-card-icon" aria-hidden="true">↯</span>
            </div>
            <div class="nexus-platform-grid" aria-label="Server platform">
                @foreach (['paper', 'pufferfish', 'purpur', 'fabric', 'forge'] as $platform)
                    <button class="nexus-platform" type="button" data-platform="{{ $platform }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                        {{ ucfirst($platform) }}
                    </button>
                @endforeach
            </div>
            <div class="nexus-version-preview" data-version-preview>
                <span>Choose a platform to inspect the latest release.</span>
            </div>
            <button class="nexus-button nexus-button--purple" type="button" data-version-update style="margin-top: 14px; width: 100%;">
                Update server jar
            </button>
        </article>

        <article class="nexus-tool-card nexus-tool-card--geyser">
            <div class="nexus-card-heading">
                <div>
                    <h2>GeyserMC updater</h2>
                    <p>Track the official GeyserMC build channel and replace the jar safely.</p>
                </div>
                <span class="nexus-card-icon" aria-hidden="true">◈</span>
            </div>
            <div class="nexus-geyser-release" data-geyser-release>
                <strong>Official GeyserMC channel</strong>
                <span>Check Jenkins/download metadata for the newest build.</span>
            </div>
            <div class="nexus-field-row" style="margin-top: 14px;">
                <button class="nexus-button nexus-button--secondary" type="button" data-geyser-check>Check for update</button>
                <button class="nexus-button nexus-button--primary" type="button" data-geyser-update disabled>Replace Geyser jar</button>
            </div>
        </article>

        <article class="nexus-tool-card nexus-tool-card--activity">
            <div class="nexus-card-heading">
                <div>
                    <h2>Automation activity</h2>
                    <p>Every install and update is recorded for a quick audit trail.</p>
                </div>
                <span class="nexus-chip">protected actions</span>
            </div>
            <div class="nexus-activity-list" data-nexus-activity>
                <div class="nexus-activity-item">
                    <div>
                        <p class="nexus-activity-title">Nexus control plane ready</p>
                        <p class="nexus-activity-meta">Waiting for your first action</p>
                    </div>
                    <span class="nexus-chip">online</span>
                </div>
            </div>
        </article>
    </div>
</section>

<aside class="nexus-assistant" aria-label="Nexus AI Assistant">
    <div class="nexus-assistant-heading">
        <span class="nexus-card-icon" aria-hidden="true">✦</span>
        <strong>Nexus AI Assistant</strong>
        <span>Natural language controls</span>
    </div>
    <form class="nexus-assistant-form" data-nexus-assistant>
        <input type="text" placeholder="Ask: Update Geyser or install Spark…" aria-label="Assistant instruction">
        <button type="submit">Run</button>
    </form>
    <p class="nexus-assistant-response" data-assistant-response>Ready when you are.</p>
</aside>