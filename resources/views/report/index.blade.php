@extends('layouts.app')

@php
    /** @var \App\Support\ReportPresenter $presenter */
    $error = $presenter->error;
    $fmt = \App\Support\ReportPresenter::format(...);
    $var = \App\Support\ReportPresenter::variance(...);
    $vcls = \App\Support\ReportPresenter::varianceClass(...);
@endphp

@section('title', 'Profit & Loss Comparison')

@section('topbar-actions')
    @if (! $presenter->connected)
        <a class="btn" href="{{ route('zoho.redirect') }}">Connect to Zoho</a>
    @else
        @if (! $presenter->hasOrganization)
            <a class="btn" href="{{ route('zoho.organizations.show') }}">Choose organization</a>
        @endif
        <form method="POST" action="{{ route('report.refresh') }}" class="form-inline">
            @csrf
            <input type="hidden" name="month_a" value="{{ $presenter->monthA }}">
            <input type="hidden" name="month_b" value="{{ $presenter->monthB }}">
            <button class="btn secondary" type="submit">Refresh from Zoho</button>
        </form>
        <form method="POST" action="{{ route('zoho.logout') }}" class="form-inline" onsubmit="return confirm('Disconnect from Zoho?')">
            @csrf
            <button class="btn secondary" type="submit">Disconnect</button>
        </form>
    @endif
@endsection

@push('styles')
    @vite('resources/css/report.css')
@endpush

