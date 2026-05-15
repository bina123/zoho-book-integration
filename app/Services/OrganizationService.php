<?php

namespace App\Services;

use App\Contracts\OrganizationStore;
use App\Models\ZohoSetting;

/**
 * Reads / writes the selected Zoho organization.
 *
 * Persisted in the `zoho_settings` singleton table (decoupled from OAuth tokens
 * so the selection survives disconnect / reconnect cycles). Falls back to the
 * ZOHO_ORGANIZATION_ID env variable for headless / CI bootstrapping.
 */
final class OrganizationService implements OrganizationStore
{
    public function setOrganization(string $organizationId, ?string $organizationName = null): void
    {
        $setting = ZohoSetting::singleton();

        $setting->update(array_filter([
            'organization_id' => $organizationId,
            'organization_name' => $organizationName,
        ], static fn ($v) => $v !== null));
    }

    public function getOrganizationId(): ?string
    {
        $stored = ZohoSetting::find(ZohoSetting::SINGLETON_ID)?->organization_id;
        $orgId = $stored ?: config('zoho.organization_id');

        return $orgId ? (string) $orgId : null;
    }

    public function getOrganizationName(): ?string
    {
        return ZohoSetting::find(ZohoSetting::SINGLETON_ID)?->organization_name;
    }
}
