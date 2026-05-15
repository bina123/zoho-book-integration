<?php

namespace App\Services;

use App\Contracts\OrganizationStore;
use App\Contracts\ZohoTokenStore;

/**
 * Reads/writes the selected Zoho organization. Backed by the latest ZohoToken
 * row (so a single org is bound to a single auth session) with .env fallback.
 */
final class OrganizationService implements OrganizationStore
{
    public function __construct(protected ZohoTokenStore $tokenStore) {}

    public function setOrganization(string $organizationId, ?string $organizationName = null): void
    {
        $token = $this->tokenStore->getLatestToken();

        if ($token === null) {
            return;
        }

        $token->update(array_filter([
            'organization_id' => $organizationId,
            'organization_name' => $organizationName,
        ], static fn ($v) => $v !== null));
    }

    public function getOrganizationId(): ?string
    {
        $token = $this->tokenStore->getLatestToken();
        $orgId = $token?->organization_id ?: config('zoho.organization_id');

        return $orgId ? (string) $orgId : null;
    }

    public function getOrganizationName(): ?string
    {
        return $this->tokenStore->getLatestToken()?->organization_name;
    }
}
