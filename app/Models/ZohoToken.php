<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoToken extends Model
{
    protected $fillable = [
        'token_type',
        'access_token',
        'refresh_token',
        'expires_in',
        'expires_at',
        'scope',
        'api_domain',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        $bufferSeconds = (int) config('zoho.token_expiry_buffer', 300);

        return $this->expires_at === null
            || $this->expires_at->subSeconds($bufferSeconds)->isPast();
    }
}
