<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /**
     * PII-safe user representation.
     * NEVER includes: password, remember_token, or any hashed credential.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'roles'        => $this->getRoleNames()->values()->all(),
            'primary_role' => $this->primary_role,
        ];
    }
}
