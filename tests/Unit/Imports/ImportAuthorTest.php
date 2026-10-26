<?php

use App\Models\User;
use App\Support\Concerns\HasImportAuthor;
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
});

function importAuthor(): object
{
    return new class
    {
        use HasImportAuthor;

        public function author(): ?int
        {
            return $this->resolveAuthorId();
        }
    };
}

it('uses the set author, otherwise the first admin, otherwise the first user', function () {
    expect(importAuthor()->author())->toBeNull();

    $first = User::factory()->create();
    expect(importAuthor()->author())->toBe($first->id);

    Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $admin = User::factory()->create()->assignRole('Admin');
    expect(importAuthor()->author())->toBe($admin->id)
        ->and(importAuthor()->setAuthorId($first->id)->author())->toBe($first->id);
});
