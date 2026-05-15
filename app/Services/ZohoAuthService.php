<?php

namespace App\Services;

use App\Contracts\ZohoAuthClient;
use App\Contracts\ZohoTokenStore;
use App\Exceptions\ZohoApiException;
use App\Models\ZohoToken;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ZohoAuthService implements ZohoAuthClient
{
    protected string $accountsUrl;

    protected string $clientId;

    protected string $clientSecret;

    protected string $redirectUri;

    public function __construct(
        protected ZohoTokenStore $tokenStore,
        protected Client $client,
    ) {
        $this->accountsUrl = rtrim((string) config('zoho.urls.accounts'), '/');
        $this->clientId = (string) config('zoho.client_id');
        $this->clientSecret = (string) config('zoho.client_secret');
        $this->redirectUri = (string) config('zoho.redirect_uri');
    }

    public function getAuthorizationUrl(?string $state = null): string
    {
        $params = http_build_query(array_filter([
            'scope' => implode(',', (array) config('zoho.scopes', [])),
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]));

        return "{$this->accountsUrl}/oauth/v2/auth?{$params}";
    }

    public function exchangeCodeForTokens(string $code): ZohoToken
    {
        try {
            $response = $this->client->post("{$this->accountsUrl}/oauth/v2/token", [
                'form_params' => [
                    'code' => $code,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri' => $this->redirectUri,
                    'grant_type' => 'authorization_code',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true) ?: [];

            if (isset($data['error']) || ! isset($data['access_token'])) {
                throw new ZohoApiException(
                    $data['error'] ?? 'Token exchange failed',
                    400,
                    $data
                );
            }

            $token = $this->tokenStore->store($data);
            $this->cacheAccessToken($token);
            $this->tokenStore->deleteOldTokens();

            Log::info('Zoho tokens obtained successfully');

            return $token;
        } catch (GuzzleException $e) {
            Log::error('Zoho token exchange failed', ['error' => $e->getMessage()]);

            throw new ZohoApiException(
                'Failed to exchange authorization code',
                500,
                ['details' => $e->getMessage()]
            );
        }
    }

    public function refreshAccessToken(?ZohoToken $token = null): ZohoToken
    {
        $token = $token ?? $this->tokenStore->getLatestToken();

        if (! $token || ! $token->refresh_token) {
            throw new ZohoApiException('No refresh token available. Re-authorization required.', 401);
        }

        try {
            $response = $this->client->post("{$this->accountsUrl}/oauth/v2/token", [
                'form_params' => [
                    'refresh_token' => $token->refresh_token,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'refresh_token',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true) ?: [];

            if (isset($data['error']) || ! isset($data['access_token'])) {
                throw new ZohoApiException(
                    $data['error'] ?? 'Token refresh failed',
                    400,
                    $data
                );
            }

            $refreshed = $this->tokenStore->update($token, $data);
            $this->cacheAccessToken($refreshed);

            Log::info('Zoho access token refreshed successfully');

            return $refreshed;
        } catch (GuzzleException $e) {
            Log::error('Zoho token refresh failed', ['error' => $e->getMessage()]);

            throw new ZohoApiException(
                'Failed to refresh access token',
                500,
                ['details' => $e->getMessage()]
            );
        }
    }

    public function getValidAccessToken(): string
    {
        $cachedToken = Cache::get(config('zoho.token_cache_key'));

        if ($cachedToken) {
            return $cachedToken;
        }

        $token = $this->tokenStore->getLatestToken();

        if (! $token) {
            throw new ZohoApiException('No token found. Authorization required.', 401);
        }

        if ($token->isExpired()) {
            $token = $this->refreshAccessToken($token);
        } else {
            $this->cacheAccessToken($token);
        }

        return $token->access_token;
    }

    public function hasValidToken(): bool
    {
        return $this->tokenStore->getLatestToken() !== null;
    }

    protected function cacheAccessToken(ZohoToken $token): void
    {
        $bufferSeconds = (int) config('zoho.token_expiry_buffer', 300);
        $ttl = (int) max(now()->diffInSeconds($token->expires_at, false) - $bufferSeconds, 60);

        Cache::put(
            config('zoho.token_cache_key'),
            $token->access_token,
            $ttl
        );
    }

    public function revokeToken(): bool
    {
        $token = $this->tokenStore->getLatestToken();

        if (! $token) {
            return true;
        }

        try {
            $this->client->post("{$this->accountsUrl}/oauth/v2/token/revoke", [
                'form_params' => [
                    'token' => $token->refresh_token ?? $token->access_token,
                ],
            ]);

            Cache::forget(config('zoho.token_cache_key'));
            $token->delete();

            Log::info('Zoho token revoked successfully');

            return true;
        } catch (GuzzleException $e) {
            Log::error('Zoho token revocation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
