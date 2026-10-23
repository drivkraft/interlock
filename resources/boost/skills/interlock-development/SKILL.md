---
name: interlock-development
description: >
  Structure a Laravel application into modules with drivkraft/interlock: create modules,
  generate classes inside them with module:* commands, and rely on automatic discovery of providers,
  routes, views, translations, components, commands, listeners, migrations and factories.
license: MIT
metadata:
  author: Kim Eric Helle
---

# Interlock

Use this skill when a Laravel application uses `drivkraft/interlock`, or when code belongs in a module under `modules/`.

## Primary Goal

- put code in the right module with the `module:*` generators, and rely on discovery instead of registering things manually

## Workflow

### 1. Inspect the Laravel app context

- confirm `drivkraft/interlock` is in `composer.json` and check `config/interlock.php` if it was published (`path`, `source`, `namespace`, `directories`, `routes`, `discover`)
- confirm the app's `composer.json` has `extra.merge-plugin.include: ["modules/*/composer.json"]` and `config.allow-plugins."wikimedia/composer-merge-plugin": true` (the package requires the plugin, but only the app can configure and allow it); the package does not autoload module classes itself
- run `php artisan module:list` to see existing modules and their namespaces

### 2. Create or pick a module

- create one with `php artisan module:make billing --no-interaction` (all parts except API routes) or choose parts: `--web --api --views --lang --migrations --factories --seeders --tests` or `--all`
- `module:make` runs `composer dump-autoload`; pass `--no-dump` only if you dump yourself afterwards

### 3. Generate classes inside the module

- use `module:<type>` instead of `make:<type>` and always pass `--module=<name>` when running non-interactively, e.g. `php artisan module:model Invoice --module=billing -mf`
- all `make:*` options work; `module:command` prefixes signatures with the module name, `module:listener --event=InvoicePaid` resolves to the module's `Events` namespace
- create actions with `php artisan module:action PlaceOrder --module=billing` (in `src/Actions` with a `handle()` method; uses `AsAction` if lorisleiva/laravel-actions is installed; no suffix is added)
- create data objects with `php artisan module:data InvoiceData --module=billing` (in `src/Data`; extends `Spatie\LaravelData\Data` if spatie/laravel-data is installed, otherwise a `final readonly` class; no suffix is added)
- seed with `php artisan module:seed --module=billing [--class=InvoiceSeeder]`; without `--class` it runs the module's `database/seeders/DatabaseSeeder.php` (created by `module:make` with the seeders part), so register new seeders in its `run()` method

### 4. Use discovered resources

- providers in `src/Providers/*ServiceProvider.php` are registered automatically; never add them to `bootstrap/providers.php`
- `routes/web.php` gets the `web` group, `routes/api.php` gets `api` + `/api` prefix, other files use `interlock.routes.{file}` or load as-is
- reference module resources with the module prefix: `view('billing::invoices.index')`, `__('billing::messages.paid')`, `<x-billing::invoice-card />`, `<livewire:billing::invoice-list />` (Livewire only)
- `Module\Billing\Models\Invoice::factory()` resolves `Module\Billing\Database\Factories\InvoiceFactory`

### 5. Deploy

- `php artisan optimize` runs `module:cache`; after adding modules or discovered classes in a cached environment, run `php artisan module:clear` (or `optimize:clear`)

## Rules, References, and Templates

Read before executing:

- no additional resource files for this skill
- `config/interlock.php` in the app (if published) overrides all defaults described here

## Examples

- add an invoicing feature: `php artisan module:make billing -n`, then `php artisan module:model Invoice --module=billing -mfs`, `php artisan module:controller InvoiceController --module=billing --model=Invoice`, add routes to `modules/billing/routes/web.php`
- look up modules in code:

```php
use Drivkraft\Interlock\Facades\Interlock;

Interlock::module('billing')?->namespace;        // 'Module\Billing\'
Interlock::moduleForClass(Invoice::class)?->name; // 'billing'
```

## Anti-patterns

- running `make:*` for module code (it writes to `app/`), or creating module files by hand in the wrong namespace
- adding module providers to `bootstrap/providers.php` or manually calling `loadViewsFrom`/`loadRoutesFrom` for module resources
- applying `web`/`api` middleware again inside module `routes/web.php` or `routes/api.php`
- editing the app's PSR-4 autoload for each module instead of using the module's own `composer.json`
- forgetting `module:clear` after adding modules when the module cache exists
