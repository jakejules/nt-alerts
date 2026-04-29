=== NT Service Alerts ===
Contributors: niagaratransit
Tags: transit, alerts, niagara, rest-api, embed
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Supervisor-posted service alerts for Niagara Transit, with a REST API and an embeddable widget for third-party sites.

== Description ==

Supervisors post short-term and long-term service alerts (detours, delays, cancellations, stop closures, weather) from a mobile-first admin interface. Alerts are exposed as JSON via a custom REST API and rendered on external sites via a drop-in JavaScript embed.

== Installation ==

1. Upload the `nt-alerts` folder to `/wp-content/plugins/`.
2. Activate through **Plugins**.
3. Visit **Settings → Permalinks** and click Save once to flush rewrite rules.
4. Create at least one supervisor: **Users → Add New**, role "Alert Supervisor". Use "Alert Manager" instead for users who also need to manage Settings, view the Archive, and delete alerts posted by anyone.

== REST API ==

Namespace: `/wp-json/nt-alerts/v1/`

Public (no auth):

* `GET /alerts/active` — all active alerts, split into `short_term` and `long_term`.
* `GET /alerts/route/{route_id}` — filtered to a single route.

Authenticated (cookie auth with nonce, or Application Password via Basic Auth):

* `POST /alerts` — create an alert.
* `PATCH /alerts/{id}` — update fields (used by Extend).
* `POST /alerts/{id}/end` — end an alert immediately.
* `GET /alerts/mine` — current supervisor's own alerts.

The `/alerts/active` response is cached via a WordPress transient (default TTL 60 seconds) and automatically busted whenever an `nt_alert` post is created, updated, or deleted. The response includes an `X-Nt-Alerts-Cache: HIT|MISS` header for diagnostics.

== CORS ==

Origins must be explicitly allowlisted — the plugin never emits `Access-Control-Allow-Origin: *`.

* Site-origin requests (e.g. an embed hosted on the same WordPress site) are allowed automatically for the public GET endpoints.
* Additional origins can be listed in the `nt_alerts_cors_origins` option (settings UI arrives in a later release).
* For local development, define `NT_ALERTS_DEV_CORS_ORIGINS` in `wp-config.php`, e.g.:

    `define( 'NT_ALERTS_DEV_CORS_ORIGINS', array( 'http://localhost:8000' ) );`

Authenticated write endpoints require the origin to appear in the allowlist — the site-origin auto-allow does not apply to writes.

== Authentication for API clients ==

The easiest way to test write endpoints is with an **Application Password**:

1. In WordPress: **Users → Profile → Application Passwords** → "Add New".
2. Copy the generated password (it looks like `xxxx yyyy zzzz aaaa`).
3. Call the API with HTTP Basic Auth, for example:

    `curl -u "username:xxxx yyyy zzzz aaaa" https://YOURSITE/wp-json/nt-alerts/v1/alerts/mine`

Your host must pass through the `Authorization` header. Some shared hosts strip it; if `Basic Auth` returns 401 unexpectedly, check your host's docs.

== Embed ==

Drop this snippet into any third-party page (Umbraco, static HTML, etc.):

    <link rel="stylesheet" href="https://YOURSITE/wp-content/plugins/nt-alerts/public/embed.css?ver=0.3.0">
    <div id="nt-alerts"></div>
    <script src="https://YOURSITE/wp-content/plugins/nt-alerts/public/embed.js?ver=0.3.0"></script>

Configuration via data attributes on the container:

* `data-route="301"` — show only alerts affecting one route.
* `data-type="short_term" | "long_term" | "all"` — default `all`.
* `data-limit="5"` — maximum alerts to show.
* `data-theme="light" | "dark" | "auto"` — default `auto` (honours `prefers-color-scheme`).
* `data-grouped="true"` — render two labelled sections (short-term + long-term) inside one container instead of a flat mixed list.
* `data-short-label="Right now"` — heading for the short-term section when `data-grouped="true"`.
* `data-long-label="Ongoing"` — heading for the long-term section when `data-grouped="true"`.

