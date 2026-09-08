<p align="center">
    <img class="filament-hidden" src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/preview.png" alt="Filament Studio — Plugin Preview" style="width: 100%; max-width: 800px;" />
</p>

<p align="center">
    <a href="https://packagist.org/packages/serhii-f8/filament-studio"><img src="https://img.shields.io/packagist/v/serhii-f8/filament-studio.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://packagist.org/packages/serhii-f8/filament-studio"><img src="https://img.shields.io/packagist/dt/serhii-f8/filament-studio.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://github.com/serhii-f8/filament-studio/actions"><img src="https://img.shields.io/github/actions/workflow/status/serhii-f8/filament-studio/tests.yml?branch=main&label=tests&style=flat-square" alt="Tests"></a>
    <a href="https://github.com/serhii-f8/filament-studio/blob/main/LICENSE.md"><img src="https://img.shields.io/packagist/l/serhii-f8/filament-studio.svg?style=flat-square" alt="License"></a>
</p>

# Filament Studio

**Let your users build their own data structures — inside the Filament panel you already ship.**

Filament Studio adds a visual data model manager to any Filament v5 panel. Your team (or your client) creates collections and fields through the admin UI, and Studio generates the forms, tables, filters, dashboards, REST API and automations for them — at runtime, with no migration and no deploy.

---

## The problem

It's 5pm on Friday. Your client emails: *"Can we add a 'Preferred Contact Method' to the customer form? And a report of customers by region?"*

<table>
<tr>
<th width="50%">Without Filament Studio</th>
<th width="50%">With Filament Studio</th>
</tr>
<tr>
<td>

1. Write a migration
2. Update the model's `$fillable` and casts
3. Add the field to the Filament resource form
4. Add a table column
5. Add a filter
6. Add validation rules
7. Write the report query and widget
8. Commit, review, deploy
9. Repeat for the next request, forever

</td>
<td>

1. The client clicks **Add Field**
2. The client builds the report from a panel picker

You are not in the loop.

</td>
</tr>
</table>

Every "just one more field" request stops being a deploy. The schema becomes data your users own, instead of code you maintain.

---

## See it in action

**Define a collection's fields through the UI — no migration:**

![Fields list](https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/fields-list.png)

**Every collection gets a full CRUD interface, generated from those fields:**

![Records list](https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/records-list.png)

**Data changes can trigger real automation, designed on a visual canvas:**

![Flow designer](https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/flow-designer.png)

