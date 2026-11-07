<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));

        $this->assertSame(url('/auth/login'), route('login'));
    }

    public function test_short_auth_aliases_redirect_to_auth_prefix(): void
    {
        $this->get('/login')->assertRedirect('/auth/login');
        $this->get('/register')->assertRedirect('/auth/register');
    }

    public function test_root_redirects_member_to_stocks(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/')
            ->assertRedirect(route('stocks.index'));
    }

    public function test_root_redirects_admin_to_admin_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('admin.users'));
    }

    public function test_authenticated_user_cannot_open_guest_pages(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/auth/login')
            ->assertRedirect();
    }

    public function test_health_check_endpoint(): void
    {
        $this->get('/up')
            ->assertStatus(200);
    }
}
