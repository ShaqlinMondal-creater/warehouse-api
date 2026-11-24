<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'owner-password',
            'role' => 'owner',
        ]);
    }

    public function test_owner_can_create_list_update_and_delete_guests(): void
    {
        Sanctum::actingAs($this->owner());

        $created = $this->postJson('/api/guests', [
            'name' => 'Rafi Ahmed',
            'email' => 'Rafi@Example.com',
            'password' => 'guest-pass-1',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'guest')
            ->assertJsonPath('data.email', 'rafi@example.com')
            ->assertJsonPath('data.avatar_initials', 'RA');

        $id = $created->json('data.id');

        $this->getJson('/api/guests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->patchJson("/api/guests/{$id}", ['name' => 'Rafi Khan'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rafi Khan')
            ->assertJsonPath('data.avatar_initials', 'RK');

        $this->deleteJson("/api/guests/{$id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_guest_email_must_be_unique_and_password_long_enough(): void
    {
        Sanctum::actingAs($this->owner());

        $this->postJson('/api/guests', [
            'name' => 'Dup',
            'email' => 'owner@example.com',
            'password' => 'guest-pass-1',
        ])->assertStatus(422);

        $this->postJson('/api/guests', [
            'name' => 'Short',
            'email' => 'short@example.com',
            'password' => '123',
        ])->assertStatus(422);
    }

    public function test_owner_endpoints_cannot_touch_non_guest_users(): void
    {
        $owner = $this->owner();
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/guests/{$owner->id}")->assertNotFound();
        $this->patchJson("/api/guests/{$owner->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'name' => 'Owner']);
    }

    public function test_login_portals_keep_account_types_separate(): void
    {
        $this->owner();
        User::query()->create([
            'name' => 'Guest One',
            'email' => 'guest@example.com',
            'password' => 'guest-pass-1',
            'role' => 'guest',
        ]);

        $this->postJson('/api/login', [
            'email' => 'guest@example.com',
            'password' => 'guest-pass-1',
            'portal' => 'guest',
        ])->assertOk()->assertJsonPath('user.role', 'guest');

        // Guest on the main login page
        $this->postJson('/api/login', [
            'email' => 'guest@example.com',
            'password' => 'guest-pass-1',
        ])->assertStatus(422);

        // Owner on the guest login page
        $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'owner-password',
            'portal' => 'guest',
        ])->assertStatus(422);

        // Owner on the main login page still works
        $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'owner-password',
        ])->assertOk()->assertJsonPath('user.role', 'owner');

        // Wrong password reveals nothing about account type
        $this->postJson('/api/login', [
            'email' => 'guest@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
    }

    public function test_guest_token_can_use_me_but_not_owner_endpoints(): void
    {
        $guest = User::query()->create([
            'name' => 'Guest One',
            'email' => 'guest@example.com',
            'password' => 'guest-pass-1',
            'role' => 'guest',
        ]);
        Sanctum::actingAs($guest);

        $this->getJson('/api/me')->assertOk()->assertJsonPath('role', 'guest');
        $this->getJson('/api/guests')->assertForbidden();
        $this->getJson('/api/overview')->assertForbidden();
        $this->getJson('/api/exams')->assertForbidden();
    }

    public function test_password_change_signs_the_guest_out(): void
    {
        Sanctum::actingAs($this->owner());

        $guest = User::query()->create([
            'name' => 'Guest One',
            'email' => 'guest@example.com',
            'password' => 'guest-pass-1',
            'role' => 'guest',
        ]);
        $guest->createToken('test');
        $this->assertSame(1, $guest->tokens()->count());

        $this->patchJson("/api/guests/{$guest->id}", ['password' => 'new-guest-pass'])->assertOk();

        $this->assertSame(0, $guest->fresh()->tokens()->count());
    }
}
