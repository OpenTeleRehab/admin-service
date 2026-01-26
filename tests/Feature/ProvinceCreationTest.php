<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Region;
use App\Models\Country;
use App\Models\Province;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ProvinceCreationTest extends TestCase
{
    // TestCase already does migrate:fresh in setUp via initDefaultData,
    // but RefreshDatabase is safer for individual tests if TestCase doesn't handle it per test.
    // However, TestCase::setUp does migrate:fresh... that's slow but clean.

    private $country;
    private $region1;
    private $region2;

    public function setUp(): void
    {
        parent::setUp();

        // Manual setup as factories might be unreliable based on inspection
        $this->country = Country::create(['name' => 'Test Country', 'code' => 'TC']);

        $this->region1 = Region::create([
            'country_id' => $this->country->id,
            'name' => 'Region 1',
            'therapist_limit' => 100,
            'phc_worker_limit' => 100
        ]);

        $this->region2 = Region::create([
            'country_id' => $this->country->id,
            'name' => 'Region 2',
            'therapist_limit' => 100,
            'phc_worker_limit' => 100
        ]);
    }

    private function createUser($attributes = [])
    {
        return User::forceCreate(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'type' => User::ADMIN_GROUP_REGIONAL_ADMIN,
            'enabled' => true,
            'country_id' => $this->country->id,
        ], $attributes));
    }

    public function test_single_region_admin_can_create_province_implicitly()
    {
        $user = $this->createUser(['region_id' => $this->region1->id]);

        $response = $this->actingAs($user)->postJson('/api/provinces', [
            'name' => 'Province A',
            'therapist_limit' => 10,
            'phc_worker_limit' => 10,
            // No region_id provided
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('provinces', [
            'name' => 'Province A',
            'region_id' => $this->region1->id
        ]);
    }

    public function test_multi_region_admin_can_create_province_with_explicit_region_id()
    {
        // User with no specific region, but access to region 1 and 2 via Regions
        $user = $this->createUser(['region_id' => null]);

        // attach regions
        DB::table('region_admin')->insert([
            ['regional_admin_id' => $user->id, 'region_id' => $this->region1->id],
            ['regional_admin_id' => $user->id, 'region_id' => $this->region2->id],
        ]);

        $response = $this->actingAs($user)->postJson('/api/provinces', [
            'name' => 'Province B',
            'therapist_limit' => 10,
            'phc_worker_limit' => 10,
            'region_id' => $this->region2->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('provinces', [
            'name' => 'Province B',
            'region_id' => $this->region2->id
        ]);
    }

    public function test_multi_region_admin_cannot_create_province_without_region_id()
    {
        $user = $this->createUser(['region_id' => null]);

        // attach regions
        DB::table('region_admin')->insert([
            ['regional_admin_id' => $user->id, 'region_id' => $this->region1->id],
        ]);

        $response = $this->actingAs($user)->postJson('/api/provinces', [
            'name' => 'Province C',
            'therapist_limit' => 10,
            'phc_worker_limit' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['region_id']);
    }

    public function test_multi_region_admin_cannot_create_province_in_unauthorized_region()
    {
        $user = $this->createUser(['region_id' => null]);

        // attach regions (only region 1)
        DB::table('region_admin')->insert([
            ['regional_admin_id' => $user->id, 'region_id' => $this->region1->id],
        ]);

        $response = $this->actingAs($user)->postJson('/api/provinces', [
            'name' => 'Province D',
            'therapist_limit' => 10,
            'phc_worker_limit' => 10,
            'region_id' => $this->region2->id,
        ]);

        $response->assertStatus(403);
    }
}
