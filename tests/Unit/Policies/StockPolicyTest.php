<?php

namespace Tests\Unit\Policies;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Policies\StockPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any(): void
    {
        $this->assertTrue((new StockPolicy())->viewAny(User::factory()->create()));
    }

    public function test_view_policy(): void
    {
        $policy = new StockPolicy();
        $owner = User::factory()->member()->create();
        $otherMember = User::factory()->member()->create();
        $admin = User::factory()->admin()->create();

        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($policy->view($owner, $stock));
        $this->assertTrue($policy->view($admin, $stock));
        $this->assertFalse($policy->view($otherMember, $stock));
    }

    public function test_create_policy(): void
    {
        $this->assertTrue((new StockPolicy())->create(User::factory()->create()));
    }

    public function test_update_policy(): void
    {
        $policy = new StockPolicy();
        $owner = User::factory()->member()->create();
        $otherMember = User::factory()->member()->create();
        $admin = User::factory()->admin()->create();

        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($policy->update($owner, $stock));
        $this->assertFalse($policy->update($otherMember, $stock));
        $this->assertFalse($policy->update($admin, $stock)); // Admin is read-only
    }

    public function test_delete_policy(): void
    {
        $policy = new StockPolicy();
        $owner = User::factory()->member()->create();
        $otherMember = User::factory()->member()->create();
        $admin = User::factory()->admin()->create();

        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($policy->delete($owner, $stock));
        $this->assertFalse($policy->delete($otherMember, $stock));
        $this->assertFalse($policy->delete($admin, $stock)); // Admin is read-only
    }
}