Multiple embeds on one page: use `class="nt-alerts"` instead of `id="nt-alerts"`.

The embed polls the API every 60 seconds, pauses while the tab is hidden, and renders a fallback link if the API is unreachable.

== Cron jobs ==

The plugin schedules two background tasks via WP-Cron:

* **Every 5 min** — auto-expire alerts whose `end_time` has passed; bust the public cache.
* **Daily 03:00 site-time** — archive expired alerts older than the configured threshold (default 30 days; configurable in Settings).

WP-Cron fires on traffic. If the site is quiet, set up a real cron job pointing at `https://YOURSITE/wp-cron.php` (your host probably has a UI for this) so auto-expiry stays on time.

To force-run a job for testing, use WP-CLI:

    wp cron event run nt_alerts_cron_expire_check
    wp cron event run nt_alerts_cron_archive

Or visit `wp-cron.php` directly with the alarm time advanced via the WP Crontrol plugin.

== Accessibility ==

The plugin targets WCAG 2.0 AA / AODA throughout:

* Touch targets are at least 44x44px on every actionable control (cards, chips, dialog options, primary buttons).
* All interactive elements are keyboard-reachable with visible focus rings.
* The Extend dialog uses native `<dialog>` for built-in focus trap + Escape-to-close, and returns focus to the Extend button when dismissed.
* Severity is conveyed by icon + colored border + the literal severity word — never by colour alone.
* Route chips fall back to a dark theme-consistent background when selected so light GTFS route colours (yellow, white) never produce low-contrast text.
* `prefers-reduced-motion` is honoured in both the embed and admin styles.
* Form fields are programmatically labelled; submit errors are announced via a live region and tied to the submit button via `aria-describedby`.
* The embed announces absolute times to screen readers via `aria-label` (e.g. "Posted Friday April 24, 2:42 pm").
* Text resizes to 200% without clipping; all body sizes use `rem`.

== Changelog ==

= 1.9.0 =
* New **Alert Manager** role (`nt_alert_manager`). Same operational UX as Alert Supervisor — they land on the dashboard at login and the WP-native Dashboard / Tools / Comments / Media menus are hidden — but with the extra plugin caps that supervisors don't have: `delete_others_nt_alerts`, `manage_nt_alerts_settings`, `view_nt_alerts_archive`. Effectively: full plugin access without WordPress-wide admin powers.
* The **Media** menu is now also hidden for Alert Supervisors. They can still upload pictures from the alert form (they keep `upload_files`); the sidebar link to the standalone Media Library is just gone for both roles.
* Archive view's "posted by" filter now lists managers alongside supervisors and admins.

= 1.8.0 =
* Alert Supervisors can now see, read, and edit each other's alerts (so an on-shift supervisor can extend or end an ongoing alert posted by a supervisor whose shift has finished). Deletion remains administrator-only.
* Dashboard cards now show the author inline in the footer ("Posted {time} by {name}") so it's clear at a glance who posted each alert.
* Removed the every-15-minute expiry-warning email and the daily 23:00 end-of-shift digest. The auto-expire (every 5 min) and auto-archive (daily 03:00) jobs continue to run. Existing scheduled events for the removed jobs are cleared on the next plugin activation/deactivation.
* `NT_Alerts_Notifications` is now a thin wrapper around the "new alert posted" operator log; the channel-pluggable dispatcher (`nt_alerts_channels` filter, `send_via_*` methods) was removed along with the two cron-driven notifications it served.

= 1.7.1 =
* Supervisor dashboard cards now match the public embed look:
  * Severity chip (icon + colored pill) at the top instead of a plain inline label.
  * Route chips with GTFS colors and auto-contrasted text replace the comma-separated routes line.
  * Larger bold title, more breathing room.
  * Combined footer line — "Posted X · expires Y" with a subtle divider above.
