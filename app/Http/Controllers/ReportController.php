<?php

namespace App\Http\Controllers;

use App\Contracts\OrganizationStore;
use App\Contracts\ZohoTokenStore;
use App\Exceptions\ZohoApiException;
use App\Http\Requests\LoadReportRequest;
use App\Services\ReportService;
use App\Support\ReportPresenter;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Renders the P&L comparison page. Cache invalidation and JSON endpoints
 * live in their own controllers (BudgetController, TransactionsController,
 * AttachmentController).
 */
final class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        protected ZohoTokenStore $tokenStore,
        protected OrganizationStore $organizationStore,
    ) {}

    public function index(LoadReportRequest $request): View
    {
        $monthA = $request->monthA();
        $monthB = $request->monthB();

        $connected = $this->tokenStore->getLatestToken() !== null;
        $hasOrganization = $connected && $this->organizationStore->getOrganizationId() !== null;

        $report = null;
        $error = null;

        if ($hasOrganization) {
            try {
                $report = $this->reportService->getComparison($monthA, $monthB);
            } catch (ZohoApiException $e) {
                $error = $e->getMessage();
                Log::warning('Report build failed', ['error' => $error, 'data' => $e->getErrorData()]);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                Log::error('Report build crashed', ['error' => $error]);
            }
        }

        $presenter = new ReportPresenter(
            report: $report,
            monthA: $monthA,
            monthB: $monthB,
            monthALabel: Carbon::createFromFormat('Y-m', $monthA)->format(ReportPresenter::MONTH_LABEL_FORMAT),
            monthBLabel: Carbon::createFromFormat('Y-m', $monthB)->format(ReportPresenter::MONTH_LABEL_FORMAT),
            connected: $connected,
            hasOrganization: $hasOrganization,
            error: $error,
        );

        return view('report.index', ['presenter' => $presenter]);
    }

    public function refresh(Request $request): RedirectResponse
    {
        $this->reportService->clearCache();

        return redirect()->route('report.index', $request->only(['month_a', 'month_b']))
            ->with('status', 'Report cache cleared.');
    }
}
