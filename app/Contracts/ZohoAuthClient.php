<?php

namespace App\Contracts;

use App\Models\ZohoToken;

interface ZohoAuthClient
{
    public function getAuthorizationUrl(?string $state = null): string;

    public function exchangeCodeForTokens(string $code): ZohoToken;

    public function refreshAccessToken(?ZohoToken $token = null): ZohoToken;

    public function getValidAccessToken(): string;

    public function hasValidToken(): bool;

    public function revokeToken(): bool;
}
