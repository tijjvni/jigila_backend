<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProfileService();
    }

    public function test_update_modifies_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $updated = $this->service->update($user, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_modifies_user_phone(): void
    {
        $user = User::factory()->create(['phone' => '080']);

        $updated = $this->service->update($user, ['phone' => '090']);

        $this->assertEquals('090', $updated->phone);
    }

    public function test_update_returns_the_same_user_instance(): void
    {
        $user = User::factory()->create();

        $returned = $this->service->update($user, ['name' => 'Same User']);

        $this->assertSame($user->id, $returned->id);
    }

    public function test_update_hashes_password_when_provided(): void
    {
        $user = User::factory()->create();

        $this->service->update($user, ['password' => 'newpassword']);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword', $user->fresh()->password));
    }
}
