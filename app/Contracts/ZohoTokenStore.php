<?php

namespace App\Contracts;

use App\Models\ZohoToken;

interface ZohoTokenStore
{
    public function store(array $tokenData): ZohoToken;

    public function update(ZohoToken $token, array $tokenData): ZohoToken;

    public function getLatestToken(): ?ZohoToken;

    public function deleteOldTokens(int $keepLast = 1): void;
}
