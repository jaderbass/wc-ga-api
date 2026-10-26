<?php

use App\Filament\Pages\SystemSwitch;
use App\Http\Middleware\BlockWhileSystemLocked;
use App\Models\User;
use App\Support\SystemLock;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });

        (include base_path('vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub'))->up();
    } finally {
        Schema::enableForeignKeyConstraints();
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    config()->set('admin_access.protected_email_hashes', [hash('sha256', 'fallback@example.com')]);
    config()->set('admin_access.panel_roles', ['Admin']);
    config()->set('admin_access.lock_file', sys_get_temp_dir().'/system-lock-test-'.getmypid().'.json');
    @unlink(SystemLock::path());

    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    $this->fallback = User::factory()->create(['email' => 'fallback@example.com']);
    $this->admin = User::factory()->create()->assignRole('Admin');
});

afterEach(function () {
    @unlink(SystemLock::path());
});

function passesLockMiddleware(string $path, ?User $user = null): bool
{
    $request = Request::create($path);
    $request->setUserResolver(fn () => $user);

    try {
        (new BlockWhileSystemLocked)->handle($request, fn () => response('ok'));

        return true;
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(503);

        return false;
    }
}

it('switches off and on again', function () {
    expect(SystemLock::isLocked())->toBeFalse();

    SystemLock::lock($this->fallback);
    expect(SystemLock::isLocked())->toBeTrue()
        ->and(SystemLock::info()['locked_by'])->toBe($this->fallback->id);

    SystemLock::unlock();
    expect(SystemLock::isLocked())->toBeFalse();
});

it('lets only protected accounts into the backend while switched off', function () {
    $panel = Filament::getPanel('admin');

    SystemLock::lock();

    expect($this->admin->canAccessPanel($panel))->toBeFalse()
        ->and($this->fallback->canAccessPanel($panel))->toBeTrue();

    SystemLock::unlock();

    expect($this->admin->canAccessPanel($panel))->toBeTrue();
});

it('answers webhooks and other routes with 503 while switched off', function () {
    expect(passesLockMiddleware('/api/webhooks/woo/1'))->toBeTrue();

    SystemLock::lock();

    expect(passesLockMiddleware('/api/webhooks/woo/1'))->toBeFalse()
        ->and(passesLockMiddleware('/health', $this->admin))->toBeFalse()
        ->and(passesLockMiddleware('/health', $this->fallback))->toBeTrue()
        // Login und Livewire bleiben erreichbar, dort entscheidet canAccessPanel
        ->and(passesLockMiddleware('/admin/login'))->toBeTrue()
        ->and(passesLockMiddleware('/livewire/update'))->toBeTrue();
});

it('pauses queue workers while switched off', function () {
    expect(Event::until(new Looping('database', 'default')))->not->toBeFalse();

    SystemLock::lock();

    expect(Event::until(new Looping('database', 'default')))->toBeFalse();
});

it('only allows maintenance commands while switched off', function () {
    expect(SystemLock::allowsCommand('system:lock'))->toBeTrue()
        ->and(SystemLock::allowsCommand('config:cache'))->toBeTrue()
        ->and(SystemLock::allowsCommand('queue:work'))->toBeFalse()
        ->and(SystemLock::allowsCommand('pricelist:apply'))->toBeFalse()
        ->and(SystemLock::allowsCommand('migrate'))->toBeFalse();
});

it('shows the switch only to protected accounts', function () {
    $this->actingAs($this->admin);
    expect(SystemSwitch::canAccess())->toBeFalse();

    $this->actingAs($this->fallback);
    expect(SystemSwitch::canAccess())->toBeTrue();
});

it('switches via artisan', function () {
    $this->artisan('system:lock', ['state' => 'off'])->assertSuccessful();
    expect(SystemLock::isLocked())->toBeTrue();

    $this->artisan('system:lock', ['state' => 'on'])->assertSuccessful();
    expect(SystemLock::isLocked())->toBeFalse();
});

it('renders the switch page and toggles it', function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->fallback);

    Livewire\Livewire::test(SystemSwitch::class)
        ->assertSee('Eingeschaltet')
        ->callAction('lock')
        ->assertSee('Abgeschaltet');

    expect(SystemLock::isLocked())->toBeTrue();

    Livewire\Livewire::test(SystemSwitch::class)
        ->callAction('unlock')
        ->assertSee('Eingeschaltet');

    expect(SystemLock::isLocked())->toBeFalse();
});

it('answers "not found" to everyone else who opens the switch page', function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire\Livewire::test(SystemSwitch::class)->assertNotFound();
});
