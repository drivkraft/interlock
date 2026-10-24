<?php

declare(strict_types=1);

use Drivkraft\Interlock\Console\Make\MakeAction;
use Drivkraft\Interlock\Console\Make\MakeData;
use Drivkraft\Interlock\InterlockServiceProvider;
use Drivkraft\Interlock\ModuleManifest;
use Drivkraft\Interlock\ModuleRegistry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->modulesPath = storage_path('framework/testing/modules-'.Str::random(8));

    File::ensureDirectoryExists($this->modulesPath);

    config([
        'interlock.path' => $this->modulesPath,
        'interlock.cache' => $this->modulesPath.'/cache.php',
    ]);

    $this->app->forgetInstance(ModuleManifest::class);
    $this->app->forgetInstance(ModuleRegistry::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->modulesPath);
});

/**
 * Create a module and return its absolute path.
 */
function makeModule(string $name): string
{
    test()->artisan('make:module', ['name' => $name, '--all' => true, '--no-dump' => true])->assertSuccessful();

    return test()->modulesPath.'/'.$name;
}

it('creates a module from the stubs', function (): void {
    $module = makeModule('shop');

    $composer = json_decode(File::get("{$module}/composer.json"), true);

    expect($composer)->not->toHaveKey('name')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Module\\Shop\\', 'src/')
        ->and(File::get("{$module}/src/Providers/ShopServiceProvider.php"))->toContain('namespace Module\\Shop\\Providers;')
        ->and("{$module}/routes/web.php")->toBeFile()
        ->and("{$module}/lang")->toBeDirectory()
        ->and(File::get("{$module}/database/seeders/DatabaseSeeder.php"))
        ->toContain('namespace Module\\Shop\\Database\\Seeders;')
        ->toContain('class DatabaseSeeder extends Seeder')
        ->and("{$module}/database/seeders/.gitkeep")->not->toBeFile()
        ->and(app(ModuleRegistry::class)->module('shop')?->namespace)->toBe('Module\\Shop\\');
});

it('uses the configured source directory for new modules and generators', function (): void {
    config(['interlock.source' => 'lib']);
    app()->forgetInstance(ModuleManifest::class);

    $module = makeModule('shop');

    $this->artisan('module:enum', ['name' => 'OrderStatus', '--module' => 'shop'])->assertSuccessful();

    expect(json_decode(File::get("{$module}/composer.json"), true)['autoload']['psr-4'])->toHaveKey('Module\\Shop\\', 'lib/')
        ->and("{$module}/lib/Providers/ShopServiceProvider.php")->toBeFile()
        ->and(File::get("{$module}/lib/Enums/OrderStatus.php"))->toContain('namespace Module\\Shop\\Enums;');
});

it('generates a model with factory and migration inside the module', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:model', ['name' => 'Order', '--module' => 'shop', '--factory' => true, '--migration' => true])->assertSuccessful();

    expect(File::get("{$module}/src/Models/Order.php"))
        ->toContain('namespace Module\\Shop\\Models;')
        ->toContain('HasFactory<\\Module\\Shop\\Database\\Factories\\OrderFactory>')
        ->and(File::get("{$module}/database/factories/OrderFactory.php"))
        ->toContain('namespace Module\\Shop\\Database\\Factories;')
        ->toContain('Module\\Shop\\Models\\Order')
        ->and(File::glob("{$module}/database/migrations/*_create_orders_table.php"))->toHaveCount(1);
});

