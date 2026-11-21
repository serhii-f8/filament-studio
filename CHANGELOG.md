# Changelog

All notable changes to Filament Studio will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- **Passwords written through the REST API, MCP, Flows or code were stored in plain text** and
  returned in responses. They are now hashed in the storage layer (values the form already hashed
  are kept), and password fields are left out of API and MCP responses.

### Added

- **Collection page panels can be managed in the admin.** The collection editor has a Panels tab
  for adding, editing, reordering and deleting panels in the Collection Header/Footer and Record
  Header/Footer placements, which used to be reachable only through MCP or code. Data panels point
  at the collection by default, and only panel types that support the chosen placement are offered.
- **A trash for soft-deleted records.** Collections with soft deletes get a "Deleted records"
  filter on the record list, with Restore and Force delete row and bulk actions (gated by the
  collection's delete permission). Force delete also removes the record's values.
- **"Disabled on Create" and "Disabled on Edit" toggles in the field editor's Behavior section.**
  The options already worked but could only be set through MCP or code.
- **CSV export and import of records.** The record list has a CSV menu: Export CSV downloads the
  records currently listed (search, filters and sort applied); Import CSV creates a record per row,
  matching the header to field column names or labels and validating each row like a REST API
  write. Bad rows are skipped and reported. Exports omit passwords and defuse spreadsheet formulas.
- **Studio records in global search.** Records are matched on their text fields across every
  collection the user may view, and link to the record's view page
  (`global_search.enabled`, `global_search.limit_per_collection`).
- **A real record view page.** Records are shown as labelled, formatted values (option labels and
  colours, images, links, rendered rich text and markdown, related record titles) instead of a
  greyed-out form, and the heading is the record's title (the collection's display template, or
  its first text field) rather than the collection name. Edit pages read "Edit <title>".
  Field types can customise their entry by overriding `toInfolistEntry()`.

### Changed

- **Fields created through MCP or code are filterable by default**, like fields created in the
  field editor, so the Advanced Filter's field list is no longer empty.
- **The create form leaves out system-managed fields** (Created At/By, Updated At/By); edit and
  view still show them read-only.
- **Collection labels are singular**: a `qa_tasks` collection is "Qa Task" / "Qa Tasks" in the
  wizard and through MCP (which also accepts `label_plural`).
- **The flow run page has one Re-run button** (Replay and "Re-run with same payload" did the
  same thing). It is disabled until the flow is published and asks for confirmation when the run
  used an older version.
- **The dashboard form fills the slug from the name and uses a colour picker**; the colour shows
  in the dashboards table.

### Fixed

- **Decimals were shown padded to their storage scale** ("99.000000") in the record list. They now
  show up to the field's scale without trailing zeros ("99", "12.5"), with the field's prefix and
  suffix, on the list, the view page and List panels.
- **A List panel with a one-field display template ("{{title}}") listed record UUIDs**: the
  template went through the dashboard-variable resolver, which treats a lone `{{token}}` as a
  variable. It is now read as a field template.
- **Opening a published flow stamped its copied draft as saved** (`draft_updated_at`), so the
  designer showed it as a separate draft. The copy is no longer stamped; only real saves are.
- **Internal model names leaked into the UI** ("New studio field", "Create Studio Panel"); relation
  managers and dashboards now use plain labels.
- **The API key form forced a collection permission row**, so an MCP-only key could not be saved.
- **The collection edit page had two Audit Logs**; the header action (which pointed at a missing
  view) is gone, the tab stays. **The version history had a Submit button that did nothing**; it
  now only has Close.
- **The flow designer said "Draft (unsaved)" as soon as it opened** (React Flow's mount-time
  measuring counted as edits, and a draft copied from the published version looked unsaved), and
  **connecting lines looped around when nodes were stacked vertically**; edges now leave and
  enter on the sides facing each other.
- **The "Unique" field setting did nothing.** It is enforced in the form, the REST API and MCP,
  within the record's tenant; a record may keep its own value.
- **Invalid select values were accepted through the REST API and MCP.** Select, radio,
  multi-select and checkbox-list values must be defined options (unless the field allows creating
  options or takes them from another collection).
- **Version history showed changes backwards** — same-second snapshots are now ordered by id —
  and **the Restore button did nothing** (it pointed at a hidden action Filament refuses to mount).
  Restoring also records the restored state as the latest version.
- **Collection lists could crash with "malformed JSON".** Plain strings are now JSON-encoded before
  they reach `val_json`; `studio:repair-json-storage` (run by a migration) moves rich editor,
  markdown, file, image and avatar fields created before v1.0.3 to text storage and re-encodes
  malformed values.
- **Collections created through MCP or code were 403 for everyone.** Their record permissions are
  granted to roles holding `studio.manageFields` and to `permissions.auto_grant_roles`.
- **Collection names were unique across all tenants**; name and slug are now checked per tenant.
- **The Sort Order option was not implemented.** New records get the next position, the records
  table is drag-and-drop reorderable, and the collection's default sort is applied.
- **The Singleton option was not implemented.** A singleton opens its one record (or the form that
  creates it), and the REST API and MCP refuse a second record.