* Visual ordering: severity, title, description, route chips, reason, stops, images, internal block, footer, action buttons. The yellow internal-only aside stays unchanged.
* Extend now updates the in-line expiry segment (and shows the separator) — no extra page reload after the in-place update.

= 1.7.0 =
* Embed redesign:
  * **Two-column auto-flow** — when the embed container is wider than ~880px, alerts naturally arrange into 2+ columns. Narrower containers stay 1 column. No new attribute required, no breaking change for existing embeds.
  * **Severity chip** at the top of each card — colored pill with icon + word ("WARNING", "CRITICAL", "INFO"). Replaces the old inline "Warning: Detour" header.
  * **Route chips** — each affected route is now a small colored pill using its GTFS `route_color`, with auto-computed black/white text for contrast. Replaces the old comma-separated routes line.
  * **Larger title** (~1.18rem, bold) for better hierarchy.
  * **Footer divider** — subtle horizontal rule above the Posted/Expires line.
* New `routes_detail` field in the public REST response (id + color + label resolved from the catalogue). Existing `routes` string array is unchanged for backward compat.
* Saving the routes catalogue in Settings now also flushes the in-memory route lookup, so colour changes propagate to the embed within one cache cycle.

= 1.6.1 =
* Dashboard layout: Active now and Ongoing (long-term) sections are now side-by-side on desktop (≥900px wide). On smaller screens they stack as before. Cards within each section flow with auto-fit so the layout adapts cleanly to the new column widths.

= 1.6.0 =
* Archive view: added CSV export. Quick-period buttons for **This week / Last week / This month / Last month** plus an **Export filtered (CSV)** button that uses the current archive filters. Files download with UTF-8 BOM so Excel renders accented stop names correctly.
* Export columns: ID, posted at, posted by, status, type, category, reason, severity, title, description, routes, closed stops, alternate stops, start/end/last-updated times, and internal department / vehicle / maintenance reason.
* Export is capped at 5,000 rows per file to keep memory predictable. Use narrower filters if you need more — usual report periods (week/month) stay well under that.
* Pictures are intentionally excluded from the CSV.

= 1.5.1 =
* Archive view: replaced the classic-editor "View" link with an "Edit" link that opens the custom edit form, keeping the editing UX consistent with the dashboard.

= 1.5.0 =
* Added an **Edit** button to every active dashboard card (alongside Extend and End now). Reuses the new-alert form pre-populated with the existing values: category, routes, reason, title, description, closed/alternate stops, images, internal-only fields. Submitting saves via PATCH and returns to the dashboard with a one-line "Alert updated." flash.
* Edit mode pins duration to "Custom" with the existing expiry pre-filled, so opening Edit doesn't accidentally shorten an alert when the supervisor only wants to fix a typo. Switching to a preset still works and recalculates from the current time.
* Permission check uses the standard `edit_post` capability — at this point supervisors can edit only their own alerts; admins can edit anyone's. (Lifted in 1.8.0 — supervisors can now edit any supervisor's alert.)

= 1.4.3 =
* Supervisor dashboard cards now render Closed and Alternate stops as bulleted lists, matching the embed and email format.

= 1.4.2 =
* Closed stops and Alternate stops now render as a bulleted `<ul>` on the public embed and as `• Name` lines in the operator notification email — easier to scan than a long comma-separated run.

= 1.4.1 =
* Image upload simplified: clicking **Choose images** now opens the native file picker directly (no Media Library detour). Selected files upload to the WP Media Library automatically and appear as thumbnail previews with progress feedback.

= 1.4.0 =
* New **Pictures (optional)** section on the new-alert form. Up to 3 images per alert, picked from the WordPress Media Library (no camera capture — typical use is uploading detour map screenshots).
* Alt-text is auto-generated from the alert title — single image gets the title, multiple images get "{title} — image N of M". No extra fields for supervisors.
* Images render as 88-96 px thumbnails on the supervisor dashboard, the public embed, and click through to the full-size image in a new tab.
* Stored as standard Media Library attachments, referenced by attachment ID in post meta. Public REST response includes resolved URLs (full + medium thumbnail) so the embed can render without extra fetches.

