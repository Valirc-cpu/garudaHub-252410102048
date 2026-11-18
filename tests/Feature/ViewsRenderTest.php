<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewsRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_views_render_correctly(): void
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertSee('Daftar Proyek')
            ->assertSee('Website Toko Batik Online');

        $this->get('/?q=Batik')
            ->assertStatus(200)
            ->assertSee('Hasil pencarian untuk:')
            ->assertSee('Website Toko Batik Online');

        $this->get('/project/1')
            ->assertStatus(200)
            ->assertSee('Website Toko Batik Online')
            ->assertSee('Penawaran');

        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('Masuk');

        $this->get('/register')
            ->assertStatus(200)
            ->assertSee('Daftar');

        $this->get('/project/99999')
            ->assertStatus(404);
    }

    public function test_authenticated_views_render_correctly(): void
    {
        $klien = User::where('role', 'klien')->first();
        $developer = User::where('role', 'developer')->first();
        $admin = User::where('role', 'admin')->first();

        $this->actingAs($klien)->get('/post')
            ->assertStatus(200)
            ->assertSee('Pasang Proyek');

        $this->actingAs($klien)->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Proyek Saya');

        $this->actingAs($developer)->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Penawaran Saya');

        $this->actingAs($admin)->get('/admin')
            ->assertStatus(200)
            ->assertSee('Kelola User')
            ->assertSee('Kelola Proyek');
    }
}