- **Fields could not be made filterable from the panel**, so table filters and the Advanced Filter
  were empty for panel-built collections. The field editor has a Filterable toggle, on by default.
- **Deleting a record ignored the collection's soft-delete setting** and always soft deleted.
- **The flow designer only worked in an app that built the package's canvas source with its own
  Vite setup.** The canvas now ships prebuilt (`resources/dist`) as a Filament asset. Publishing
  from the canvas also sends the typed change summary and saves pending edits first.
- **"Add Panel" gave no feedback**: no notification, and the panels table did not refresh.
- Field defaults (`default_value`) are applied; the Status field defaults to "draft".
- MCP errors are returned with `isError: true`; MCP reports the real package version.
- The flow run viewer and variable widget no longer depend on host Tailwind classes.
- Changing a type in the field editor or the add-panel modals no longer logs Livewire entangle
  errors.
- REST API: an invalid `per_page` falls back to the default instead of a 500; update responses
  return the fresh `updated_at`; pages are ordered by `created_at` then id; system timestamp fields
  are no longer repeated inside `data`; records created through the API or MCP get their
  collection's tenant.

## [1.9.0] - 2026-11-18

### Fixed

- **The Status system field had no options** ([#11](https://github.com/serhii-f8/filament-studio/issues/11)).
  The create-collection wizard parked Draft / Published / Archived in `settings.default_options`,
  which nothing reads; select fields load their choices from `studio_field_options`. The wizard now
  writes the option rows.
- **Created At / Updated At / Created By / Updated By were never filled.** Their `auto_fill_on` /
  `auto_fill_value` settings were stored but nothing applied them. `EavQueryBuilder::create()` and
  `update()` now fill them, so every write path (panel, REST API, MCP, Flows) is covered. They
  render read-only and are not submitted with the form, client-supplied values for them are
  overwritten, and an update with no user to credit keeps the previous Updated By. Created By /
  Updated By show the user's name in forms and tables.
- **Editing a record whose Created By was set failed with "The selected created by is invalid".**
  Fixed by the change above.
- **Saving a field in the field editor wiped its type-specific settings** (options source, a
  Belongs To field's related collection, toggles and so on). The Type-Specific Settings section now
  saves into `settings`. When a field is edited, any type setting it never stored is filled with
  the type's default instead of loading as "off".
- **No way to define static options in the panel.** The field editor now has an Options list for
  select, multi-select, radio and checkbox-list fields.
- **A new data model returned 403 to the user who created it.** Its four
  `studio.collection.{slug}.*` permissions were created but granted to nobody. The creator now gets
  them (only when spatie/laravel-permission is installed; a guard mismatch is logged, never fatal).
- **Select and Radio table columns showed the stored value instead of the option label**, and
  ignored the option's colour. Badges now show the label in the option's colour (Filament colour
  name or hex).
- **`vendor:publish --tag=filament-studio-migrations` followed by `migrate` failed with "table
  already exists"** — the step the README told every new user to run. The package auto-loads its
  migrations, so published copies ran a second time under different names. Stubs the app has
  published are now skipped by the auto-loader; newer, unpublished ones still load.
- **Dashboard panels lost their grid width on the first poll.** The span was set on a protected
  property in `mount()`, which Livewire does not persist, so every panel snapped to full width
  after the first refresh. Widgets now read it from the stored panel on every render.
- **The meter panel drew a black ring and no progress arc.** Its view relied on Tailwind classes
  that are not in the host panel's CSS, and a Filament colour name such as `success` was passed to
  SVG as-is. It now uses inline styles, maps Filament colour names to theme variables, and shows
  the percentage with value / maximum.
- **Pie and bar charts drew every slice and bar in the same pale colour** and labelled groups with
  stored values. They now use a distinct palette and option labels.
- **List panels ignored their sort order, and a display template with more than one placeholder
  (`{{name}} — {{company}}`) showed record IDs.** `EavQueryBuilder::toEloquentQuery()` now applies
  `orderBy()`, and `VariableResolver` only treats a string as a variable when it is exactly one
  placeholder. List columns use field labels instead of `ucfirst()` of the column name.
- **Flow, run and step status badges were all the same colour.** The status enums now implement
  Filament's `HasColor` / `HasLabel` (completed = green, failed = red, and so on).

### Changed

- The field editor's Options list is full width with a compact table layout.
- Long textarea values are clamped to two lines in tables.
- New README screenshots and preview image, taken from the plugin running in a real app.

### Upgrading

- Run `php artisan migrate`. The new `z_repair_system_fields` migration runs
  `studio:repair-system-fields`, which turns legacy `default_options` into option rows and
  back-fills empty Created/Updated At/By values from each record's own `created_at`, `updated_at`,
  `created_by` and `updated_by` columns. It only fills what is missing, never overwrites, and is
  safe to run again by hand.

## [1.8.1] - 2026-09-22

### Fixed

- **A reused MCP confirm token was reported as expired rather than consumed.** Consuming a token
  deleted its cache entry, so a second `studio_delete_collection` / `studio_delete_field` /
  `studio_delete_dashboard` call with the same token found nothing and returned
  `EXPIRED_CONFIRM_TOKEN` — telling the agent to preview and retry an operation that had already
  run. `ConfirmTokenInvalidException::consumed()` existed and was unit-tested but was never thrown.
  Consuming a token now leaves a tenant-scoped marker for the token's TTL, and a replay returns
  `CONSUMED_CONFIRM_TOKEN`. The marker is claimed with an atomic `Cache::add`, so two concurrent
  calls with one token can no longer both pass validation. A token from another tenant still
  reports as expired, revealing nothing.

## [1.8.0] - 2026-09-09

Found by running the Flows engine end-to-end against a real Laravel host app — real HTTPS webhook
deliveries, a real queue worker, a real MySQL database and the panel driven in a browser — rather
than only through the test suite. Fourteen defects surfaced, none of which the existing suite
covered. Every fix below ships with a test written to fail first.

### Security

- **Webhook HMAC verification could be satisfied without knowing the secret.** `webhook_auth_mode`
  defaults to `hmac`, but the secret was only generated when the *trigger node config* carried
  `auth_mode: hmac` — and `WebhookTriggerConfig` never declared such a field, so the ordinary path
  left `webhook_secret` NULL. `HmacWebhookVerifier` then verified against `(string) null === ''`,
  and any caller could compute `hash_hmac('sha256', "{ts}.{body}", '')`. An endpoint that presented
  as signed accepted unsigned traffic. The verifier now rejects an empty secret outright, and
  `WebhookTrigger::register()` reads the authoritative `webhook_auth_mode` column, so a flow in
  HMAC mode always has a secret. **Check any flow whose webhook was reachable: if
  `webhook_secret` was NULL while `webhook_auth_mode` was `hmac`, that endpoint was effectively
  public.**

- **`webhook_redact_paths` did not cover the stored raw body.** Redaction and key-pattern masking
  reached only the parsed `body`/`headers`, so a value scrubbed from `body` was still persisted
  verbatim under `trigger_payload.raw` on the run. `raw` is now re-encoded from the sanitized body
  whenever the body parsed.

- **Flow secrets were never injected.** `studio_flow_secrets` rows existed and decrypted, and
  `FlowContext` had a `$secrets` slot, but the engine never populated it — `{{ $secrets.KEY }}`
  always rendered empty, so anyone relying on it was sending an empty credential. See *Added*.

### Added

- **Flow secrets now reach operation configs.** A new `ResolveFlowSecrets` service loads a flow's
  encrypted secrets as `key => value`; both `FlowWorkflow` and `StepThroughExecutor` inject them,
  and `{{ $secrets.KEY }}` resolves. Three properties come with it: resolved secret values are
  scrubbed out of every string leaf before a step is persisted (key patterns alone cannot protect a
  secret interpolated into a URL or an innocuously named field); `FlowContext::toCache()` no longer
  carries secrets, since a paused step-through run parks that blob in the cache store; and a
  sub-flow resolves its own secrets, so a parent never lends credentials to a flow it triggers.

- **`studio_flow_run_steps.duration_ms`**, measured by the engine. `started_at`/`finished_at` are
  second-precision timestamps, so a sub-second step always rendered as `0ms`. The run detail page
  falls back to the timestamp delta for steps recorded before this column existed.

### Fixed

- **`collection_event` triggers silently stopped firing after any cache flush.**
  `EventSubscriptionRegistry` stored subscriptions with `Cache::forever`, but "forever" in a cache
  store is not durability: a deploy running `php artisan optimize:clear`, an eviction, or a
  restarted cache server emptied the index, and every collection-event flow stopped firing with
  nothing logged and nothing shown in the UI. Recovery required re-publishing each flow. The cache
  is now a read-through index over the published versions that declare a `collection_event`
  trigger, and a missing entry rebuilds from the database.

- **The flows REST API was unreachable whenever the collection API was also enabled.** The
  collection routes are a one- and two-segment catch-all under the same prefix, so
  `GET /api/studio/flows` resolved to `StudioApiController@index` and
  `GET /api/studio/webhooks/{slug}` answered with the collection guard's 401 instead of 405. Which
  API answered depended purely on service-provider boot order.
  `StudioApiRouteRegistrar::RESERVED_SEGMENTS` (`flows`, `webhooks`) now constrains
  `{collection_slug}`. **A collection slugged `flows` or `webhooks` is no longer addressable
  through the collection API.**

- **The Studio Dashboard page was unreachable for every user.** `StudioDashboardPage::canAccess()`
  required `View:StudioDashboardPage`, a permission name registered nowhere in the package, so the
  page and its navigation items returned 403 on any install using spatie/laravel-permission. It now
  checks `ViewAny:StudioDashboard`, matching `StudioDashboardPolicy::viewAny()`.

- **Form-encoded webhook bodies silently produced empty records.** The controller parsed the body
  only when `$request->isJson()`, so an `application/x-www-form-urlencoded` delivery left `body` a
  raw string, `{{ $trigger.body.x }}` resolved to `''`, and the run *completed successfully* having
  written a blank record. Form-encoded bodies are now parsed; anything else stays a raw string and
  remains reachable through the payload's `raw` key.

- **The run detail timeline listed steps out of execution order.** Sorting used `started_at` alone,
  which is second-precision, so every step of a fast run tied and the order fell through to the
  `(flow_run_id, operation_key)` index — alphabetical. It now tie-breaks on the monotonic UUIDv7
  primary key.

- **A synchronous `trigger_flow` swallowed child failures.** The parent step reported success
  regardless of how the child run ended. A failed child now takes the `failure` branch, matching how
  `http_request` surfaces an error response.

- **Trigger config schemas disagreed with the runtime that reads them**, so a flow configured from
  its own declared schema did not work:
  - `CollectionEventTriggerConfig` declared `collection_id` + `event`; the trigger reads
    `collection` + `events`. Such a flow never subscribed.
  - `ScheduleTriggerConfig` declared `cron_expression`; the trigger and the dispatch command read
    `cron`. Such a flow **failed to publish** with `Invalid cron expression:`.
  - `WebhookTriggerConfig` declared `secret_key` + `expected_method`, neither of which anything
    reads. The webhook node now declares no config at all: auth mode, secret, API-key allowlist and
    redact paths are properties of the flow record, edited in `FlowResource`'s Webhook Security
    section. The designer's `triggerConfigSchemas.webhook` was emptied to match, and
    `WebhookTrigger::register()` no longer consults node config, leaving one source of truth.

- **The audit log recorded `updated` for every lifecycle change.** Draft saves, publishes and
  rollbacks were indistinguishable except by inspecting metadata. They now record `draft_saved`,
  `published` and `rolled_back`.

### Upgrading

Run `php artisan migrate` to add `studio_flow_run_steps.duration_ms`. No configuration changes are
required. Two behaviour changes to be aware of: a collection slugged `flows` or `webhooks` is no
longer reachable through the collection REST API, and a webhook flow in HMAC mode with no secret
now returns 401 rather than accepting the request — publish the flow again to have its secret
generated.

## [1.7.1] - 2026-09-08

### Fixed

- **Graph edges without a `sourceHandle` no longer dead-end silently.** `GraphWalker::successors()`
  matched the branch handle exactly, so an edge stored as `{source, target}` — the shape produced
  by hand-written graphs, seeders, and the REST API — never matched the `success` branch the engine
  asks for. The walk stopped at the trigger and the run was recorded as **completed in 0 ms with
  zero steps**, giving no indication that nothing had run. A missing or null `sourceHandle` is now
  treated as a success edge.

  Graphs built in the flow designer were never affected — its nodes declare named `success` and
  `failure` handles, so every edge it draws carries one. Existing published graphs are unchanged;
  this only makes previously inert edges execute as intended. Fixes both normal runs
  (`FlowWorkflow`) and step-through debugging (`StepThroughExecutor`), which share the walker.

## [1.7.0] - 2026-09-08

### Removed

- **`durable-workflow/workflow` is no longer a dependency.** It was declared as a hard `require`
  but never used: nothing in `src/`, `tests/`, `database/`, or `config/` referenced its `Workflow\`
  namespace, its classes, its namespaced helper functions, its tables, or its config, and its
  service provider was never registered by the test harness. The Flows engine is self-contained —
  `FlowWorkflow` walks the operation graph itself and `ExecuteFlowJob` is a plain Laravel queued
  job, so durability comes from Laravel queues plus the `studio_flow_run*` tables. Verified by
  removing the package and running the full suite: 1785 passed, 1 skipped, 4323 assertions —
  identical to the run with it installed.

  This drops three packages from every install — `durable-workflow/workflow` and its transitive
  `apache/avro` and `react/promise` — along with roughly thirty unused migrations. **No code
  changes are required.** If your own application uses `durable-workflow/workflow` and was
  relying on Filament Studio to pull it in, add it to your own `composer.json`.

### Fixed

- Corrected the architecture note describing Flows as "built on `durable-workflow/workflow`"
  (introduced in v1.4.0). That was never accurate; the engine has always been self-contained.

## [1.6.0] - 2026-09-08

### Changed

- **`durable-workflow/workflow` constraint widened to `^1.0 || ^2.0`.** Version 2.x is now
  accepted alongside 1.x. Existing installs are unaffected — nothing is forced to upgrade.
- **`laravel/mcp` constraint widened to `^0.3 || ^0.5 || ^0.6 || ^0.8 || ^0.9`.** Version 0.9 is
  now accepted. Existing installs are unaffected.
- Third-party GitHub Actions in the test workflow are pinned to full commit SHAs
  (`actions/checkout@3d3c42e` / v7.0.1, `shivammathur/setup-php@f3e473d` / v2.37.2) so a
  retargeted or compromised tag cannot change what CI executes.
- Dependabot now waits before opening an update PR — 3 days for patches, 7 for minors, 14 for
  majors — across both the Composer and GitHub Actions ecosystems.

## [1.5.1] - 2026-09-08

### Changed

- Package author is now Serhii Fedorenko; the former Flexpik name, `info@flexpick.net`
  address, and `flexpick.net` homepage have been removed from `composer.json` and the
  README credits. The PHP namespace `Flexpik\FilamentStudio\` is **unchanged**, as is the
  `replace` entry for `flexpik/filament-studio` — no code changes are required.
- Security reports now go through GitHub's private vulnerability reporting instead of the
  retired `security@flexpik.com` address.

## [1.5.0] - 2026-09-08

### Added

- **User guide for non-technical users** (`docs/user-guide/`) — a twelve-part, screenshot-led
  walkthrough written for the people who use a Studio panel rather than the people who install
  it: signing in, collections, fields (all 33 types grouped by what you want to store), records,
  search and filtering, dashboards, version history, permissions, automations, API keys, and a
  glossary. Linked from the README.

### Changed

- **README rewritten** to lead with the problem it solves rather than a feature list, with
  concrete use cases, an honest "when not to use it" section, and refreshed screenshots
  captured against the current UI.
- **Package renamed to `serhii-f8/filament-studio`.** The repository moved to
  [github.com/serhii-f8/filament-studio](https://github.com/serhii-f8/filament-studio) and the Composer package
  follows it. The PHP namespace is unchanged — `Flexpik\FilamentStudio\` stays exactly as it is, so **no code
  changes are required**: your imports, config keys, table prefixes, and published assets all keep working.

  To upgrade:

  ```bash
  composer remove flexpik/filament-studio
  composer require serhii-f8/filament-studio
  ```

  The new package declares `replace: { "flexpik/filament-studio": "self.version" }`, so any dependency still
  requiring the old name resolves against the new one and the two can never be installed side by side.
  Releases up to and including v1.4.2 remain available under the old name; everything from here on is published
  under the new one.
- Repository, issue tracker, homepage, and documentation links now point at the `serhii-f8` account. The old
  GitHub URLs redirect, so existing links continue to resolve.
- Copyright holder in `LICENSE` and `LICENSE.md` updated to Serhii Fedorenko. The license itself is unchanged (MIT).

### Fixed

- **Record list search now returns results.** The search box on a dynamic collection's
  record list matched nothing for any term, including exact field values. Table columns
  were registered with Filament's default `searchable()`, which emitted
  `where "<column_name>" like ?` against `studio_records` — but EAV values live in
  `studio_values` and are only exposed on the record query as correlated subquery
  aliases, which SQL cannot reference in a `WHERE` clause. On SQLite the unresolved
  identifier silently degraded to a string literal (always false, hence zero rows); on
  MySQL the same query would fail with `Unknown column`. Columns now supply a search
  query that matches against the values table via `whereExists`.

## [1.4.2] - 2026-08-29

### Changed

- No functional changes from v1.4.1; supersedes it with a clarified changelog entry below.

## [1.4.1] - 2026-08-29

### Removed

- Internal planning and specification documents that were accidentally included in the v1.4.0 package distribution. No source code changes from v1.4.0.

## [1.4.0] - 2026-08-29

### Added

- **Flows (automation engine)** — a self-contained workflow subsystem (`src/Flows/`), opt-in via `flows.enabled` config, built on `durable-workflow/workflow`. Flows are edited as a draft and published into an immutable `StudioFlowVersion`; runs form a traceable tree (`StudioFlowRun`/`StudioFlowRunStep`) with observability columns for duration, status, and failure tracking.
- **Triggers**: Manual, Webhook (HMAC-signed with timestamp window + IP allowlist, rate-limited), Collection Event (create/update/delete via `RecordLifecycleObserver`), and Schedule (cron, dispatched by `studio:flows:dispatch-scheduled`).
- **Operations** by category — Records (create/read/update/delete), Logic (condition, log message), Data (transform payload), Communication (HTTP request, send email), Composition (trigger another flow, depth-limited by `flows.max_call_depth`).
- **Engine**: `GraphWalker` topologically executes the operation graph; `TemplateEngine` interpolates config tokens against the run's data chain; `DryRunExecutor` and `StepThroughExecutor` power canvas dry-run and step-through debugging.
- **Security**: `LogMaskingService` redacts values matching `flows.sensitive_key_patterns` before persisting step logs; per-flow encrypted secrets (`StudioFlowSecret`) injected into operation configs; safety gates before publishing a dangerous graph or enabling a public webhook.
- **Flow designer UI** — `FlowResource` (canvas-based `DesignFlow` page), run list/viewer, and dashboard widgets (recent runs, failure rate, duration, top failing).
- **REST API** for flows, registered by `StudioFlowsApiRouteRegistrar` behind `flows.enabled`.
- **Audit log** (`studio_flow_audit_log`) recording security/lifecycle events.
- **Plugin API** — third parties register custom operations/triggers via `FilamentStudioPlugin::registerFlowOperation()` / `::registerFlowTrigger()`.
- New MCP scope `_studio.flows` ("Manage Flows") for MCP-driven flow management.
- `BackfillFlowVersioningCommand` to migrate pre-versioning (MVP) flows to the draft/published model.

### Changed

- Added `durable-workflow/workflow` (`^1.0`) as a new required dependency.

## [1.3.2] - 2026-08-29

### Changed

- Widened `laravel/mcp` to `^0.3 || ^0.8`, `spatie/laravel-permission` to `^7.2 || ^8.0`, and `orchestra/testbench` (dev) to `^10.0 || ^11.0`, so the package also installs alongside these dependencies' newer major versions ([#5](https://github.com/serhii-f8/filament-studio/pull/5), [#4](https://github.com/serhii-f8/filament-studio/pull/4), [#2](https://github.com/serhii-f8/filament-studio/pull/2)).
- Bumped `actions/checkout` from v4 to v7 in the test workflow ([#6](https://github.com/serhii-f8/filament-studio/pull/6)).

## [1.3.1] - 2026-08-29

### Fixed

- Dashboard panel creation ("The collection field is required.", "The display Template field is required.") — required fields inside a panel type's `configSchema()` (e.g. `collection_id`, `display_template`) that only appear after a Panel Type is selected now declare an explicit default. Without it, Livewire cannot bind the reactively-added field on the client, so the value is visibly filled in but never reaches the server. Affects all built-in panel types (Metric, List, Time Series, Bar Chart, Line Chart, Pie Chart, Meter, Label, Variable).

## [1.3.0] - 2026-05-10

### Added
- MCP server foundation (`src/Mcp/`) — opt-in via `mcp.enabled` config flag.
- Both HTTP/SSE (mounted at `/ai/studio`) and stdio (`php artisan mcp:start studio`) transports.
- Reuse of `StudioApiKey` for MCP auth with a new `_studio.*` management-scope namespace (`manage_collections`, `manage_dashboards`, `manage_filters`, `manage_api_keys`, `read_schema`).
- Six capability-discovery Resources: `studio://info`, `studio://field-types`, `studio://field-types/{key}`, `studio://panel-types`, `studio://panel-types/{key}`, `studio://operators`.
- "MCP Management Scopes" section on the API Key edit form (visible only when `mcp.enabled`).
- New `studio-mcp` rate limiter (per X-Api-Key, configurable via `mcp.http.rate_limit`).
- 12 schema-management MCP tools: `studio_list_collections`, `studio_get_collection`, `studio_create_collection`, `studio_update_collection`, `studio_preview_delete_collection`, `studio_delete_collection`, `studio_create_field`, `studio_update_field`, `studio_preview_delete_field`, `studio_delete_field`, `studio_reorder_fields`, `studio_set_field_options`.
- Cache-backed per-tenant confirm-token flow for destructive collection/field deletes (5-minute TTL, one-time use, configurable via `mcp.confirm_token_ttl`).
- Action layer (`Mcp/Actions/`) for collection/field/field-option mutations — framework-agnostic, reusable.
- `StudioMcpExceptionHandler` mapping domain exceptions to JSON-RPC error codes per §6 of the design spec.
- `McpSerializer` for canonical entity shapes shared across all schema-management tools.
- Domain exception classes: `ConfirmTokenInvalidException`, `EavCastConflictException`, `IntegrityException`, `StudioNotFoundException` — each with `mcpCode()` and `mcpData()` for uniform handler calls.
- `mcpCallTool($apiKey, $toolClass, $input)` Pest helper for MCP tool tests.
- End-to-end HTTP integration test (`SchemaDesignFlowTest`) covering create→field→preview→delete flow.
- Cross-tenant confirm-token isolation test.
- **MCP Phase 3 (runtime tools).** 22 new tools complete the v1 MCP surface:
  - Records: `studio_query_records`, `studio_get_record`, `studio_create_record`, `studio_update_record`, `studio_delete_record`.
  - Dashboards: `studio_list_dashboards`, `studio_get_dashboard`, `studio_create_dashboard`, `studio_update_dashboard`, `studio_preview_delete_dashboard`, `studio_delete_dashboard` (confirm-token flow).
  - Panels: `studio_create_panel`, `studio_update_panel`, `studio_delete_panel`, `studio_reorder_panels`.
  - Saved filters: `studio_list_saved_filters`, `studio_save_filter` (upsert), `studio_delete_saved_filter`.
  - API keys: `studio_list_api_keys`, `studio_get_api_key`, `studio_create_api_key` (returns secret once), `studio_revoke_api_key`.
- McpSerializer extended with `record()`, `dashboard()`, `panel()`, `savedFilter()`, `apiKey()` shapes.
- End-to-end stdio smoke test asserting the full 34-tool surface.

## [1.2.0] - 2026-04-15

### Added

- **Multilingual Support** — Opt-in per-locale content for translatable fields, controlled by a global `locales.enabled` config toggle
- **Locale Column on EAV Values** — `studio_values` table gains a `locale` column with a composite unique constraint `[record_id, field_id, locale]`, enabling per-locale storage without schema-per-field changes
- **Per-Field Translatable Flag** — Each field can be individually marked as translatable via the `is_translatable` toggle in the field editor; non-translatable fields store a single value regardless of locale
- **Per-Collection Locale Settings** — Collections define their own `supported_locales` subset and a `default_locale` for fallback behavior
- **LocaleResolver Service** — Centralized locale detection with a 4-level priority chain: `?locale=` query param > `X-Locale` header > session > collection/global default
- **Locale-Aware EavQueryBuilder** — New `locale()` fluent method on the query builder. `create()`, `update()`, `getRecordData()`, and `toEloquentQuery()` all respect the active locale with automatic fallback to the default locale for missing translations
- **Fallback Metadata** — `getRecordDataWithMeta()` returns both data and a `fallbacks` array indicating which fields are displaying fallback values from the default locale
- **All-Locale Data Retrieval** — `getAllLocaleData()` returns translatable fields as nested locale maps (`{"en": "Hello", "fr": "Bonjour"}`) and non-translatable fields as plain values
- **Admin Locale Switcher** — Livewire-powered locale toggle buttons displayed in the record edit page header; switching locale persists to session and reloads form data for the selected locale
- **Collection Multilingual Toggle** — New "Multilingual" section in collection settings with locale multi-select and default locale picker, only visible when `locales.enabled` is true
- **Field Translatable Toggle** — "Translatable" toggle in the field behavior settings section, only visible when the parent collection has multilingual enabled
- **REST API Locale Support** — API endpoints accept `?locale=` query param or `X-Locale` header for locale-specific reads/writes; responses include `_meta.locale` and `_meta.fallbacks` metadata
- **API All-Locales Mode** — `GET ?all_locales=true` returns translatable fields as nested locale objects in a single response
- **OpenAPI Locale Documentation** — API docs now show `locale`, `X-Locale`, and `all_locales` parameters with enum dropdowns when multilingual is enabled, plus `_meta` response schema and locale hints in operation descriptions
- **Multi-Locale Version Snapshots** — Record version snapshots capture all locale values for translatable fields as nested objects; version restore correctly writes back all locale rows
- **UI Metadata Translations** — Field labels, placeholders, and hints resolve from the `translations` JSON column per active locale via `StudioField::getTranslatedAttribute()`

### Changed

- **EavQueryBuilder** `getRecordData()` now delegates to `getRecordDataWithMeta()` internally
- **RecordVersioningObserver** `buildSnapshot()` produces nested locale structures for translatable fields
- **RecordResource** API serialization is now locale-aware, resolving the active locale from the request
- **StudioApiController** `show()`, `store()`, and `update()` endpoints return JSON responses with `_meta` locale metadata
- **StudioDocumentTransformer** conditionally adds locale parameters and `_meta` response schemas when multilingual is enabled

## [1.1.0] - 2026-04-11

### Added

- **Spatie Permission Integration** — Automatic per-collection permission sync when `spatie/laravel-permission` is installed. Each collection gets four granular permissions (`viewRecords`, `createRecord`, `updateRecord`, `deleteRecord`) under the `studio.collection.{slug}.*` namespace
- **StudioPermission Enum** — Single source of truth for all permission strings, with helpers for generating collection-specific permission names and labels
- **PermissionRegistrar Service** — Detects Spatie Permission at runtime, syncs global and per-collection permissions, and handles collection renames by removing old and creating new permission entries
- **Auto-Sync via Observer** — Permissions are automatically created when a collection is created, updated on rename, and removed on deletion
- **Policy Enforcement** — `StudioCollectionPolicy` checks per-collection permissions for view, create, update, and delete operations. `StudioApiKeyPolicy` and `StudioDashboardPolicy` enforce global permission checks
- **UI Permission Enforcement** — Navigation items, Create/Edit/Delete actions, and dashboard pages are hidden when the user lacks the required permission
- **Collection Permissions Screenshot** — Added `art/collection-permissions.png` showing the per-collection RBAC interface

### Changed

- **Policies refactored** to use `StudioPermission` enum instead of hardcoded permission strings
- **Dashboard page** uses Shield page permission for access control

## [1.0.3] - 2026-04-04

### Added

- **Dashboard Panels Relation Manager** — Manage panels directly within dashboard edit pages with full CRUD, ordering, and panel type configuration
- **PanelsRelationManagerTest** — 174-line test covering panel creation, editing, deletion, and listing within dashboards
- **Inline Filter Builder Views** — Alpine-based filter builder variants (`filter-builder-alpine`, `filter-builder-inline`, `filter-group-inline`, `filter-rule-row-inline`) for alternative filter UX

### Changed

- **Filter Builder** rewritten with improved UX — enhanced filter-builder, filter-group, and filter-rule-row Blade views
- **Version History View** — Layout and display improvements
- **EavQueryBuilder** — Expanded query capabilities and performance refinements
- **Collection Record Pages** — Improved Edit, List, and View pages with better layout and functionality
- **API Settings Resource** — Updated resource and ListApiKeys page
- **Field Types** improved: Avatar (validation), CheckboxList (options handling), File (upload config), Image (preview), MultiSelect (search), Password (confirmation), RichEditor (toolbar), Tags (separators)
- **Dashboard Panel Types** refined: BarChart, LineChart, List, Meter, Metric, PieChart, TimeSeries — consistent configuration patterns
- **StudioRecordVersion** model updates

### Fixed

- **Concurrency test reliability** — Fixed timing issues in DeleteWithIntegrityRace and HardDeleteRace tests
- **API cross-tenant access** — Test cleanup for ApiCrossTenantAccessTest
- **RecordResource test** — Removed redundant assertion
- **RichEditorFieldType test** — Fixed expected toolbar config

## [1.0.2] - 2026-03-28

### Added

- **Per-Collection API Documentation** — Each collection now has an `api_enabled` toggle in the Data Models editor. When enabled, the collection's CRUD endpoints appear in the OpenAPI/Swagger documentation at `/docs/api` with field-specific request/response schemas
- **Dynamic OpenAPI Schema Generation** — `StudioDocumentTransformer` generates per-collection paths with typed schemas derived from each collection's field definitions, including proper EAV cast mapping (text→string, integer→integer, decimal→number, boolean→boolean, datetime→date-time, json→object)
- **API Key Display & Copy** — After creating or regenerating an API key, the plain key is shown in a dedicated form section with a monospace input and Filament's built-in copy-to-clipboard button
- **Regenerate Key Action** — "Regenerate Key" button on the API key edit page with confirmation dialog; immediately invalidates the old key
- **`api_enabled` column** on `studio_collections` table with migration, model cast, `scopeApiEnabled()` query scope, and `apiEnabled()` factory state

### Fixed

- **API route registration boot order** — Fixed timing issue where `FilamentStudioPlugin::boot()` set the API config after the service provider's `booted()` callback had already checked it, preventing routes from registering
- **OpenAPI path prefix duplication** — Stripped Scramble's `api_path` prefix from generated paths to avoid double-prefixed URLs (e.g., `/api/api/studio/...`) that caused 404s from the Try It button
- **API gate for disabled collections** — `StudioApiController::resolveCollection()` now rejects requests to collections with `api_enabled=false` (returns 404)
- **Duplicate X-Api-Key parameters** — `StudioOperationTransformer` skips operations that already have the header parameter added by the document transformer

## [1.0.1] - 2026-03-28

### Changed

- Minimum PHP version raised from 8.2 to 8.3 (required by Pest v4)
- Added package metadata: `authors`, `homepage`, and `support` to `composer.json`
- Fixed release date for v1.0.0 in CHANGELOG

## [1.0.0] - 2026-03-27

### Added

- **Dynamic Collections** — Create and manage data collections at runtime through the Schema Manager
- **33 Built-in Field Types** across 9 categories: text, numeric, boolean, selection, date/time, file, relational, structured, and presentation
- **EAV Storage Engine** with 6 typed value columns (`val_text`, `val_integer`, `val_decimal`, `val_boolean`, `val_datetime`, `val_json`)
- **Dynamic Form Builder** — Auto-generates Filament forms from field definitions with validation, conditional visibility, and sections
- **Dynamic Table Builder** — Auto-generates sortable, searchable, and filterable table columns
- **Dashboard Builder** with 9 panel types: Metric, List, Time Series, Bar Chart, Line Chart, Pie Chart, Meter, Label, and Variable
- **Panel Placement System** — Place panels in 5 contexts: Dashboard, Collection Header/Footer, Record Header/Footer
- **Advanced Filtering** with 22 operators, saved filters, and dynamic value resolution
- **Record Versioning** — Optional snapshot-based version history
- **Multi-Tenancy** — Tenant-scoped collections, records, and dashboards
- **Hook System** — Lifecycle hooks for `afterCollectionCreated`, `afterFieldAdded`, `modifyFormSchema`, `modifyTableColumns`, and `modifyQuery`
- **Custom Condition Resolvers** for dynamic field visibility, required, and disabled states
- **Dynamic Variables** — Runtime token resolution (`$CURRENT_USER`, `$CURRENT_TENANT`, `$NOW`, etc.)
- **Policy-based Authorization** on collections
- **Activity Logging** — Optional integration with `spatie/activitylog`
- **Soft Deletes** — Optional soft delete support for records
- **Extensible Architecture** — Register custom field types, panel types, and condition resolvers via the plugin API
- **Configurable Table Prefix** to avoid naming conflicts
- **Migration Log Tracking** for schema change auditing

[Unreleased]: https://github.com/serhii-f8/filament-studio/compare/v1.9.0...HEAD
[1.9.0]: https://github.com/serhii-f8/filament-studio/compare/v1.8.1...v1.9.0
[1.8.1]: https://github.com/serhii-f8/filament-studio/compare/v1.8.0...v1.8.1
[1.8.0]: https://github.com/serhii-f8/filament-studio/compare/v1.7.1...v1.8.0
[1.7.1]: https://github.com/serhii-f8/filament-studio/compare/v1.7.0...v1.7.1
[1.7.0]: https://github.com/serhii-f8/filament-studio/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/serhii-f8/filament-studio/compare/v1.5.1...v1.6.0
[1.5.1]: https://github.com/serhii-f8/filament-studio/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/serhii-f8/filament-studio/compare/v1.4.2...v1.5.0
[1.2.0]: https://github.com/serhii-f8/filament-studio/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/serhii-f8/filament-studio/compare/v1.0.4...v1.1.0
[1.0.3]: https://github.com/serhii-f8/filament-studio/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/serhii-f8/filament-studio/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/serhii-f8/filament-studio/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/serhii-f8/filament-studio/releases/tag/v1.0.0
