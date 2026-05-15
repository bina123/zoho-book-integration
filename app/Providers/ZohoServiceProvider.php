<?php

namespace App\Providers;

use App\Contracts\BudgetStore;
use App\Contracts\OrganizationStore;
use App\Contracts\ZohoAuthClient;
use App\Contracts\ZohoBooksClient;
use App\Contracts\ZohoTokenStore;
use App\Repositories\BudgetRepository;
use App\Repositories\ZohoTokenRepository;
use App\Services\OrganizationService;
use App\Services\ZohoAuthService;
use App\Services\ZohoBooksService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

final class ZohoServiceProvider extends ServiceProvider
{
    /**
     * Map of contract → concrete implementation. Listed here so the wiring
     * lives in one place and concretes can be swapped without hunting them down.
     */
    public array $bindings = [
        ZohoTokenStore::class => ZohoTokenRepository::class,
        BudgetStore::class => BudgetRepository::class,
        OrganizationStore::class => OrganizationService::class,
        ZohoAuthClient::class => ZohoAuthService::class,
        ZohoBooksClient::class => ZohoBooksService::class,
    ];

    public function register(): void
    {
        // Single shared Guzzle client. Lets tests swap it via $this->app->instance(Client::class, $mock).
        $this->app->singleton(Client::class, function () {
            return new Client([
                'timeout' => 60,
                'verify' => $this->app->environment('production'),
                'http_errors' => false,
            ]);
        });
    }
}
