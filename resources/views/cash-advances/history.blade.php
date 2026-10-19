@extends('layouts.app')

@section('title', 'Cash Advance History')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Cash Advance History</h1>
            <p class="text-muted mb-0">Cash advance deductions from completed and approved payrolls.</p>
        </div>
        <a href="{{ route('cash-advances.index', request()->query('scope') === 'my' ? ['scope' => 'my'] : []) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Cash Advance
        </a>
    </div>

    @forelse($entries->getCollection()->groupBy('payroll_period_id') as $periodEntries)
        @php
            $historyPeriod = $periodEntries->first()->payrollPeriod;
        @endphp
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong>{{ $historyPeriod?->period_code ?? $historyPeriod?->name ?? 'Payroll' }}</strong>
                <span class="text-muted small">
                    {{ $historyPeriod?->start_date?->format('M d, Y') }} – {{ $historyPeriod?->end_date?->format('M d, Y') }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee Name</th>
                            <th>Employee ID</th>
                            <th>Position</th>
                            <th>Payroll Status</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($periodEntries as $entry)
                            <tr>
                                <td>{{ $historyPeriod?->payment_date?->format('M d, Y') ?? $historyPeriod?->end_date?->format('M d, Y') ?? '--' }}</td>
                                <td>{{ $entry->employeeProfile?->first_name }} {{ $entry->employeeProfile?->last_name }}</td>
                                <td>{{ $entry->employeeProfile?->employee_number ?? '--' }}</td>
                                <td>{{ $entry->employeeProfile?->position ?? '--' }}</td>
                                <td><span class="badge bg-success">{{ ucfirst($historyPeriod?->status ?? $entry->status) }}</span></td>
                                <td class="text-end">₱{{ number_format((float) $entry->cash_advance_deduction, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="5" class="text-end">Period Total</td>
                            <td class="text-end">₱{{ number_format($periodEntries->sum('cash_advance_deduction'), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center text-muted py-4">
                No cash advance deductions from fully approved payrolls found.
            </div>
        </div>
    @endforelse
    @if($entries->hasPages())
        <div class="card-footer">{{ $entries->links() }}</div>
    @endif
</div>
@endsection
