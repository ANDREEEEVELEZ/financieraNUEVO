<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomLoginController extends Controller
{
    public function store(Request $request)
    {
        // Log without PII — email/password never written to log storage
        Log::debug('Login attempt', ['ip' => $request->ip()]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            Log::debug('Login successful', [
                'user_id' => $user->id,
                'ip'      => $request->ip(),
            ]);

            return redirect()->intended('dashboard');
        }

        Log::debug('Login failed: Invalid credentials', ['ip' => $request->ip()]);
        
        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->withInput($request->only('email', 'remember'));
    }
}