it('generates classes in the module namespace and source directory', function (string $command, array $arguments, string $path, string $namespace): void {
    $module = makeModule('shop');

    $this->artisan($command, [...$arguments, '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/{$path}"))->toContain("namespace {$namespace};");
})->with([
    'controller' => ['module:controller', ['name' => 'OrderController'], 'src/Http/Controllers/OrderController.php', 'Module\\Shop\\Http\\Controllers'],
    'request' => ['module:request', ['name' => 'StoreOrderRequest'], 'src/Http/Requests/StoreOrderRequest.php', 'Module\\Shop\\Http\\Requests'],
    'job' => ['module:job', ['name' => 'ShipOrder'], 'src/Jobs/ShipOrder.php', 'Module\\Shop\\Jobs'],
    'event' => ['module:event', ['name' => 'OrderPlaced'], 'src/Events/OrderPlaced.php', 'Module\\Shop\\Events'],
    'policy' => ['module:policy', ['name' => 'OrderPolicy'], 'src/Policies/OrderPolicy.php', 'Module\\Shop\\Policies'],
    'provider' => ['module:provider', ['name' => 'ShopEventServiceProvider'], 'src/Providers/ShopEventServiceProvider.php', 'Module\\Shop\\Providers'],
    'seeder' => ['module:seeder', ['name' => 'OrderSeeder'], 'database/seeders/OrderSeeder.php', 'Module\\Shop\\Database\\Seeders'],
    'phpunit test' => ['module:test', ['name' => 'OrderTest', '--phpunit' => true], 'tests/Feature/OrderTest.php', 'Module\\Shop\\Tests\\Feature'],
]);

it('prefixes generated command signatures with the module name', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:command', ['name' => 'SyncOrders', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/Console/Commands/SyncOrders.php"))
        ->toContain('namespace Module\\Shop\\Console\\Commands;')
        ->toContain('shop:sync-orders');
});

it('points generated listeners at the module events namespace', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:listener', ['name' => 'NotifyCustomer', '--event' => 'OrderPlaced', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/Listeners/NotifyCustomer.php"))
        ->toContain('namespace Module\\Shop\\Listeners;')
        ->toContain('use Module\\Shop\\Events\\OrderPlaced;');
});

it('generates blade components with module views', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:component', ['name' => 'OrderCard', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/View/Components/OrderCard.php"))
        ->toContain('namespace Module\\Shop\\View\\Components;')
        ->toContain("view('shop::components.order-card')")
        ->and("{$module}/resources/views/components/order-card.blade.php")->toBeFile();
});

it('generates livewire class components through the module namespace', function (): void {
    $module = makeModule('shop');

    $this->app->register(InterlockServiceProvider::class, force: true);

    $this->artisan('make:livewire', ['name' => 'shop::order-list', '--class' => true])->assertSuccessful();

    expect(File::get("{$module}/src/Livewire/OrderList.php"))->toContain('namespace Module\\Shop\\Livewire;');
});

it('rejects unknown modules', function (): void {
    $this->artisan('module:model', ['name' => 'Order', '--module' => 'missing']);
})->throws(InvalidArgumentException::class, 'The "missing" module does not exist.');

it('asks for the module when it is not given', function (): void {
    $module = makeModule('shop');
    makeModule('blog');

    $this->artisan('module:model', ['name' => 'Order'])
        ->expectsChoice('Which module?', 'shop', ['blog' => 'blog', 'shop' => 'shop'])
        ->expectsQuestion('Would you like any of the following?', [])
        ->assertSuccessful();

    expect("{$module}/src/Models/Order.php")->toBeFile();
});

it('requires the module when running without interaction', function (): void {
    makeModule('shop');

    $this->artisan('module:model', ['name' => 'Order', '--no-interaction' => true]);
})->throws(InvalidArgumentException::class, 'The --module option is required.');

it('leaves the stock make commands untouched', function (): void {
    expect(Artisan::all()['make:model']->getDefinition()->hasOption('module'))->toBeFalse();
});

it('asks which related files to create for a model', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:model', ['name' => 'Order', '--module' => 'shop'])
        ->expectsQuestion('Would you like any of the following?', ['factory', 'migration'])
        ->assertSuccessful();

    expect("{$module}/src/Models/Order.php")->toBeFile()
        ->and("{$module}/database/factories/OrderFactory.php")->toBeFile()
        ->and(File::glob("{$module}/database/migrations/*_create_orders_table.php"))->toHaveCount(1)
        ->and("{$module}/database/seeders/OrderSeeder.php")->not->toBeFile();
});

it('does not ask for related files when options are passed', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:model', ['name' => 'Order', '--module' => 'shop', '--factory' => true])->assertSuccessful();

    expect("{$module}/database/factories/OrderFactory.php")->toBeFile();
});

