<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    |
    | The directory that holds every module. Each sub-directory containing a
    | composer.json file is treated as a module.
    |
    */

    'path' => base_path('modules'),

    /*
    |--------------------------------------------------------------------------
    | Source Directory
    |--------------------------------------------------------------------------
    |
    | The directory inside each module that holds its PHP classes (the
    | equivalent of app/). Generators, provider, command, component and
    | listener discovery all read from this directory.
    |
    */

    'source' => 'src',

    /*
    |--------------------------------------------------------------------------
    | Namespace
    |--------------------------------------------------------------------------
    |
    | The PHP namespace modules are created in (\Module\Identity).
    |
    */

    'namespace' => 'Module',

    /*
    |--------------------------------------------------------------------------
    | Base Test Case
    |--------------------------------------------------------------------------
    */

    'tests_base' => 'Tests\TestCase',

    /*
    |--------------------------------------------------------------------------
    | Module Stubs
    |--------------------------------------------------------------------------
    |
    | Files written by module:make, as [module-relative destination =>
    | absolute stub path]. Null uses the package stubs. Destinations and
    | contents support the Stub* placeholders listed in MakeModuleCommand.
    |
    */

    'stubs' => null,

    /*
    |--------------------------------------------------------------------------
    | Generator Directories
    |--------------------------------------------------------------------------
    |
    | Where module:* generators put classes, relative to the module's source
    | directory, keyed by generator (module:enum => 'enum'). Generators that
    | are not listed keep Laravel's defaults (Jobs, Events, Http/Controllers,
    | ...). A name with a path (module:enum Billing/Status) is placed below
    | the configured directory.
    |
    */

    'directories' => [
        'model' => 'Models',
        'enum' => 'Enums',
        'interface' => 'Contracts',
        'trait' => 'Concerns',
        'scope' => 'Models/Scopes',
        'data' => 'Data',
        'action' => 'Actions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Files
    |--------------------------------------------------------------------------
    |
    | Route files in a module's routes/ directory are matched by file name
    | (without .php) against this map and loaded inside a group with the
    | given attributes. Files that are not in the map are loaded as-is, so
    | they must apply their own middleware.
    |
    */

    'routes' => [
        'web' => ['middleware' => ['web']],
        'api' => ['middleware' => ['api'], 'prefix' => 'api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | Each module is a Livewire namespace named after the module, mirroring
    | livewire.component_locations: <livewire:shop::cart /> resolves to
    | modules/shop/{components}. Every entry in 'namespaces' adds a sub-namespace
    | per module, mirroring livewire.component_namespaces: 'pages' gives
    | shop.pages::orders in modules/shop/resources/views/pages. Class-based
    | components live in {source}/Livewire (or Livewire/Pages, ...).
    | Ignored when livewire/livewire is not installed.
    |
    */

    'livewire' => [
        'components' => 'resources/views/components',
        'namespaces' => [
            'pages' => 'resources/views/pages',
            'layouts' => 'resources/views/layouts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    |
    | Toggle each kind of per-module discovery.
    |
    */

    'discover' => [
        'providers' => true,
        'routes' => true,
        'views' => true,
        'translations' => true,
        'migrations' => true,
        'components' => true,
        'livewire' => true,
        'commands' => true,
        'events' => true,
        'factories' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache File
    |--------------------------------------------------------------------------
    |
    | Written by module:cache (and php artisan optimize).
    |
    */

    'cache' => base_path('bootstrap/cache/interlock.php'),

];
