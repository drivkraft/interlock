<div align="center">
    <h1>Laravel Interlock</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/drivkraft/interlock"><img src="https://img.shields.io/packagist/v/drivkraft/interlock.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/drivkraft/interlock"><img src="https://img.shields.io/packagist/php-v/drivkraft/interlock.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/drivkraft/interlock"><img src="https://badge.laravel.cloud/badge/drivkraft/interlock?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/drivkraft/interlock/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/drivkraft/interlock/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/drivkraft/interlock"><img src="https://img.shields.io/packagist/dt/drivkraft/interlock.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Lightweight architecture manager for structuring Laravel apps with modules.

A module is a folder in `modules/` with its own `composer.json`, classes, routes, views, translations and migrations. The package discovers and registers everything a module contains, and adds `module:*` generators that write straight into a module.

**Requirements:** PHP 8.5+, Laravel 13.

## Installation

```bash
composer require drivkraft/interlock
```

Module autoloading uses [wikimedia/composer-merge-plugin](https://github.com/wikimedia/composer-merge-plugin), which the package requires. Composer only reads the plugin's settings from your application, so add them to its `composer.json` and run `composer dump-autoload`:

```json
"extra": {
    "merge-plugin": {
        "include": ["modules/*/composer.json"]
    }
},
"config": {
    "allow-plugins": {
        "wikimedia/composer-merge-plugin": true
    }
}
```

Without `allow-plugins`, non-interactive installs (CI) fail.

### Configuration

```bash
php artisan vendor:publish --tag="interlock-config"
```

```php
// config/interlock.php
return [
    'path' => base_path('modules'),      // keep the merge plugin's include in sync
    'source' => 'src',                   // PHP folder inside each module
    'namespace' => 'Module',             // root namespace for new modules
    'tests_base' => 'Tests\TestCase',    // exposed to custom stubs
    'stubs' => null,                     // custom module:make stubs, null = package stubs

    // Folder per generator, relative to `source`
    'directories' => [
        'model' => 'Models',
        'enum' => 'Enums',
        'interface' => 'Contracts',
        'trait' => 'Concerns',
        'scope' => 'Models/Scopes',
        'data' => 'Data',
        'action' => 'Actions',
    ],

    // Group attributes per route file name; unlisted files load ungrouped
    'routes' => [
        'web' => ['middleware' => ['web']],
        'api' => ['middleware' => ['api'], 'prefix' => 'api'],
    ],

    // Used only when Livewire is installed
    'livewire' => [
        'components' => 'resources/views/components',
        'namespaces' => [
            'pages' => 'resources/views/pages',
            'layouts' => 'resources/views/layouts',
        ],
    ],

    // Each discovery type can be turned off
    'discover' => [
        'providers' => true, 'routes' => true, 'views' => true, 'translations' => true,
        'migrations' => true, 'components' => true, 'livewire' => true, 'commands' => true,
        'events' => true, 'factories' => true,
    ],

    'cache' => base_path('bootstrap/cache/interlock.php'),
];
```

## Quick Start

```bash
php artisan module:make billing
php artisan module:model Invoice --module=billing -mf
php artisan module:controller InvoiceController --module=billing
php artisan migrate
```

```text
modules/billing/
├── composer.json                   Module\Billing\ => src/
├── src/
│   ├── Http/Controllers/InvoiceController.php
│   ├── Models/Invoice.php
│   └── Providers/BillingServiceProvider.php
├── routes/web.php                  loaded in the web group
├── resources/views/                view('billing::...')
├── lang/                           __('billing::...')
├── database/{migrations,factories,seeders}/
└── tests/
```

No registration needed: the provider, routes, views, translations and migrations are discovered. `module:make` runs `composer dump-autoload` (skip with `--no-dump`) and asks which parts to create; without interaction it creates all except `--api`.

## How-to Guides

### Generate classes inside a module

Every `make:*` command has a `module:*` counterpart with the same options plus `--module` (prompted when omitted, required with `--no-interaction`). Module-aware differences:

- `module:command` prefixes the signature with the module name (`billing:send-reminders`).
- `module:component` writes the view to the module and renders `billing::components.invoice-card`.
- `module:listener --event=InvoicePaid` resolves `Module\Billing\Events\InvoicePaid`.
- `module:model` asks for related files unless you pass an option.
- `module:provider` does not touch `bootstrap/providers.php` (module providers are discovered) and warns when the provider won't be discovered: only top-level `Providers/*ServiceProvider` classes are.

`make:*` is unchanged and still writes to `app/`.

### Actions and data objects

```bash
php artisan module:action SendInvoice --module=billing   # src/Actions
php artisan module:data InvoiceData --module=billing     # src/Data
```

`module:action` adds `AsAction` when [lorisleiva/laravel-actions](https://github.com/lorisleiva/laravel-actions) is installed; `module:data` extends `Spatie\LaravelData\Data` when [spatie/laravel-data](https://github.com/spatie/laravel-data) is installed, otherwise a `final readonly` class. Names are used as given; no suffix is added.

### Change generator folders

Set `directories.{generator}` to a folder relative to `source`, for example `'interface' => 'Interfaces'` or `'class' => 'Support'`. Unlisted generators use Laravel's defaults. Nested names (`module:enum Billing/Status`) go below the configured folder.

### Add route groups

```php
'routes' => [
    'web' => ['middleware' => ['web']],
    'api' => ['middleware' => ['api'], 'prefix' => 'api'],
    'tenant' => ['middleware' => ['web', 'auth', 'tenant'], 'prefix' => 'app'],
],
```

Every module's `routes/tenant.php` now loads in that group. Route files not in the map load without a group.

### Translations, components and Livewire

- `lang/en/messages.php` → `__('billing::messages.paid')`. JSON translations are merged unprefixed.
- `src/View/Components` and `resources/views/components` → `<x-billing::invoice-card />`.
- With Livewire installed, each module is a namespace: `<livewire:billing::invoice-list />`. Each `livewire.namespaces` entry adds a sub-namespace (`billing.pages::invoices`), whose folder is also a view and anonymous component namespace (`<x-billing.layouts::app>`). Generate with `module:livewire {name} --module=billing [--kind=pages]`; all `make:livewire` options work.

### Seed a module

```bash
php artisan module:seed --module=billing                        # Module\Billing\Database\Seeders\DatabaseSeeder
php artisan module:seed --module=billing --class=InvoiceSeeder
```

`module:make` creates the module's `DatabaseSeeder` with the seeders part.

### Custom module stubs

```php
'stubs' => [
    'composer.json' => base_path('stubs/modules/composer.json.stub'),
    'StubSourceDirectory/Providers/StubClassNamePrefixServiceProvider.php' => base_path('stubs/modules/provider.stub'),
    'README.md' => base_path('stubs/modules/readme.stub'),
],
```

With custom stubs, `module:make` writes the whole map and skips the parts prompt. Paths and contents support the [placeholders](#stub-placeholders). Include a `composer.json` with a PSR-4 entry for `source`.

### Cache discovery in production

`php artisan optimize` runs `module:cache`, and `optimize:clear` runs `module:clear`. While cached, new modules and discovered classes are ignored until you run `module:clear` (`module:make` does this for you).

### Find modules from code

```php
use Drivkraft\Interlock\Facades\Interlock;

Interlock::modules();                              // Collection<string, Module>
Interlock::module('billing')?->namespace;          // 'Module\Billing\'
Interlock::moduleForClass(Invoice::class)?->name;  // 'billing'
Interlock::moduleForPath(__FILE__);
```

`module()` matches the folder name or its kebab-case form.

## Explanation

**Discovery.** Any folder in `path` with a `composer.json` is a module, named after the folder. Its namespace comes from the PSR-4 entry pointing at `source`, falling back to `{namespace}\{Name}\`. On boot the package builds a manifest of everything each module contains; providers are registered during Laravel's register phase, the rest at boot. `module:cache` snapshots the manifest, like Laravel's config and route caches.

**Autoloading.** Modules are plain Composer autoload entries merged by the merge plugin rather than registered at runtime. Module classes therefore work with optimised and authoritative classmaps, exist before Laravel boots, and are visible to IDEs and static analysis. The cost is a `dump-autoload` per new module.

**Generators.** Each `module:*` command extends its `make:*` counterpart and only changes the target namespace and path, so all options, and future ones, keep working. References to your application in Laravel's stubs stay in `App\`, so a module controller still extends `App\Http\Controllers\Controller`.

**Optional integrations.** Livewire, laravel-actions and laravel-data are detected at runtime and never required. They only change what the generators produce.

## Reference

### Commands

| Command | Description |
| --- | --- |
| `module:make {name}` | Create a module. `--web`, `--api`, `--views`, `--lang`, `--migrations`, `--factories`, `--seeders`, `--tests`, `--all`, `--no-dump`. Existing files are skipped. |
| `module:list` | List modules with namespace and path. |
| `module:cache` / `module:clear` | Write or delete the discovery cache. Hooked into `optimize` / `optimize:clear`. |
| `module:seed` | `db:seed` for a module. |
| `module:action`, `module:data` | See [Actions and data objects](#actions-and-data-objects). |

Generators mirroring `make:*`: `cast`, `channel`, `class`, `command`, `component`, `controller`, `enum`, `event`, `exception`, `factory`, `interface`, `job`, `job-middleware`, `listener`, `livewire`, `mail`, `middleware`, `migration`, `model`, `notification`, `observer`, `policy`, `provider`, `request`, `resource`, `rule`, `scope`, `seeder`, `test`, `trait`.

### What gets discovered

| Module path | Registered as |
| --- | --- |
| `src/Providers/*ServiceProvider.php` | Service providers |
| `routes/{name}.php` | Routes in the `routes.{name}` group (`web`; `api` with `/api` prefix), or ungrouped |
| `resources/views` | `billing::` view namespace |
| `lang` | `billing::` translations, plus JSON translations |
| `src/View/Components`, `resources/views/components` | `<x-billing::...>` components |
| `src/Livewire`, `resources/views/components` | `billing::` Livewire namespace (Livewire only) |
| `src/Console/Commands` | Artisan commands |
| `src/Listeners` | Event listeners |
| `database/migrations` | Migration path |
| `database/factories` | Factories for `src/Models/*` |

### Stub placeholders

| Placeholder | `module:make billing` |
| --- | --- |
| `StubModuleName` / `StubModuleNameSingular` / `StubModuleNamePlural` | `billing` / `billing` / `billings` |
| `StubClassNamePrefix` | `Billing` |
| `StubModuleNamespace` | `Module` |
| `StubSourceDirectory` | `src` |
| `StubBasePath` | Absolute module path |
| `StubMigrationPrefix` | `2026_10_23_120000` |
| `StubTestCaseBase` / `StubFullyQualifiedTestCaseBase` | `TestCase` / `Tests\TestCase` |

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Interlock! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Kim Eric Helle](https://github.com/drivkraft)
- [All Contributors](../../contributors)

## License

Interlock is open-sourced software licensed under the [MIT license](LICENSE.md).