it('asks for the module name and parts when not given', function (): void {
    $this->artisan('make:module', ['--no-dump' => true])
        ->expectsQuestion('What should the module be called?', 'Shop')
        ->expectsQuestion('Which parts should the module have?', ['api', 'migrations'])
        ->assertSuccessful();

    $module = $this->modulesPath.'/shop';

    expect("{$module}/composer.json")->toBeFile()
        ->and("{$module}/src/Providers/ShopServiceProvider.php")->toBeFile()
        ->and("{$module}/routes/api.php")->toBeFile()
        ->and("{$module}/database/migrations")->toBeDirectory()
        ->and("{$module}/routes/web.php")->not->toBeFile()
        ->and("{$module}/resources/views")->not->toBeDirectory();
});

it('creates only the parts passed as options without asking', function (): void {
    $this->artisan('make:module', ['name' => 'shop', '--web' => true, '--no-dump' => true])->assertSuccessful();

    expect($this->modulesPath.'/shop/routes/web.php')->toBeFile()
        ->and($this->modulesPath.'/shop/database')->not->toBeDirectory();
});

it('creates the default parts when running without interaction', function (): void {
    $this->artisan('make:module', ['name' => 'shop', '--no-dump' => true, '--no-interaction' => true])->assertSuccessful();

    expect($this->modulesPath.'/shop/routes/web.php')->toBeFile()
        ->and($this->modulesPath.'/shop/tests')->toBeDirectory()
        ->and($this->modulesPath.'/shop/routes/api.php')->not->toBeFile();
});

it('generates livewire components in the chosen module', function (): void {
    $module = makeModule('shop');
    makeModule('blog');

    $this->app->register(InterlockServiceProvider::class, force: true);

    $this->artisan('module:livewire', ['name' => 'order-list', '--class' => true])
        ->expectsChoice('Which module?', 'shop', ['blog' => 'blog', 'shop' => 'shop'])
        ->expectsChoice('What kind of component?', 'component', ['component' => 'Component (shop::)', 'pages' => 'Page (shop.pages::)', 'layouts' => 'Layout (shop.layouts::)'])
        ->assertSuccessful();

    expect(File::get("{$module}/src/Livewire/OrderList.php"))->toContain('namespace Module\\Shop\\Livewire;')
        ->and("{$module}/resources/views/livewire/order-list.blade.php")->toBeFile();
});

it('generates livewire pages in the pages sub-namespace', function (): void {
    $module = makeModule('shop');

    $this->app->register(InterlockServiceProvider::class, force: true);

    $this->artisan('module:livewire', ['name' => 'orders', '--module' => 'shop', '--kind' => 'pages', '--class' => true])->assertSuccessful();

    expect(File::get("{$module}/src/Livewire/Pages/Orders.php"))->toContain('namespace Module\\Shop\\Livewire\\Pages;')
        ->and("{$module}/resources/views/livewire/pages/orders.blade.php")->toBeFile();
});

it('generates single file livewire pages in the pages directory', function (): void {
    $module = makeModule('shop');

    $this->app->register(InterlockServiceProvider::class, force: true);

    $this->artisan('module:livewire', ['name' => 'orders', '--module' => 'shop', '--kind' => 'pages', '--sfc' => true, '--emoji' => 'false'])->assertSuccessful();

    expect("{$module}/resources/views/pages/orders.blade.php")->toBeFile();
});

it('rejects unknown livewire kinds', function (): void {
    makeModule('shop');

    $this->artisan('module:livewire', ['name' => 'orders', '--module' => 'shop', '--kind' => 'widgets']);
})->throws(InvalidArgumentException::class, 'Unknown kind "widgets"');