= 1.3.1 =
* Fixed the new-alert notification email so it actually contains the alert details. Previously it fired before the post meta was written, so most fields arrived blank.
* Email subject now leads with severity + category + routes + title (e.g. `[NT Alerts] Warning — Detour on 301, 302: Detour on 301, 302`).
* Body uses human-readable category, severity, reason, department, and maintenance-reason labels (instead of slugs like `cancelled_trip`).
* Timestamps render in site-local format (`Apr 25, 2026 2:42 pm`) instead of raw ISO 8601.

= 1.3.0 =
* New Settings field: **Notify on every new alert**. Add one or more email addresses (one per line) to receive a notification each time any alert is posted, with full alert details including internal fields. Leave blank to disable.
* Notifications fire on creation only (not on subsequent updates) and skip post revisions / autosaves.

= 1.2.0 =
* New category **Reduced service**. Removed Weather from the category list — Weather is now a Reason value instead.
* New **Reason** field on every alert (optional). Eleven options: Construction, Street closure, Weather, Maintenance, Police activity, Fire, Evacuation, Terminal closure, Collision, Parade, Other. Surfaced on dashboard cards, the Archive table, and the public embed.
* New **Stops affected** picker on the new-alert form: two typeahead inputs (Closed stops + Use these stops instead). Searches by stop name, intersection text, stop code, or stop ID across the full ~1,875-stop GTFS catalogue. Selected stops appear as removable chips. Public REST + embed render closed/alternate stop names alongside each alert.
* New **Internal-only** section on the new-alert form (Department responsible, plus Vehicle number + Maintenance reason when Maintenance is picked). These fields stay inside the WordPress site — they are excluded from the public REST response and the embed.
* Settings → Stops catalogue: shows the current stop count and a "Re-seed from default file" button that re-imports `data/stops-default.php` (drop in a fresh GTFS export, click re-seed).
* New REST endpoint `GET /wp-json/nt-alerts/v1/stops` returning the public catalogue (cached for 5 minutes downstream).

= 1.1.0 =
* Embed: added grouped mode. Set `data-grouped="true"` on the container to render short-term and long-term alerts in two labelled sections inside a single embed (one HTTP request, two visually distinct lists). Section headings are configurable via `data-short-label` and `data-long-label` (defaults: "Right now" / "Ongoing").
* When grouped, alert titles render as `<h4>` (one level below the section headings) for proper heading hierarchy. Flat-mode alerts still render as `<h3>` so existing embeds are unchanged.
* `data-limit` now caps each section independently in grouped mode (e.g. limit 3 = up to 3 short-term + up to 3 long-term).
* Empty sections are skipped — if there are no long-term alerts, only the short-term heading appears.

= 1.0.0 =
* Accessibility audit pass: fixed `aria-labelledby` references in the new-alert form, added explicit IDs to every legend, gave the duration radiogroup a programmatic name, wired the form-level error region to the submit via `aria-describedby`, returned focus to the Extend trigger when the dialog closes, added screen-reader friendly absolute-time `aria-label`s in the embed, and unified the focus ring across plugin admin screens.
* Replaced the route-chip "selected" colour fill with a dark theme-consistent background so light GTFS route colours (yellow, white) no longer produce unreadable white-on-light text. The route's actual colour is preserved on the chip's left border + a corner dot is added as a non-colour signal of selection.
* Hid the CPT's duplicate auto-generated admin menu now that the custom Service Alerts dashboard is complete. `show_ui` remains true so the Archive's "View" link can open the classic editor for full inspection.

= 0.9.0 =
* Added the **Archive** view at *Service Alerts → Archive* (admin only, gated on `view_nt_alerts_archive`). Filters by status (archived / expired / active / any), category, posted-by supervisor, date range, and title search. Paginated 25 per page.
* Each row links to the WordPress edit screen for the underlying alert post, giving admins the full editor view the spec describes for long-term alerts.

