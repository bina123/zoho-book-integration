<?php

namespace App\Contracts;

interface OrganizationStore
{
    public function setOrganization(string $organizationId, ?string $organizationName = null): void;

    public function getOrganizationId(): ?string;

    public function getOrganizationName(): ?string;
}
