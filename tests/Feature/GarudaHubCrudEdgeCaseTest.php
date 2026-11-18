<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarudaHubCrudEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_can_register_and_login(): void
    {
        $response = $this->post('/register', [
            'nama'     => 'Mahasiswa Baru',
            'email'    => 'mhs@example.com',
            'password' => 'rahasia123',
            'role'     => 'developer',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('users', [
            'email' => 'mhs@example.com',
            'role'  => 'developer',
        ]);

        $loginResponse = $this->post('/login', [
            'email'    => 'mhs@example.com',
            'password' => 'rahasia123',
        ]);

        $loginResponse->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_developer_cannot_submit_duplicate_bid_on_same_project(): void
    {
        // Developer ID 5 (Dewi Lestari) already bid on Project ID 1 in seeder
        $dewi = User::findOrFail(5);

        $response = $this->actingAs($dewi)->post('/bid', [
            'project_id' => 1,
            'harga'      => 6000000,
            'durasi'     => 20,
            'proposal'   => 'Penawaran kedua yang seharusnya ditolak.',
        ]);

        $response->assertRedirect(route('projects.show', 1));
        $response->assertSessionHas('flash', ['Anda sudah mengajukan penawaran pada proyek ini.', 'err']);
        $this->assertEquals(1, Bid::where('project_id', 1)->where('developer_id', 5)->count());
    }

    public function test_developer_cannot_bid_on_closed_project(): void
    {
        $developer = User::where('role', 'developer')->first();
        $closedProject = Project::where('status', 'ditutup')->first();

        $response = $this->actingAs($developer)->post('/bid', [
            'project_id' => $closedProject->id,
            'harga'      => 2000000,
            'durasi'     => 10,
            'proposal'   => 'Coba tawar proyek tertutup.',
        ]);

        $response->assertRedirect(route('projects.show', $closedProject->id));
        $response->assertSessionHas('flash', ['Lelang sudah ditutup; penawaran tidak dapat diajukan.', 'err']);
    }

    public function test_non_owner_klien_cannot_pick_winner(): void
    {
        // Project 1 belongs to Budi (ID 2), Siti (ID 3) tries to pick winner
        $siti = User::findOrFail(3);
        $bidOnProject1 = Bid::where('project_id', 1)->first();

        $response = $this->actingAs($siti)->post('/pick', [
            'project_id' => 1,
            'bid_id'     => $bidOnProject1->id,
        ]);

        $response->assertRedirect(route('projects.show', 1));
        $response->assertSessionHas('flash', ['Proyek tidak ditemukan atau bukan milik Anda.', 'err']);
        $this->assertDatabaseHas('projects', ['id' => 1, 'status' => 'terbuka']);
    }

    public function test_admin_cannot_modify_or_delete_own_account(): void
    {
        $admin = User::where('role', 'admin')->first();

        $this->actingAs($admin)->post('/admin/role', [
            'user_id' => $admin->id,
            'role'    => 'klien',
        ])->assertSessionHas('flash', ['Anda tidak dapat mengubah peran akun sendiri.', 'err']);

        $this->actingAs($admin)->post('/admin/delete-user', [
            'user_id' => $admin->id,
        ])->assertSessionHas('flash', ['Anda tidak dapat menghapus akun sendiri.', 'err']);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_deleting_user_cascades_to_projects_and_bids(): void
    {
        $admin = User::where('role', 'admin')->first();
        // Budi (ID 2) owns Project 1, 2, 7
        $budiProjectIds = Project::where('client_id', 2)->pluck('id')->all();
        $this->assertNotEmpty($budiProjectIds);

        $this->actingAs($admin)->post('/admin/delete-user', [
            'user_id' => 2,
        ])->assertSessionHas('flash', ['User dihapus.', 'ok']);

        $this->assertDatabaseMissing('users', ['id' => 2]);
        $this->assertEquals(0, Project::where('client_id', 2)->count());
        $this->assertEquals(0, Bid::whereIn('project_id', $budiProjectIds)->count());
    }
}
