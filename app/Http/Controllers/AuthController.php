<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Jeśli już zalogowany — przekieruj na dashboard (spójność z showLoginForm)
        if (Auth::guard('admin')->check()) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Login jest wymagany.',
            'password.required' => 'Hasło jest wymagane.',
        ]);

        $username = (string) $request->input('username');
        $throttleKey = 'login:' . $request->ip() . ':' . $username;

        // Sprawdź rate limit PRZED próbą logowania
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::warning('Admin login rate-limited', [
                'username'   => $username,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'retry_in'   => $seconds,
            ]);

            throw ValidationException::withMessages([
                'username' => "Zbyt wiele prób logowania. Spróbuj ponownie za {$seconds} sekund.",
            ]);
        }

        $credentials = $request->only('username', 'password');

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            Log::info('Admin login successful', [
                'username'   => $username,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey, 60);

        Log::warning('Admin login failed', [
            'username'   => $username,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        throw ValidationException::withMessages([
            'username' => 'Nieprawidłowy login lub hasło.',
        ]);
    }

    public function logout(Request $request)
    {
        // Sprawdź, czy w ogóle był zalogowany (żeby nie psuć sesji gościa)
        if (!Auth::guard('admin')->check()) {
            return redirect()->route('login.form');
        }

        $username = Auth::guard('admin')->user()?->username;

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('Admin logout', [
            'username' => $username,
            'ip'       => $request->ip(),
        ]);

        return redirect()->route('login.form');
    }
}