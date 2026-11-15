<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Tampilkan panel admin kelola user dan proyek.
     */
    public function index(): View
    {
        $users = User::orderBy('id', 'asc')->get();
        $projects = Project::with('client')->orderBy('id', 'desc')->get();

        return view('admin.index', compact('users', 'projects'));
    }

    /**
     * Ubah peran pengguna.
     */
    public function updateRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role'    => ['required', 'in:klien,developer,admin'],
        ]);

        $targetId = (int) $validated['user_id'];

        if ($targetId === Auth::id()) {
            return back()->with('flash', ['Anda tidak dapat mengubah peran akun sendiri.', 'err']);
        }

        $user = User::findOrFail($targetId);
        $user->update(['role' => $validated['role']]);

        return back()->with('flash', ['Peran user diubah.', 'ok']);
    }

    /**
     * Hapus pengguna beserta seluruh proyek dan penawarannya.
     */
    public function deleteUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $targetId = (int) $validated['user_id'];

        if ($targetId === Auth::id()) {
            return back()->with('flash', ['Anda tidak dapat menghapus akun sendiri.', 'err']);
        }

        $user = User::findOrFail($targetId);
        $user->delete();

        return back()->with('flash', ['User dihapus.', 'ok']);
    }

    /**
     * Hapus proyek beserta seluruh penawarannya.
     */
    public function deleteProject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $project = Project::findOrFail((int) $validated['project_id']);
        $project->delete();

        return back()->with('flash', ['Proyek dihapus.', 'ok']);
    }
}
