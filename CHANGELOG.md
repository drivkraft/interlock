# Changelog

All notable changes to `drivkraft/interlock` will be documented in this file.

## Release v1 - 2026-10-23

### Modules

- Folders in `modules/` that contain a `composer.json` are discovered as modules. Each module is named after its folder, and its namespace comes from its PSR-4 entry.
- Module autoloading goes through `wikimedia/composer-merge-plugin`, so it works with optimised and authoritative classmaps.
- `Interlock` facade with `modules()`, `module()`, `moduleForClass()` and `moduleForPath()`.

### Discovery

- Registers each module's service providers, routes, views, translations (PHP and JSON), Blade components, Artisan commands, event listeners, migrations and model factories.
- Route groups are set per route file name through `interlock.routes`. `web` and `api` are included by default.
- Livewire support (optional): each module gets a Livewire namespace, plus configurable sub-namespaces such as `billing.pages::`.
- Each discovery type can be turned off under `interlock.discover`.
- `module:cache` and `module:clear` cache the discovery manifest. Both are hooked into `optimize` and `optimize:clear`.

### Commands

- `module:make` scaffolds a module and lets you pick its parts (`--web`, `--api`, `--views`, `--lang`, `--migrations`, `--factories`, `--seeders`, `--tests`, `--all`). It supports custom stubs with placeholders.
- `module:list` and `module:seed`.
- `module:*` counterparts of 30 `make:*` generators, with the same options plus `--module`: cast, channel, class, command, component, controller, enum, event, exception, factory, interface, job, job-middleware, listener, livewire, mail, middleware, migration, model, notification, observer, policy, provider, request, resource, rule, scope, seeder, test, trait.
- `module:action` uses `lorisleiva/laravel-actions` when it's installed, and `module:data` uses `spatie/laravel-data` when it's installed.
- Output folders for each generator are set through `interlock.directories`.

### Requirements

- PHP 8.5+ and Laravel 13.
