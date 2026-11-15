<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tampilkan halaman dasbor sesuai peran user (klien / developer).
     */
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.index');
        }

        if ($user->isKlien()) {
            $projects = Project::where('client_id', $user->id)
                ->withCount('bids')
                ->orderBy('id', 'desc')
                ->get();

            return view('dashboard.index', compact('projects'));
        }

        // Developer
        $bids = Bid::where('developer_id', $user->id)
            ->with(['project.client'])
            ->orderBy('id', 'desc')
            ->get();

        return view('dashboard.index', compact('bids'));
    }
}