<sub>More screenshots in the [gallery](#screenshot-gallery) at the bottom, and step-by-step walkthroughs in the [User Guide](docs/user-guide/README.md).</sub>

---

## Who it's for

**Agencies shipping client admin panels.** Hand the client a panel they can extend themselves. The "can you add a field" emails stop.

**Internal tools teams.** Ops, finance and support each want their own tracker. Give them one panel and let each team model its own data instead of queueing behind your sprint.

**Headless CMS backends.** Editors define content types in the UI; your frontend reads them over the REST API with per-collection API keys.

**Multi-tenant SaaS.** Every tenant gets its own collections, records, dashboards and API keys, isolated by `tenant_id` across every model.

## When *not* to use it

Being honest saves you a refactor later. Reach for regular Eloquent models instead when:

- **The schema is known and stable.** If you already know you need `orders` with fifteen fixed columns, a migration is simpler, faster and easier to query.
- **You need heavy analytical queries.** EAV stores each value in its own row, so wide reporting queries mean many joins. Studio is built for operational CRUD, not for a data warehouse.
- **You depend on database-level constraints across fields.** Composite foreign keys, multi-column unique indexes and check constraints don't translate to EAV storage.

Studio and hand-written resources coexist fine in one panel — use each where it fits.

---

## Quick Start

```bash
composer require serhii-f8/filament-studio
php artisan vendor:publish --tag="filament-studio-migrations"
php artisan migrate
```

Register the plugin on your panel:

```php
use Flexpik\FilamentStudio\FilamentStudioPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentStudioPlugin::make(),
    ]);
}
```

Open your admin panel — there's a new **Studio** section in the sidebar. Create a collection, add a few fields, and you have a working CRUD interface.

Optional configuration:

```php
FilamentStudioPlugin::make()
    ->navigationGroup('Content')
    ->enableVersioning()
    ->enableSoftDeletes()
    ->enableApi()
    ->fieldTypes(['currency' => CurrencyFieldType::class])
    ->panelTypes([CustomMapPanel::class]);
```

```bash
php artisan vendor:publish --tag="filament-studio-config"
```

---

## What you get

### Collections and fields, defined at runtime

Create a collection, add fields, and Studio generates the form, table, filters and validation. Fields can be reordered, made required or unique, hidden from forms or tables, and shown conditionally based on other values.

**33 field types across 9 categories:**

| Category | Types |
|----------|-------|
| Text | Text, Textarea, Rich Editor, Markdown, Password, Slug, Color, Hidden |
| Numeric | Integer, Decimal, Range |
| Boolean | Checkbox, Toggle |
| Selection | Select, Multi-Select, Radio, Checkbox List, Tags |
| Date & Time | Date, Time, Datetime |
| File | File, Image, Avatar |
| Relational | Belongs To, Has Many, Belongs To Many |
| Structured | Repeater, Builder, Key-Value |
| Presentation | Section Header, Divider, Callout |

### Dashboards your users build themselves

Non-developers assemble dashboards from a panel picker — no widget classes to write. **9 panel types** (Metric, List, Time Series, Bar, Line, Pie, Meter, Label, Variable) can be placed on dashboards, collection pages or record pages, with aggregate functions and dynamic variables like `$CURRENT_USER` and `$NOW`.

### Filtering that survives real questions

A visual filter builder with **23 operators** and nested AND/OR groups, so "customers in Germany who are either overdue or high-value" is a filter, not a support ticket. Operators adapt to the field type, and useful filters can be saved and shared with the team.

### A REST API you didn't have to write

Turn on the API and every collection gets CRUD endpoints with API key auth, per-collection permissions, rate limiting and OpenAPI documentation generated via Scramble.

### Workflow automation (Flows)

Data changes can start real work. Triggers (manual, webhook, collection event, cron schedule) kick off a graph of operations — record CRUD, conditions, payload transforms, HTTP requests, emails, or another flow.

- **Draft → publish versioning** — edit a live draft, dry-run and step through it, then publish an immutable, restorable version
- **Full observability** — every run records a step tree of inputs, outputs, timing and status
- **Security by default** — HMAC-signed, rate-limited, IP-allowlisted webhooks; dangerous operations need explicit publish-time confirmation; sensitive values are masked before logs are written

Flows are opt-in via `flows.enabled`. See the [Flows documentation](docs/flows.md).

### AI-native (MCP server)

A built-in [Model Context Protocol](https://modelcontextprotocol.io/) server lets Claude, Cursor or Windsurf manage your data model in natural language — **34 tools** covering schema design, data access, dashboards and administration.

```json
{
  "mcpServers": {
    "filament-studio": {
      "type": "stdio",
      "command": "php",
      "args": ["artisan", "mcp:start", "studio"],
      "env": { "STUDIO_API_KEY": "your-key", "STUDIO_MCP_ENABLED": "true" }
    }
  }
}
```

### Built for production

- **Multi-tenancy** — every collection, record, dashboard and API key is scoped to its tenant
- **Multilingual** — opt-in per-locale values with automatic fallback, an admin locale switcher, and locale-aware API responses
- **Versioning & soft deletes** — snapshot history with one-click restore, per locale, and a recycle bin for deleted records
- **Authorization** — policy-based access with per-collection CRUD permissions, auto-synced to `spatie/laravel-permission` when it's installed (and gracefully skipped when it isn't)
- **Extensible** — register custom field types, panel types, flow operations, triggers and lifecycle hooks

---

## Proof it works

- **1,784 tests** across unit, feature and integration suites (Pest v4 + Orchestra Testbench)
- **Mutation-tested** — an MSI target of ≥80% per module, not just line coverage
- **CI on PHP 8.3 and 8.4** on every push

```bash
vendor/bin/pest
```

---

## How it works

Instead of a table per collection, Studio uses **EAV (Entity-Attribute-Value)** storage across four tables:

| Table | Purpose |
|-------|---------|
| `studio_collections` | Schema definitions (name, slug, settings) |
| `studio_fields` | Field definitions per collection (type, settings, validation) |
| `studio_records` | Record entries (UUID, collection, tenant) |
| `studio_values` | Typed data storage (text, integer, decimal, boolean, datetime, JSON columns) |

Values live in **six typed columns** rather than one stringly-typed blob, so sorting and comparison stay native to the database and type safety survives the trip.

---

## Extending

### Custom field types

```php
use Flexpik\FilamentStudio\FieldTypes\AbstractFieldType;
use Flexpik\FilamentStudio\Enums\EavCast;

class RatingFieldType extends AbstractFieldType
{
    protected static string $key = 'rating';
    protected static string $label = 'Rating';
    protected static string $icon = 'heroicon-o-star';
    protected static EavCast $eavCast = EavCast::Integer;
    protected static string $category = 'numeric';

    public function settingsSchema(): array { /* ... */ }
    public function toFilamentComponent(): Component { /* ... */ }
    public function toTableColumn(): ?Column { /* ... */ }
    public function toFilter(): ?Filter { /* ... */ }
}
```

### Lifecycle hooks

```php
FilamentStudioPlugin::afterCollectionCreated(fn ($collection) => /* ... */);
FilamentStudioPlugin::afterFieldAdded(fn ($field) => /* ... */);

FilamentStudioPlugin::modifyFormSchema(fn (array $schema, $collection) => $schema);
FilamentStudioPlugin::modifyTableColumns(fn (array $columns, $collection) => $columns);
FilamentStudioPlugin::modifyQuery(fn ($query) => $query);
```

---

## Documentation

**New to Studio, or handing it to a non-technical team?** Start with the **[User Guide](docs/user-guide/README.md)** — a plain-language, screenshot-led walkthrough written for the people who use the panel, not the people who install it.

| Guide | Description |
|-------|-------------|
| [User Guide](docs/user-guide/README.md) | Plain-language walkthrough for editors and administrators |
| [Installation](docs/installation.md) | Requirements, setup, and verification |
| [Configuration](docs/configuration.md) | Config file, plugin options, feature flags |
| [Field Types](docs/field-types.md) | All 33 built-in types, EAV storage, field settings |
| [Dashboards & Panels](docs/dashboards.md) | Dashboard builder, 9 panel types, variables |
| [Filtering](docs/filtering.md) | 23 operators, filter trees, saved filters |
| [REST API](docs/api.md) | Endpoints, authentication, permissions, rate limiting |
| [MCP Server](docs/mcp.md) | AI assistant integration — 34 tools, stdio & HTTP transport |
| [Flows](docs/flows.md) | Workflow automation — triggers, operations, versioning, webhook security |
| [Conditional Logic](docs/conditional-logic.md) | Dynamic visibility, required, and disabled states |
| [Authorization](docs/authorization.md) | Policies, permissions, Spatie integration |
| [Multi-Tenancy](docs/multi-tenancy.md) | Tenant scoping, lifecycle hooks |
| [Multilingual](docs/multilingual.md) | Locale config, translatable fields, API locale support |
| [Record Versioning](docs/versioning.md) | Snapshots, restore, soft deletes |
| [Hooks & Events](docs/hooks.md) | Lifecycle hooks, schema modification |
| [Custom Field Types](docs/extending/custom-field-types.md) | Building your own field types |
| [Custom Panel Types](docs/extending/custom-panel-types.md) | Building your own dashboard panels |
| [Extending Flows](docs/extending/flows.md) | Building your own operations and triggers |

---

## Requirements

- PHP 8.3+
- Laravel 11+
- Filament v5

---

## Screenshot gallery

<details>
<summary>Collections list</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/collections-list.png" alt="Collections List" />
</details>

<details>
<summary>Create a collection</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/create-collection-basic-info.png" alt="Create Collection" />
</details>

<details>
<summary>Fields list</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/fields-list.png" alt="Fields List" />
</details>

<details>
<summary>Field editor</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/field-editor.png" alt="Field Editor" />
</details>

<details>
<summary>Records list</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/records-list.png" alt="Records List" />
</details>

<details>
<summary>Record editor</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/record-edit.png" alt="Record Editor" />
</details>

<details>
<summary>Advanced filter builder</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/advanced-filter.png" alt="Advanced Filter Builder" />
</details>

<details>
<summary>Version history</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/version-history.png" alt="Version History" />
</details>

<details>
<summary>Dashboard</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/dashboard-view.png" alt="Dashboard" />
</details>

<details>
<summary>Dashboard editor</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/dashboard-editor.png" alt="Dashboard Editor" />
</details>

<details>
<summary>Flows list</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/flows-list.png" alt="Flows List" />
</details>

<details>
<summary>Flow designer canvas</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/flow-designer.png" alt="Flow Designer Canvas" />
</details>

<details>
<summary>Flow run history</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/flow-runs.png" alt="Flow Run History" />
</details>

<details>
<summary>API keys</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/api-keys-list.png" alt="API Keys List" />
</details>

<details>
<summary>API key editor</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/api-key-editor.png" alt="API Key Editor" />
</details>

<details>
<summary>API documentation</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/api-documentation.png" alt="API Documentation" />
</details>

<details>
<summary>Roles &amp; permissions</summary>
<img src="https://raw.githubusercontent.com/serhii-f8/filament-studio/main/art/roles-list.png" alt="Roles and Permissions" />
</details>

---

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

Please report vulnerabilities through [GitHub's private vulnerability reporting](https://github.com/serhii-f8/filament-studio/security) rather than a public issue. See [SECURITY.md](SECURITY.md) for details.

## Credits

- [Serhii Fedorenko](https://github.com/serhii-f8)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE](LICENSE.md) for details.
