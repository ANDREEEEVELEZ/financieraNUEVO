<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RefreshToken extends Model
{
    /**
     * This table only tracks creation time — there is no updated_at column,
     * so Eloquent's automatic timestamp management is disabled entirely and
     * created_at is populated by the database default (useCurrent()).
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'access_token_id',
        'token',
        'device_name',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Only tokens that are neither revoked nor expired.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * Issue a new refresh token for the given user/device, linked to a
     * Sanctum personal access token id. Returns the persisted model plus the
     * plaintext value (only ever available at issuance time — never stored).
     *
     * @return array{model: self, plaintext: string}
     */
    public static function issueFor(User $user, string $deviceName, ?int $accessTokenId): array
    {
        $plaintext = Str::random(64);

        $model = self::create([
            'user_id'         => $user->id,
            'access_token_id' => $accessTokenId,
            'token'           => hash('sha256', $plaintext),
            'device_name'     => $deviceName,
            'expires_at'      => now()->addDays((int) config('api_auth.refresh_token_ttl_days')),
            'created_at'      => now(),
        ]);

        return ['model' => $model, 'plaintext' => $plaintext];
    }
}