= 0.8.0 =
* Added four WP-Cron jobs: auto-expire (every 5 min), expiry-warning emails (every 15 min, deduped per alert), end-of-shift supervisor digest (daily 23:00), and auto-archive of long-expired alerts (daily 03:00).
* Added a channel-pluggable notification dispatcher (`NT_Alerts_Notifications`). Email is the only channel today; SMS / push / in-dashboard channels can register via the `nt_alerts_channels` filter and a `send_via_<slug>` method or `nt_alerts_send_<slug>` action.
* Extending an alert past the warning window resets its "warning sent" flag, so the supervisor gets warned again if it nears expiry a second time.
* Activation now schedules the cron events; deactivation and uninstall clear them.

= 0.7.0 =
* Added the **Settings** page at *Service Alerts → Settings*. Configurable: allowed CORS origins (textarea, one per line, no wildcards), API cache duration, embed script version pin, auto-archive threshold, default duration per category, and the routes catalogue (JSON editor with example shape).
* Embed script `?ver=` query string is now driven by the pinned version option, not the plugin version. Update the pin to bust third-party caches when shipping a breaking embed change.
* Default duration per category now flows through to the new-alert form: pick a category and the duration auto-selects (until the supervisor manually overrides it).
* Saving the routes catalogue invalidates the public alerts cache.

= 0.6.0 =
* The **Extend** button on dashboard cards is now functional. It opens a small modal with quick options (+30 min, +1 hour, +2 hours, +4 hours); picking one PATCHes the REST endpoint and updates the card's expiry display in place — no page reload, no extra confirmation step.
* Card expiry time now lives in its own line and updates live after Extend; the Posted line stays separate.
* End-now action remains a single confirmation step, as specified.

= 0.5.0 =
* Added the New Alert form at **Service Alerts → + New Alert**: single-screen, mobile-first, with category templates (Detour / Delay / Cancelled trip / Stop closure / Weather / Other), a multi-select route grid grouped by service area with per-group "Select all" toggles, duration quick-picks (1h / 2h / 4h / Rest of day / Long-term / Custom), optional description, and inline confirmation with "Post another" and "Back to dashboard".
* Title auto-fills from the chosen category and routes; supervisors can override it.
* Severity auto-derives from category (warning for detour/delay/cancelled, info for stop closure/other, critical for weather).
* Submits via the REST API with nonce authentication; the new alert busts the public cache immediately.

= 0.4.0 =
* Added supervisor dashboard at **Service Alerts** in the admin menu, with Active now / Ongoing (long-term) / Ended today sections and an "End now" button that calls the REST API.
* Supervisors land on the dashboard after login, the front-end admin bar is hidden for them, and the Dashboard / Tools / Comments menu items are trimmed.
* The **+ New Alert** button links to a placeholder; the real alert form arrives in 0.5.0, and the Extend modal in 0.6.0.
* Admins continue to see all supervisors' alerts; supervisors see only their own.

= 0.3.0 =
* Added drop-in embed: `public/embed.js` (vanilla, no dependencies) and `public/embed.css` with light/dark/auto themes and severity styling.
* Added `NT_Alerts_Assets` helper with versioned URLs and an embed-snippet generator.
* Added `embed-test.html` at the project root for cross-origin verification.

= 0.2.0 =
* Added REST endpoints: `GET /alerts/active`, `GET /alerts/route/{id}`, `GET /alerts/mine`, `POST /alerts`, `PATCH /alerts/{id}`, `POST /alerts/{id}/end`.
* Added transient cache for the active-alerts response with automatic invalidation on post/meta changes.
* Added scoped CORS handling with allowlist + optional `NT_ALERTS_DEV_CORS_ORIGINS` dev constant.
* Activator now seeds three sample alerts on first activation so the API returns data immediately.

= 0.1.0 =
* Initial scaffold: plugin bootstrap, `nt_alert` CPT with post meta, `nt_alert_supervisor` role, administrator capability extensions, option seeding with the full Niagara Transit GTFS route catalogue.