it('places classes in the configured directories', function (string $command, string $name, string $path, string $namespace): void {
    $module = makeModule('shop');

    $this->artisan($command, ['name' => $name, '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/{$path}"))->toContain("namespace {$namespace};");
})->with([
    'enum' => ['module:enum', 'OrderStatus', 'src/Enums/OrderStatus.php', 'Module\\Shop\\Enums'],
    'interface' => ['module:interface', 'Payable', 'src/Contracts/Payable.php', 'Module\\Shop\\Contracts'],
    'trait' => ['module:trait', 'HasTotals', 'src/Concerns/HasTotals.php', 'Module\\Shop\\Concerns'],
    'scope' => ['module:scope', 'PaidScope', 'src/Models/Scopes/PaidScope.php', 'Module\\Shop\\Models\\Scopes'],
    'nested name' => ['module:enum', 'Billing/InvoiceStatus', 'src/Enums/Billing/InvoiceStatus.php', 'Module\\Shop\\Enums\\Billing'],
]);

it('lets the directories be changed and added in config', function (): void {
    config([
        'interlock.directories.interface' => 'Interfaces',
        'interlock.directories.class' => 'Support',
    ]);

    $module = makeModule('shop');

    $this->artisan('module:interface', ['name' => 'Payable', '--module' => 'shop'])->assertSuccessful();
    $this->artisan('module:class', ['name' => 'Money', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/Interfaces/Payable.php"))->toContain('namespace Module\\Shop\\Interfaces;')
        ->and(File::get("{$module}/src/Support/Money.php"))->toContain('namespace Module\\Shop\\Support;');
});

it('registers the module generators under the module prefix', function (): void {
    expect(Artisan::all())->toHaveKeys(['module:model', 'module:action', 'module:data', 'module:livewire', 'module:seed'])
        ->not->toHaveKey('mod:model');
});

it('registers the module management commands under the module prefix', function (): void {
    expect(Artisan::all())->toHaveKeys(['make:module', 'module:list', 'module:cache', 'module:clear'])
        ->not->toHaveKeys(['modules:list', 'modules:cache', 'modules:clear']);
});

it('does not add generated providers to bootstrap/providers.php', function (): void {
    makeModule('shop');

    $bootstrap = app()->getBootstrapProvidersPath();
    $before = File::get($bootstrap);

    $this->artisan('module:provider', ['name' => 'ShopEventServiceProvider', '--module' => 'shop'])
        ->doesntExpectOutputToContain('not discovered automatically')
        ->assertSuccessful();

    expect(File::get($bootstrap))->toBe($before);
});

it('warns when a generated provider will not be discovered', function (string $name): void {
    makeModule('shop');

    $this->artisan('module:provider', ['name' => $name, '--module' => 'shop'])
        ->expectsOutputToContain('not discovered automatically')
        ->assertSuccessful();
})->with([
    'name without suffix' => ['ShopEvents'],
    'nested directory' => ['Nested/ShopEventServiceProvider'],
]);

it('points a factory at the module model named after it when the model does not exist yet', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:factory', ['name' => 'InvoiceFactory', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/database/factories/InvoiceFactory.php"))
        ->toContain('namespace Module\\Shop\\Database\\Factories;')
        ->toContain('Module\\Shop\\Models\\Invoice')
        ->not->toContain('Models\\Model');
});

it('finds modules whose directory is not kebab case', function (): void {
    File::ensureDirectoryExists($this->modulesPath.'/Billing/src');
    File::put($this->modulesPath.'/Billing/composer.json', json_encode(['autoload' => ['psr-4' => ['Module\\Billing\\' => 'src/']]]));

    expect(app(ModuleRegistry::class)->module('Billing')?->name)->toBe('Billing')
        ->and(app(ModuleRegistry::class)->module('billing')?->name)->toBe('Billing');

    $this->artisan('module:enum', ['name' => 'InvoiceStatus', '--module' => 'billing'])->assertSuccessful();

    expect(File::get($this->modulesPath.'/Billing/src/Enums/InvoiceStatus.php'))->toContain('namespace Module\\Billing\\Enums;');
});

it('skips existing files when the module already exists', function (): void {
    $module = makeModule('shop');

    File::put("{$module}/routes/web.php", '<?php // custom');

    $this->artisan('make:module', ['name' => 'shop', '--web' => true, '--no-dump' => true])
        ->expectsOutputToContain('already exists')
        ->assertSuccessful();

    expect(File::get("{$module}/routes/web.php"))->toBe('<?php // custom');
});

it('writes custom stubs from config', function (): void {
    $stub = $this->modulesPath.'/readme.stub';
    File::put($stub, 'StubClassNamePrefix in StubModuleNamespace');

    config(['interlock.stubs' => ['README-StubModuleName.md' => $stub]]);

    $this->artisan('make:module', ['name' => 'shop', '--no-dump' => true])->assertSuccessful();

    expect(File::get($this->modulesPath.'/shop/README-shop.md'))->toBe('Shop in Module')
        ->and($this->modulesPath.'/shop/composer.json')->not->toBeFile();
});

it('fails when a custom stub does not exist', function (): void {
    config(['interlock.stubs' => ['README.md' => $this->modulesPath.'/missing.stub']]);

    $this->artisan('make:module', ['name' => 'shop', '--no-dump' => true])
        ->expectsOutputToContain('Stub file not found')
        ->assertFailed();
});

it('seeds a specific seeder class inside the module', function (): void {
    makeModule('shop');

    $this->artisan('module:seed', ['--module' => 'shop', '--class' => 'MissingSeeder']);
})->throws(BindingResolutionException::class, 'Module\\Shop\\Database\\Seeders\\MissingSeeder');

it('generates laravel-data objects when spatie/laravel-data is installed', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:data', ['name' => 'OrderData', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/Data/OrderData.php"))
        ->toContain('namespace Module\\Shop\\Data;')
        ->toContain('use Spatie\\LaravelData\\Data;')
        ->toContain('class OrderData extends Data');
});

it('generates plain readonly data objects without spatie/laravel-data', function (): void {
    $module = makeModule('shop');

    $command = new class(app('files')) extends MakeData
    {
        protected function usesLaravelData(): bool
        {
            return false;
        }
    };

    $command->setLaravel($this->app);
    $this->app->make(Kernel::class)->registerCommand($command);

    $this->artisan('module:data', ['name' => 'Billing/Address', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/Data/Billing/Address.php"))
        ->toContain('namespace Module\\Shop\\Data\\Billing;')
        ->toContain('final readonly class Address')
        ->not->toContain('Spatie');
});

it('places data objects in the configured directory without adding a suffix', function (): void {
    config(['interlock.directories.data' => 'DataTransferObjects']);

    $module = makeModule('shop');

    $this->artisan('module:data', ['name' => 'Order', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/DataTransferObjects/Order.php"))->toContain('class Order extends Data');
});

it('generates actions using AsAction when lorisleiva/laravel-actions is installed', function (): void {
    $module = makeModule('shop');

    $this->artisan('module:action', ['name' => 'PlaceOrder', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/Actions/PlaceOrder.php"))
        ->toContain('namespace Module\\Shop\\Actions;')
        ->toContain('use Lorisleiva\\Actions\\Concerns\\AsAction;')
        ->toContain('use AsAction;')
        ->toContain('public function handle(): void');
});

it('generates plain actions without lorisleiva/laravel-actions', function (): void {
    $module = makeModule('shop');

    $command = new class(app('files')) extends MakeAction
    {
        protected function usesLaravelActions(): bool
        {
            return false;
        }
    };

    $command->setLaravel($this->app);
    $this->app->make(Kernel::class)->registerCommand($command);

    $this->artisan('module:action', ['name' => 'Orders/CancelOrder', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/Actions/Orders/CancelOrder.php"))
        ->toContain('namespace Module\\Shop\\Actions\\Orders;')
        ->toContain('class CancelOrder')
        ->toContain('public function handle(): void')
        ->not->toContain('AsAction');
});

it('places actions in the configured directory without adding a suffix', function (): void {
    config(['interlock.directories.action' => 'UseCases']);

    $module = makeModule('shop');

    $this->artisan('module:action', ['name' => 'PlaceOrder', '--module' => 'shop'])->assertSuccessful();

    expect(File::get("{$module}/src/UseCases/PlaceOrder.php"))->toContain('namespace Module\\Shop\\UseCases;');
});

it('creates a DatabaseSeeder that module:seed runs', function (): void {
    $module = makeModule('shop');

    require_once "{$module}/database/seeders/DatabaseSeeder.php";

    $this->artisan('module:seed', ['--module' => 'shop'])->assertSuccessful();
});
