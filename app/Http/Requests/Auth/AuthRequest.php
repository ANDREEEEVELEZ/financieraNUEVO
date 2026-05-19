<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates authentication credentials.
 * Replaces raw $request->validate() calls in login controllers.
 */
final class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Login is public — authorization is handled by the auth attempt itself
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
