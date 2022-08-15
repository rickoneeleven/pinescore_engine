<?php

namespace Tests\Feature;

//use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\alerts;

class DatabaseTest extends TestCase
{
    use WithFaker;
    //, Refreshdatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testjizz()
    {
        $attributes = [
            'email' => 'crag@david.com',
            'lastLTAalertDate' => '2012-12-12'
        ];
        
        $alert = new alerts;
        $alert->save();
        
        
        //$this->assertDatabaseHas('alerts', $attributes);
    }
}
