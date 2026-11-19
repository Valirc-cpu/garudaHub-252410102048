<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Halaman Beranda / Daftar Proyek (Mendukung rute normal dan parameter ?page=)
Route::get('/', function (Request $request) {
    if ($request->has('page')) {
        $page = $request->query('page');
        return match ($page) {
            'project'   => redirect()->route('projects.show', ['id' => (int) $request->query('id')]),
            'login'     => redirect()->route('login'),
            'register'  => redirect()->route('register'),
            'post'      => redirect()->route('projects.create'),
            'dashboard' => redirect()->route('dashboard'),
            'admin'     => redirect()->route('admin.index'),
            default     => app(ProjectController::class)->index($request),
        };
    }
    return app(ProjectController::class)->index($request);
})->name('home');

Route::get('/project/{id}', [ProjectController::class, 'show'])->whereNumber('id')->name('projects.show');

// Autentikasi (Tamu)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Aset statis & file standar dengan header keamanan lengkap
Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow:\n", 200)
        ->header('Content-Type', 'text/plain; charset=utf-8');
});

Route::get('/sitemap.xml', function () {
    return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>', 200)
        ->header('Content-Type', 'application/xml; charset=utf-8');
});

Route::get('/favicon.ico', function () {
    return response('', 204)
        ->header('Content-Type', 'image/x-icon');
});

Route::get('/css/style.css', function () {
    $path = public_path('css/style.css');
    return response(file_exists($path) ? file_get_contents($path) : '', 200)
        ->header('Content-Type', 'text/css; charset=utf-8')
        ->header('Cache-Control', 'public, max-age=86400');
});

// Logout (User terautentikasi)
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Rute Klien
Route::middleware(['role:klien'])->group(function () {
    Route::get('/post', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/post', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/pick', [BidController::class, 'pickWinner'])->name('bids.pick');
});

// Rute Developer
Route::middleware(['role:developer'])->group(function () {
    Route::post('/bid', [BidController::class, 'store'])->name('bids.store');
});

// Rute Dasbor (Klien & Developer)
Route::middleware(['role:klien,developer'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Rute Admin
Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::post('/role', [AdminController::class, 'updateRole'])->name('role');
    Route::post('/delete-user', [AdminController::class, 'deleteUser'])->name('deleteUser');
    Route::post('/delete-project', [AdminController::class, 'deleteProject'])->name('deleteProject');
});
