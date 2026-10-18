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

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Payroll Period</th>
                        <th>Employee</th>
                        <th>Payroll Status</th>
                        <th class="text-end">Cash Advance Deducted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $entry->payrollPeriod?->period_code ?? $entry->payrollPeriod?->name ?? 'Payroll' }}</div>
                                <small class="text-muted">
                                    {{ $entry->payrollPeriod?->start_date?->format('M d, Y') }} – {{ $entry->payrollPeriod?->end_date?->format('M d, Y') }}
                                </small>
                            </td>
                            <td>
                                {{ $entry->employeeProfile?->first_name }} {{ $entry->employeeProfile?->last_name }}
                                <small class="d-block text-muted">{{ $entry->employeeProfile?->employee_number }}</small>
                            </td>
                            <td><span class="badge bg-success">{{ ucfirst($entry->payrollPeriod?->status ?? $entry->status) }}</span></td>
                            <td class="text-end">₱{{ number_format((float) $entry->cash_advance_deduction, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No previous payroll cash advance deductions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
            <div class="card-footer">{{ $entries->links() }}</div>
        @endif
    </div>
</div>
@endsection
