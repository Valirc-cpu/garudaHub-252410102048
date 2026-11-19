<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Tampilkan formulir login.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Proses autentikasi user.
     */
    public function login(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email', 'max:100'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if (Auth::attempt(['email' => strtolower(trim($credentials['email'])), 'password' => $credentials['password']])) {
            $request->session()->regenerate();
            session()->flash('flash', ['Selamat datang kembali.', 'ok']);

            return $this->redirectBasedOnRole(Auth::user());
        }

        return back()
            ->withInput($request->only('email'))
            ->with('flash', ['Email atau password salah.', 'err']);
    }

    /**
     * Tampilkan formulir pendaftaran.
     */
    public function showRegisterForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.register');
    }

    /**
     * Proses pendaftaran user baru.
     */
    public function register(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        $validated = $request->validate([
            'nama'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'string', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:72'],
            'role'     => ['required', 'in:klien,developer'],
        ], [
            'nama.required'    => 'Nama wajib diisi (maksimal 100 karakter).',
            'email.email'      => 'Format email tidak valid.',
            'email.unique'     => 'Email sudah terdaftar.',
            'password.min'     => 'Password minimal 6 karakter.',
            'password.max'     => 'Password maksimal 72 karakter.',
            'role.in'          => 'Peran tidak valid.',
        ]);

        User::create([
            'nama'     => trim($validated['nama']),
            'email'    => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('flash', ['Pendaftaran berhasil. Silakan masuk.', 'ok']);
    }

    /**
     * Proses logout user.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('flash', ['Anda telah keluar.', 'ok']);
    }

    /**
     * Redirect user sesuai dengan perannya.
     */
    protected function redirectBasedOnRole(User $user): RedirectResponse
    {
        return $user->isAdmin()
            ? redirect()->route('admin.index')
            : redirect()->route('dashboard');
    }
}
