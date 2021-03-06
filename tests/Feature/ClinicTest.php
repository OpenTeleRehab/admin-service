<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClinicTest extends TestCase
{
    /**
     * @group FeatureListClinicTest
     *
     * @return void
     */
    public function testListClinic()
    {
        $globalAdmin = $this->getCountryAdmin();
        $response = $this->actingAs($globalAdmin)->get('/api/clinic');
        $response->assertStatus(200);
    }

    /**
     * @group FeatureCreateClinicTest
     *
     * @return void
     */
    public function testCreateClinic()
    {
        $countryAdmin = $this->getCountryAdmin();
        $response = $this->actingAs($countryAdmin)->post('/api/clinic',[
            'name' => 'Vietnam',
            'country' => $countryAdmin->country_id,
            'region' => 'Hanoi',
            'province' => 'Hanoi',
            'city' => 'Hanoi'
        ]);
        $response->assertJson(['success' => true,"message" => "success_message.clinic_add"]);
        $this->assertDatabaseCount('clinics', 2);
        $this->assertDatabaseHas('clinics', [
            'name' => 'Vietnam'
        ]);
    }
}
