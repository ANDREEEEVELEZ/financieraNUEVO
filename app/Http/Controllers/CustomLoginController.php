<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomLoginController extends Controller
{
    public function store(Request $request)
    {
        Log::debug('Login attempt', [
            'email' => $request->input('email'),
            'has_password' => !empty($request->input('password')),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            Log::debug('Login successful', [
                'user_id' => $user->id,
                'name' => $user->name,
                'active' => $user->active,
                'roles' => $user->getRoleNames()->toArray()
            ]);
            
            return redirect()->intended('dashboard');
        }

        Log::debug('Login failed: Invalid credentials');
        
        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->withInput($request->only('email', 'remember'));
    }
}