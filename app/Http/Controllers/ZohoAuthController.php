<?php

namespace App\Http\Controllers;

use App\Contracts\OrganizationStore;
use App\Contracts\ZohoAuthClient;
use App\Contracts\ZohoBooksClient;
use App\Contracts\ZohoTokenStore;
use App\Exceptions\ZohoApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class ZohoAuthController extends Controller
{
    public function __construct(
        protected ZohoAuthClient $authService,
        protected ZohoBooksClient $booksClient,
        protected OrganizationStore $organizationStore,
        protected ZohoTokenStore $tokenStore,
    ) {}

    private const STATE_SESSION_KEY = 'zoho_oauth_state';

    public function redirect(Request $request): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        $request->session()->put(self::STATE_SESSION_KEY, $state);

        return redirect()->away($this->authService->getAuthorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::STATE_SESSION_KEY);
        $receivedState = (string) $request->query('state', '');

        if (! $expectedState || ! hash_equals($expectedState, $receivedState)) {
            Log::warning('OAuth state mismatch', [
                'expected' => $expectedState ? 'present' : 'missing',
                'received' => $receivedState ? 'present' : 'missing',
            ]);

            return redirect()->route('report.index')
                ->with('error', 'OAuth state mismatch. Please try connecting again.');
        }

        $error = $request->query('error');
        if ($error) {
            return redirect()->route('report.index')
                ->with('error', "Zoho authorization failed: {$error}");
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('report.index')
                ->with('error', 'Zoho did not return an authorization code.');
        }

        try {
            $this->authService->exchangeCodeForTokens($code);
        } catch (ZohoApiException $e) {
            Log::error('OAuth callback failed', ['error' => $e->getMessage(), 'data' => $e->getErrorData()]);

            return redirect()->route('report.index')
                ->with('error', 'Failed to obtain Zoho tokens: '.$e->getMessage());
        }

        return redirect()->route('zoho.organizations.show')
            ->with('status', 'Connected to Zoho. Please select an organization.');
    }

    public function showOrganizations(): View|RedirectResponse
    {
        if (! $this->authService->hasValidToken()) {
            return redirect()->route('report.index')
                ->with('error', 'Please connect to Zoho first.');
        }

        try {
            $response = $this->booksClient->getOrganizations();
            $organizations = $response['organizations'] ?? [];
        } catch (ZohoApiException $e) {
            return redirect()->route('report.index')
                ->with('error', 'Failed to load organizations: '.$e->getMessage());
        }

        return view('zoho.organizations', ['organizations' => $organizations]);
    }

    public function chooseOrganization(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:50'],
            'organization_name' => ['nullable', 'string', 'max:255'],
        ]);

        $this->organizationStore->setOrganization(
            $validated['organization_id'],
            $validated['organization_name'] ?? null,
        );

        return redirect()->route('report.index')
            ->with('status', 'Organization selected. You can now load the report.');
    }

    public function logout(): RedirectResponse
    {
        $this->authService->revokeToken();

        return redirect()->route('report.index')
            ->with('status', 'Disconnected from Zoho.');
    }

    public function status(): JsonResponse
    {
        $token = $this->tokenStore->getLatestToken();

        return response()->json([
            'connected' => $token !== null,
            'organization_id' => $this->organizationStore->getOrganizationId(),
            'expires_at' => $token?->expires_at?->toIso8601String(),
        ]);
    }
}
