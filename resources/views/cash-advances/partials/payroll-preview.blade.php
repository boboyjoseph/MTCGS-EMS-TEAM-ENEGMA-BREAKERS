<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Payroll Preview: Sep 1–15, 2026</h5>
        <span class="badge bg-secondary">Not posted</span>
    </div>
    <div class="card-body">
        <p class="text-muted">{{ $cutoffCashAdvances->count() }} eligible approved cash advance application{{ $cutoffCashAdvances->count() === 1 ? '' : 's' }}. Nothing is posted to payroll.</p>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Approved Loan Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cutoffCashAdvances as $application)
                        <tr>
                            <td>{{ $application->employeeProfile?->first_name }} {{ $application->employeeProfile?->last_name }}</td>
                            <td>₱{{ number_format((float) ($application->approved_amount ?: $application->requested_amount), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">No eligible approved cash advances for this cutoff.</td></tr>
                    @endforelse
                </tbody>
                @if($cutoffCashAdvances->isNotEmpty())
                    <tfoot>
                        <tr class="fw-semibold">
                            <td>Approved Loan Total</td>
                            <td>₱{{ number_format($cutoffApprovedTotal, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>