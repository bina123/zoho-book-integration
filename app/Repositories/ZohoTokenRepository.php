<?php

namespace App\Repositories;

use App\Contracts\ZohoTokenStore;
use App\Models\ZohoToken;
use Carbon\Carbon;

final class ZohoTokenRepository implements ZohoTokenStore
{
    public function store(array $tokenData): ZohoToken
    {
        return ZohoToken::create([
            'token_type' => $tokenData['token_type'] ?? 'Bearer',
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_in' => (int) $tokenData['expires_in'],
            'expires_at' => Carbon::now()->addSeconds((int) $tokenData['expires_in']),
            'scope' => $tokenData['scope'] ?? null,
            'api_domain' => $tokenData['api_domain'] ?? null,
        ]);
    }

    public function update(ZohoToken $token, array $tokenData): ZohoToken
    {
        $token->update([
            'access_token' => $tokenData['access_token'],
            'expires_in' => (int) $tokenData['expires_in'],
            'expires_at' => Carbon::now()->addSeconds((int) $tokenData['expires_in']),
            'api_domain' => $tokenData['api_domain'] ?? $token->api_domain,
        ]);

        return $token->fresh();
    }

    public function getLatestToken(): ?ZohoToken
    {
        return ZohoToken::orderBy('created_at', 'desc')->first();
    }

    public function deleteOldTokens(int $keepLast = 1): void
    {
        $idsToKeep = ZohoToken::orderBy('created_at', 'desc')
            ->limit($keepLast)
            ->pluck('id')
            ->all();

        ZohoToken::whereNotIn('id', $idsToKeep)->delete();
    }
}
