@extends('layouts.app')

@section('title', 'Select Organization')

@push('styles')
    @vite('resources/css/report.css')
@endpush

@section('topbar-actions')
    <a class="btn secondary" href="{{ route('report.index') }}">Back</a>
@endsection

@section('content')
    <div class="card">
        <h2 class="section-title">Select your Zoho organization</h2>
        <p class="muted">Choose the organization that holds your TATA test company. This selection is saved and used for all subsequent report queries.</p>

        @if (empty($organizations))
            <p>No organizations returned by Zoho. Make sure the connected account has access to at least one organization.</p>
        @else
            <form method="POST" action="{{ route('zoho.organizations.choose') }}">
                @csrf
                <table class="org-table">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Organization</th>
                            <th>Organization ID</th>
                            <th>Currency</th>
                            <th>Time Zone</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($organizations as $i => $org)
                            <tr>
                                <td>
                                    <input type="radio" name="organization_id" value="{{ $org['organization_id'] ?? '' }}" data-name="{{ $org['name'] ?? '' }}" {{ $i === 0 ? 'checked' : '' }} required>
                                </td>
                                <td>{{ $org['name'] ?? '—' }}</td>
                                <td class="mono">{{ $org['organization_id'] ?? '' }}</td>
                                <td>{{ $org['currency_code'] ?? '' }}</td>
                                <td>{{ $org['time_zone'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <input type="hidden" name="organization_name" id="orgname-hidden" value="">
                <div class="org-actions">
                    <button class="btn" type="submit">Save selection</button>
                </div>
            </form>
            <script>
                (() => {
                    const hidden = document.getElementById('orgname-hidden');
                    const radios = document.querySelectorAll('input[name="organization_id"]');
                    function sync() {
                        const checked = document.querySelector('input[name="organization_id"]:checked');
                        if (checked) hidden.value = checked.dataset.name || '';
                    }
                    radios.forEach(r => r.addEventListener('change', sync));
                    sync();
                })();
            </script>
        @endif
    </div>
@endsection
