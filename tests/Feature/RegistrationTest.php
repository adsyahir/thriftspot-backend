<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_with_user_role(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->postJson('/api/auth/register', [
            'email' => 'new@example.com',
            'username' => 'newbie',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated();

        $this->assertTrue(User::where('email', 'new@example.com')->firstOrFail()->hasRole('user'));
    }

    public function test_register_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/auth/register', [
            'email' => 'dup@example.com',
            'username' => 'someone',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}
