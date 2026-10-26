<?php

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
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

    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    $this->fallback = User::factory()->create(['email' => 'Fallback@Example.com']);
    $this->admin = User::factory()->create()->assignRole('Admin');
});

it('lets only admins and the fallback account into the backend', function () {
    $panel = Filament::getPanel('admin');
    $nobody = User::factory()->create();

    expect($this->admin->canAccessPanel($panel))->toBeTrue()
        ->and($nobody->canAccessPanel($panel))->toBeFalse()
        // auch ohne Rolle
        ->and($this->fallback->roles)->toHaveCount(0)
        ->and($this->fallback->canAccessPanel($panel))->toBeTrue();
});

it('has no public registration', function () {
    expect(Filament::getPanel('admin')->hasRegistration())->toBeFalse();
    $this->get('/admin/register')->assertNotFound();
});

it('does not let another admin delete or change the fallback account', function () {
    $this->actingAs($this->admin);

    expect(Gate::allows('delete', $this->fallback))->toBeFalse()
        ->and(Gate::allows('update', $this->fallback))->toBeFalse();

    expect($this->fallback->delete())->toBeFalse();
    expect(User::query()->whereKey($this->fallback->id)->exists())->toBeTrue();

    $this->fallback->password = 'anders-gesetzt';
    expect($this->fallback->save())->toBeFalse();
});

it('also protects against the super-user gate', function () {
    $joerg = User::factory()->create(['email' => 'joerg@jaderbass.de']);
    $this->actingAs($joerg);

    expect(Gate::allows('delete', $this->fallback))->toBeFalse()
        ->and(Gate::allows('delete', $this->admin))->toBeTrue();
});

it('lets the fallback account manage itself', function () {
    $this->actingAs($this->fallback);

    expect(Gate::allows('update', $this->fallback))->toBeTrue()
        ->and(Gate::allows('delete', $this->fallback))->toBeTrue();

    $this->fallback->name = 'Neuer Name';
    expect($this->fallback->save())->toBeTrue();

    expect($this->fallback->delete())->toBeTrue();
});

it('keeps normal users manageable', function () {
    $this->actingAs($this->admin);
    $other = User::factory()->create();

    expect(Gate::allows('delete', $other))->toBeTrue()
        ->and($other->delete())->toBeTrue();
});

it('hides protected accounts from everyone else in the user list', function () {
    $this->actingAs($this->admin);
    expect(UserResource::getEloquentQuery()->pluck('id')->all())
        ->not->toContain($this->fallback->id)
        ->toContain($this->admin->id);

    $this->actingAs($this->fallback);
    expect(UserResource::getEloquentQuery()->pluck('id')->all())
        ->toContain($this->fallback->id)
        ->toContain($this->admin->id);
});
