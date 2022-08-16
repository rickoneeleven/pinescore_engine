<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\alerts;

class DatabaseTest extends TestCase
{
    use WithFaker;
    use DatabaseTransactions;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testlastltaalertdata_column_has_been_migrated_added()
    {
        $testdata = [
            'email' => $this->faker->email,
            'lastLTAalertDate' => $this->faker->datetime
        ];
        
        $alert = new alerts;
        $alert->email = $testdata['email'];
        $alert->lastLTAalertDate = $testdata['lastLTAalertDate'];
        $alert->save($testdata);
        
        $this->assertDatabaseHas('alerts', $testdata);
    }
}
