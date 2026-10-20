@forelse($cutoffPreviews as $cutoffPreview)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Payroll Preview: {{ $cutoffPreview->period->branch?->branch_name }} | {{ $cutoffPreview->period->start_date->format('M j') }}–{{ $cutoffPreview->period->end_date->format('M j, Y') }}</h5>
            <span class="badge bg-secondary">Not posted</span>
        </div>
        <div class="card-body">
            <p class="text-muted">{{ $cutoffPreview->applications->count() }} eligible approved cash advance application{{ $cutoffPreview->applications->count() === 1 ? '' : 's' }}. Nothing is posted to payroll.</p>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Approved Loan Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cutoffPreview->applications as $application)
                            <tr>
                                <td>{{ $application->employeeProfile?->first_name }} {{ $application->employeeProfile?->last_name }}</td>
                                <td>₱{{ number_format((float) ($application->approved_amount ?: $application->requested_amount), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted py-3">No eligible approved cash advances for this cutoff.</td></tr>
                        @endforelse
                    </tbody>
                    @if($cutoffPreview->applications->isNotEmpty())
                        <tfoot>
                            <tr class="fw-semibold">
                                <td>Approved Loan Total</td>
                                <td>₱{{ number_format($cutoffPreview->approvedTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@empty
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Payroll Preview</h5>
            <span class="badge bg-secondary">Not posted</span>
        </div>
        <div class="card-body text-muted">No draft payroll periods are available for your branch scope.</div>
    </div>
@endforelse

<div class="text-end mb-4">
    <a href="{{ route('cash-advances.history', request()->query('scope') === 'my' ? ['scope' => 'my'] : []) }}" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-history me-1"></i> View Full History
    </a>
</div>