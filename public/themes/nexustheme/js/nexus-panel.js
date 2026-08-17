/*
 * NexusTheme client. The theme stays safe to install because all privileged
 * work is delegated to same-origin panel endpoints configured by the host.
 */
(function () {
    "use strict";

    var script = document.getElementById("nexus-theme-script");
    var apiBase = script ? script.dataset.apiBase : "";
    var serverUuid = script ? script.dataset.serverUuid : "";
    var csrfToken = script ? script.dataset.csrfToken : "";

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function toast(message, type) {
        var existing = document.querySelector(".nexus-toast");
        if (existing) existing.remove();
        var element = document.createElement("div");
        element.className = "nexus-toast";
        element.dataset.type = type || "success";
        element.textContent = message;
        document.body.appendChild(element);
        window.setTimeout(function () { element.remove(); }, 4200);
    }

    async function request(path, options) {
        if (!apiBase || !serverUuid) {
            throw new Error("Open this tool from a server page to enable actions.");
        }

        var config = options || {};
        var headers = Object.assign({ Accept: "application/json" }, config.headers || {});
        if (csrfToken) headers["X-CSRF-TOKEN"] = csrfToken;
        if (config.body && typeof config.body !== "string") {
            headers["Content-Type"] = "application/json";
            config.body = JSON.stringify(config.body);
        }

        var response = await fetch(apiBase.replace(/\/$/, "") + "/" + serverUuid + path, {
            credentials: "same-origin",
            method: config.method || "GET",
            headers: headers,
            body: config.body
        });
        var payload = await response.json().catch(function () { return {}; });
        if (!response.ok) {
            throw new Error(payload.message || payload.error || "The panel rejected this request.");
        }
        return payload;
    }

    function setBusy(button, busy, label) {
        if (!button) return;
        if (busy) {
            button.dataset.originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = label || "Working…";
        } else {
            button.disabled = false;
            button.textContent = button.dataset.originalLabel || button.textContent;
        }
    }

    function resultMarkup(item) {
        return [
            '<div class="nexus-result">',
            '<div class="nexus-result-copy">',
            '<p class="nexus-result-title">' + escapeHtml(item.name || item.title || "Untitled project") + "</p>",
            '<p class="nexus-result-meta">' + escapeHtml(item.description || item.author || "Provider result") + "</p>",
            "</div>",
            '<span class="nexus-chip">' + escapeHtml(item.provider || "plugin") + "</span>",
            '<button class="nexus-button nexus-button--primary nexus-install" type="button" data-plugin="' +
                escapeHtml(JSON.stringify(item)) + '">Install</button>',
            "</div>"
        ].join("");
    }

    async function searchPlugins(form) {
        var button = form.querySelector("button[type=submit]");
        var results = document.querySelector("[data-nexus-results]");
        var query = form.querySelector("[name=query]").value.trim();
        var provider = form.querySelector("[name=provider]").value;
        if (!query) {
            toast("Enter a plugin name to search.", "error");
            return;
        }
        setBusy(button, true, "Searching…");
        try {
            var payload = await request("/plugins/search?source=" + encodeURIComponent(provider) + "&q=" + encodeURIComponent(query));
            var items = Array.isArray(payload.data) ? payload.data : [];
            results.innerHTML = items.length
                ? items.map(resultMarkup).join("")
                : '<p class="nexus-empty">No matching projects found in ' + escapeHtml(provider) + ".</p>";
        } catch (error) {
            toast(error.message, "error");
            results.innerHTML = '<p class="nexus-empty">Search failed. Check the provider connection and try again.</p>';
        } finally {
            setBusy(button, false);
        }
    }

    async function installPlugin(button) {
        var plugin;
        try { plugin = JSON.parse(button.dataset.plugin); } catch (_) { plugin = {}; }
        setBusy(button, true, "Installing…");
        try {
            var payload = await request("/plugins/install", { method: "POST", body: { plugin: plugin } });
            toast(payload.message || "Plugin installed. Restart the server to load it.");
            addActivity(payload.message || "Plugin installed");
        } catch (error) {
            toast(error.message, "error");
        } finally {
            setBusy(button, false);
        }
    }

    async function loadVersions(platform) {
        var panel = document.querySelector("[data-version-preview]");
        panel.innerHTML = '<span>Fetching releases for ' + escapeHtml(platform) + "…</span>";
        try {
            var payload = await request("/versions?platform=" + encodeURIComponent(platform));
            var latest = payload.data && payload.data[0] ? payload.data[0] : null;
            panel.innerHTML = latest
                ? "<strong>" + escapeHtml(latest.name || latest.version) + "</strong><span>" +
                    escapeHtml(latest.channel || "Stable") + " · " + escapeHtml(latest.releaseDate || "Latest release") + "</span>"
                : '<span class="nexus-empty">No release metadata returned.</span>';
            panel.dataset.latest = latest ? JSON.stringify(latest) : "";
        } catch (error) {
            panel.innerHTML = '<span class="nexus-empty">Unable to load platform releases.</span>';
            toast(error.message, "error");
        }
    }

    async function updateVersion(button) {
        var platform = document.querySelector("[data-platform][aria-pressed=true]");
        var panel = document.querySelector("[data-version-preview]");
        var latest = panel.dataset.latest ? JSON.parse(panel.dataset.latest) : {};
        setBusy(button, true, "Updating…");
        try {
            var payload = await request("/versions/update", {
                method: "POST",
                body: { platform: platform ? platform.dataset.platform : "paper", release: latest }
            });
            toast(payload.message || "Server jar updated. Restart the server to apply it.");
            addActivity(payload.message || "Server jar updated");
        } catch (error) {
            toast(error.message, "error");
        } finally {
            setBusy(button, false);
        }
    }

    async function loadGeyser(button) {
        var release = document.querySelector("[data-geyser-release]");
        setBusy(button, true, "Checking…");
        try {
            var payload = await request("/geyser/releases");
            var latest = payload.data && payload.data[0] ? payload.data[0] : null;
            release.innerHTML = latest
                ? "<strong>Geyser " + escapeHtml(latest.version || "Latest") + "</strong><span>" +
                    escapeHtml(latest.build ? "Build " + latest.build : "Official download available") + "</span>"
                : '<span class="nexus-empty">No Geyser release metadata returned.</span>';
            release.dataset.latest = latest ? JSON.stringify(latest) : "";
            document.querySelector("[data-geyser-update]").disabled = !latest;
        } catch (error) {
            toast(error.message, "error");
        } finally {
            setBusy(button, false);
        }
    }

    async function updateGeyser(button) {
        var release = document.querySelector("[data-geyser-release]");
        var latest = release.dataset.latest ? JSON.parse(release.dataset.latest) : {};
        setBusy(button, true, "Updating…");
        try {
            var payload = await request("/geyser/update", { method: "POST", body: { release: latest } });
            toast(payload.message || "GeyserMC has been updated.");
            addActivity(payload.message || "GeyserMC updated");
        } catch (error) {
            toast(error.message, "error");
        } finally {
            setBusy(button, false);
        }
    }

    function addActivity(message) {
        var list = document.querySelector("[data-nexus-activity]");
        if (!list) return;
        var item = document.createElement("div");
        item.className = "nexus-activity-item";
        item.innerHTML = '<div><p class="nexus-activity-title">' + escapeHtml(message) +
            '</p><p class="nexus-activity-meta">Just now · Nexus automation</p></div><span class="nexus-chip">done</span>';
        list.prepend(item);
    }

    async function askAssistant(form) {
        var input = form.querySelector("input");
        var responseElement = document.querySelector("[data-assistant-response]");
        var button = form.querySelector("button");
        var message = input.value.trim();
        if (!message) return;
        setBusy(button, true, "…");
        responseElement.textContent = "Nexus is interpreting your instruction…";
        try {
            var payload = await request("/assistant", { method: "POST", body: { message: message } });
            responseElement.textContent = payload.message || "Instruction completed.";
            addActivity(payload.message || "Assistant action completed");
            input.value = "";
        } catch (error) {
            responseElement.textContent = error.message;
            toast(error.message, "error");
        } finally {
            setBusy(button, false);
        }
    }

    function bind() {
        var root = document.querySelector("[data-nexus-tools]");
        if (!root) return;
        if (!serverUuid) serverUuid = root.dataset.serverUuid || "";

        var searchForm = root.querySelector("[data-nexus-plugin-search]");
        if (searchForm) searchForm.addEventListener("submit", function (event) {
            event.preventDefault();
            searchPlugins(searchForm);
        });

        root.querySelectorAll("[data-provider-tab]").forEach(function (tab) {
            tab.addEventListener("click", function () {
                root.querySelectorAll("[data-provider-tab]").forEach(function (item) {
                    item.setAttribute("aria-selected", item === tab ? "true" : "false");
                });
                var provider = searchForm.querySelector("[name=provider]");
                if (provider) provider.value = tab.dataset.providerTab;
            });
        });

        root.addEventListener("click", function (event) {
            var install = event.target.closest(".nexus-install");
            if (install) installPlugin(install);
            var platform = event.target.closest("[data-platform]");
            if (platform) {
                root.querySelectorAll("[data-platform]").forEach(function (item) {
                    item.setAttribute("aria-pressed", item === platform ? "true" : "false");
                });
                loadVersions(platform.dataset.platform);
            }
            var updateVersionButton = event.target.closest("[data-version-update]");
            if (updateVersionButton) updateVersion(updateVersionButton);
            var geyserCheck = event.target.closest("[data-geyser-check]");
            if (geyserCheck) loadGeyser(geyserCheck);
            var geyserUpdate = event.target.closest("[data-geyser-update]");
            if (geyserUpdate) updateGeyser(geyserUpdate);
        });

        var assistant = root.querySelector("[data-nexus-assistant]");
        if (assistant) assistant.addEventListener("submit", function (event) {
            event.preventDefault();
            askAssistant(assistant);
        });

        var initialPlatform = root.querySelector("[data-platform][aria-pressed=true]");
        if (initialPlatform) loadVersions(initialPlatform.dataset.platform);
    }

    document.addEventListener("DOMContentLoaded", bind);
    window.NexusTheme = { request: request, toast: toast };
}());