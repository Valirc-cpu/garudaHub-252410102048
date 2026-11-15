<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAndRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_from_protected_routes(): void
    {
        $this->get('/post')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_role_middleware_blocks_unauthorized_roles(): void
    {
        $klien = User::where('role', 'klien')->first();
        $developer = User::where('role', 'developer')->first();

        // Klien cannot access admin panel
        $this->actingAs($klien)->get('/admin')->assertStatus(403);

        // Developer cannot access post project or admin panel
        $this->actingAs($developer)->get('/post')->assertStatus(403);
        $this->actingAs($developer)->get('/admin')->assertStatus(403);
    }

    public function test_auth_controller_login_and_logout(): void
    {
        // Login failed
        $response = $this->post('/login', [
            'email'    => 'admin@example.com',
            'password' => 'wrongpassword',
        ]);
        $response->assertSessionHas('flash');

        // Login success
        $response = $this->post('/login', [
            'email'    => 'admin@example.com',
            'password' => 'admin123',
        ]);
        $response->assertRedirect('/admin');
        $this->assertAuthenticated();

        // Logout
        $response = $this->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_klien_can_create_project(): void
    {
        $klien = User::where('role', 'klien')->first();

        $response = $this->actingAs($klien)->post('/post', [
            'judul'     => 'Proyek Test Baru',
            'deskripsi' => 'Deskripsi proyek pengujian',
            'kategori'  => 'Web',
            'bmin'      => 1000000,
            'bmax'      => 5000000,
            'batas'     => date('Y-m-d', strtotime('+7 days')),
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('projects', [
            'judul'     => 'Proyek Test Baru',
            'client_id' => $klien->id,
        ]);
    }

    public function test_developer_can_place_bid(): void
    {
        $developer = User::where('role', 'developer')->first();
        $project = Project::where('status', 'terbuka')->first();

        // Ensure developer hasn't bid on this project yet
        Bid::where('project_id', $project->id)->where('developer_id', $developer->id)->delete();

        $response = $this->actingAs($developer)->post('/bid', [
            'project_id' => $project->id,
            'harga'      => 2500000,
            'durasi'     => 14,
            'proposal'   => 'Saya mampu menyelesaikan proyek ini tepat waktu.',
        ]);

        $response->assertRedirect(route('projects.show', $project->id));
        $this->assertDatabaseHas('bids', [
            'project_id'   => $project->id,
            'developer_id' => $developer->id,
            'harga'        => 2500000,
        ]);
    }

    public function test_klien_can_pick_winner(): void
    {
        $klien = User::where('role', 'klien')->first();
        $project = Project::where('client_id', $klien->id)->where('status', 'terbuka')->first();
        $bid = $project->bids()->first();

        $response = $this->actingAs($klien)->post('/pick', [
            'project_id' => $project->id,
            'bid_id'     => $bid->id,
        ]);

        $response->assertRedirect(route('projects.show', $project->id));
        $this->assertDatabaseHas('bids', [
            'id'        => $bid->id,
            'is_winner' => true,
        ]);
        $this->assertDatabaseHas('projects', [
            'id'     => $project->id,
            'status' => 'ditutup',
        ]);
    }

    public function test_admin_operations(): void
    {
        $admin = User::where('role', 'admin')->first();
        $klien = User::where('role', 'klien')->first();

        // Update role
        $response = $this->actingAs($admin)->post('/admin/role', [
            'user_id' => $klien->id,
            'role'    => 'developer',
        ]);
        $response->assertSessionHas('flash');
        $this->assertDatabaseHas('users', [
            'id'   => $klien->id,
            'role' => 'developer',
        ]);

        // Delete project
        $project = Project::first();
        $response = $this->actingAs($admin)->post('/admin/delete-project', [
            'project_id' => $project->id,
        ]);
        $response->assertSessionHas('flash');
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