@section('content')
    <div class="card filters">
        <form method="GET" action="{{ route('report.index') }}" class="filters form-inline">
            <label>
                Column A month
                <input type="month" name="month_a" value="{{ $presenter->monthA }}">
            </label>
            <label>
                Column D month
                <input type="month" name="month_b" value="{{ $presenter->monthB }}">
            </label>
            <button class="btn" type="submit">Load</button>
        </form>
        <div class="muted filters-info">
            @if (! $presenter->connected)
                Not connected. Click <strong>Connect to Zoho</strong> to begin.
            @elseif (! $presenter->hasOrganization)
                Connected. Choose an organization to load the report.
            @else
                Showing live data from Zoho Books.
            @endif
        </div>
    </div>

    @if ($presenter->hasReport())
        @php
            $columnA = $presenter->columnA();
            $columnB = $presenter->columnB();
        @endphp
        <div class="card">
            <table class="pnl-table">
                <thead>
                    <tr>
                        <th>{{ $columnA['label'] }}</th>
                        <th>Budget<br><span class="budget-label-cell">(Put Manually)</span></th>
                        <th>Variance</th>
                        <th class="col-pnl">Profit &amp; Loss</th>
                        <th>{{ $columnB['label'] }}</th>
                        <th>Budget</th>
                        <th>Variance</th>
                    </tr>
                    <tr>
                        <th class="col-key">A</th>
                        <th class="col-key">B</th>
                        <th class="col-key">C = A - B</th>
                        <th class="col-key"></th>
                        <th class="col-key">D</th>
                        <th class="col-key">E</th>
                        <th class="col-key">F = D - E</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($presenter->sections() as $section)
                        @php
                            $sectionBudgetA = $presenter->sectionBudget($section['name'], 'a');
                            $sectionBudgetB = $presenter->sectionBudget($section['name'], 'b');
                            $sectionVarA = $var($section['total_a'], $sectionBudgetA);
                            $sectionVarB = $var($section['total_b'], $sectionBudgetB);
                        @endphp

                        <tr class="section-header">
                            <td></td><td></td><td></td>
                            <td class="label center">{{ $section['name'] }}</td>
                            <td></td><td></td><td></td>
                        </tr>

                        @foreach ($section['accounts'] as $account)
                            @php
                                $acctBudgetA = $presenter->accountBudget($account['name'], 'a');
                                $acctBudgetB = $presenter->accountBudget($account['name'], 'b');
                                $acctVarA = $var($account['total_a'], $acctBudgetA);
                                $acctVarB = $var($account['total_b'], $acctBudgetB);
                            @endphp
                            <tr>
                                <td class="num">
                                    @if ($account['account_id_a'])
                                        <button type="button" class="pnl-link"
                                            data-account-id="{{ $account['account_id_a'] }}"
                                            data-month="{{ $columnA['month'] }}"
                                            data-account-name="{{ $account['name'] }}"
                                            data-month-label="{{ $columnA['label'] }}">{{ $fmt($account['total_a']) }}</button>
                                    @else
                                        {{ $fmt($account['total_a']) }}
                                    @endif
                                </td>
                                <td class="num">{{ $fmt($acctBudgetA) }}</td>
                                <td class="num {{ $vcls($acctVarA) }}">{{ $fmt($acctVarA) }}</td>
                                <td class="label">{{ $account['name'] }}</td>
                                <td class="num">
                                    @if ($account['account_id_b'])
                                        <button type="button" class="pnl-link"
                                            data-account-id="{{ $account['account_id_b'] }}"
                                            data-month="{{ $columnB['month'] }}"
                                            data-account-name="{{ $account['name'] }}"
                                            data-month-label="{{ $columnB['label'] }}">{{ $fmt($account['total_b']) }}</button>
                                    @else
                                        {{ $fmt($account['total_b']) }}
                                    @endif
                                </td>
                                <td class="num">{{ $fmt($acctBudgetB) }}</td>
                                <td class="num {{ $vcls($acctVarB) }}">{{ $fmt($acctVarB) }}</td>
                            </tr>
                        @endforeach

                        <tr class="section-total">
                            <td class="num">{{ $fmt($section['total_a']) }}</td>
                            <td class="num">{{ $fmt($sectionBudgetA) }}</td>
                            <td class="num {{ $vcls($sectionVarA) }}">{{ $fmt($sectionVarA) }}</td>
                            <td class="label">Total for {{ $section['name'] }}</td>
                            <td class="num">{{ $fmt($section['total_b']) }}</td>
                            <td class="num">{{ $fmt($sectionBudgetB) }}</td>
                            <td class="num {{ $vcls($sectionVarB) }}">{{ $fmt($sectionVarB) }}</td>
                        </tr>

                        <tr class="spacer"><td colspan="7"></td></tr>
                    @endforeach

                    @php
                        $netA = $presenter->netProfit('a');
                        $netB = $presenter->netProfit('b');
                        $netBudgetA = $presenter->netBudget('a');
                        $netBudgetB = $presenter->netBudget('b');
                        $netVarA = $var($netA, $netBudgetA);
                        $netVarB = $var($netB, $netBudgetB);
                    @endphp
                    <tr class="net-total">
                        <td class="num">{{ $fmt($netA) }}</td>
                        <td class="num">{{ $fmt($netBudgetA) }}</td>
                        <td class="num {{ $vcls($netVarA) }}">{{ $fmt($netVarA) }}</td>
                        <td class="label">Net Profit/Loss</td>
                        <td class="num">{{ $fmt($netB) }}</td>
                        <td class="num">{{ $fmt($netBudgetB) }}</td>
                        <td class="num {{ $vcls($netVarB) }}">{{ $fmt($netVarB) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3 class="section-title">Budgets (stored locally)</h3>
            <p class="muted">Edit the budget for each month. These values populate columns B and E above.</p>
            <table class="budgets-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="amount">Sales Budget</th>
                        <th class="amount">Cost of Goods Sold Budget</th>
                        <th class="action">Save</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['key' => $presenter->monthA, 'label' => $columnA['label'], 'budget' => $presenter->budgetForColumn('a')],
                        ['key' => $presenter->monthB, 'label' => $columnB['label'], 'budget' => $presenter->budgetForColumn('b')],
                    ] as $row)
                        <tr>
                            <form method="POST" action="{{ route('report.budgets.update') }}">
                                @csrf
                                <input type="hidden" name="month" value="{{ $row['key'] }}">
                                <input type="hidden" name="month_a" value="{{ $presenter->monthA }}">
                                <input type="hidden" name="month_b" value="{{ $presenter->monthB }}">
                                <td class="row-cell">{{ $row['label'] }}</td>
                                <td class="row-cell amount">
                                    <input type="number" step="0.01" min="0" name="sales_budget" value="{{ (float) ($row['budget']['sales'] ?? 0) }}">
                                </td>
                                <td class="row-cell amount">
                                    <input type="number" step="0.01" min="0" name="cogs_budget" value="{{ (float) ($row['budget']['cogs'] ?? 0) }}">
                                </td>
                                <td class="row-cell action">
                                    <button type="submit" class="btn small">Save</button>
                                </td>
                            </form>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($presenter->connected && ! $presenter->hasOrganization)
        <div class="card">
            <p>Almost there. <a href="{{ route('zoho.organizations.show') }}">Select an organization</a> to load the report.</p>
        </div>
    @elseif (! $presenter->connected)
        <div class="card">
            <h2 class="section-title">Connect to Zoho Books</h2>
            <p>To view live Profit &amp; Loss data, authorize this application to access your Zoho Books organization.</p>
            <a class="btn" href="{{ route('zoho.redirect') }}">Connect to Zoho</a>
        </div>
    @endif

    <!-- Transactions modal -->
    <div class="modal-overlay" id="txn-modal" role="dialog" aria-modal="true"
        data-transactions-url="{{ route('report.transactions') }}"
        data-attachment-base="{{ url('/report/attachments') }}">
        <div class="modal">
            <div class="modal-header">
                <h2 id="txn-modal-title">Account Transactions</h2>
                <button class="modal-close" type="button" data-modal-close aria-label="Close">×</button>
            </div>
            <div class="modal-body" id="txn-modal-body">
                <div class="modal-loading">Loading...</div>
            </div>
            <div class="modal-footer">
                <button class="btn secondary" type="button" data-modal-close>Close</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/report.js')
@endpush
