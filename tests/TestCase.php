<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsUser(?User $user = null, string $role = 'user'): User
    {
        if (! Role::where('name', $role)->where('guard_name', 'api')->exists()) {
            $this->seed(RolePermissionSeeder::class);
        }

        $user ??= User::factory()->create();
        $user->assignRole($role);

        $token = auth('api')->login($user);
        auth('api')->forgetUser();

        $this->withHeader('Authorization', 'Bearer '.$token);

        return $user;
    }
}
