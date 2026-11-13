<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_project_bid_relations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('role', 'klien')->has('projects')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isKlien());
        $this->assertFalse($user->isDeveloper());
        $this->assertFalse($user->isAdmin());

        $project = $user->projects()->first();
        $this->assertNotNull($project);
        $this->assertEquals($user->id, $project->client->id);

        $bid = Bid::with(['project', 'developer'])->first();
        $this->assertNotNull($bid);
        $this->assertInstanceOf(Project::class, $bid->project);
        $this->assertInstanceOf(User::class, $bid->developer);
        $this->assertTrue($bid->developer->isDeveloper());
    }
}
