<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_promote_self(): void
    {
        $u = $this->actingAsUser();

        $this->postJson("/api/users/{$u->id}/roles", ['role' => 'admin'])->assertForbidden();

        $this->assertFalse($u->fresh()->hasRole('admin'));
    }

    public function test_regular_user_cannot_list_roles_or_users(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/roles')->assertForbidden();
        $this->getJson('/api/permissions')->assertForbidden();
        $this->getJson('/api/users/roles')->assertForbidden();
    }

    public function test_admin_can_assign_and_remove_role(): void
    {
        $this->actingAsUser(role: 'admin');
        $target = User::factory()->create();

        $this->postJson("/api/users/{$target->id}/roles", ['role' => 'admin'])->assertOk();
        $this->assertTrue($target->fresh()->hasRole('admin'));

        $this->deleteJson("/api/users/{$target->id}/roles", ['role' => 'admin'])->assertOk();
        $this->assertFalse($target->fresh()->hasRole('admin'));
    }

    public function test_last_admin_cannot_lose_admin(): void
    {
        $admin = $this->actingAsUser(role: 'admin');

        $this->deleteJson("/api/users/{$admin->id}/roles", ['role' => 'admin'])->assertStatus(422);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_core_roles_cannot_be_deleted_or_renamed(): void
    {
        $this->actingAsUser(role: 'admin');

        foreach (['admin', 'user'] as $name) {
            $role = Role::where('name', $name)->firstOrFail();

            $this->deleteJson("/api/roles/{$role->id}")->assertStatus(422);
            $this->putJson("/api/roles/{$role->id}", ['name' => $name.'-renamed'])->assertStatus(422);
            $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => $name]);
        }
    }

    public function test_core_permissions_cannot_be_deleted_or_renamed(): void
    {
        $this->actingAsUser(role: 'admin');

        foreach (['view-users', 'manage-settings'] as $name) {
            $permission = Permission::where('name', $name)->firstOrFail();

            $this->deleteJson("/api/permissions/{$permission->id}")->assertStatus(422);
            $this->putJson("/api/permissions/{$permission->id}", ['name' => $name.'-renamed'])->assertStatus(422);
            $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => $name]);
        }
    }
}
