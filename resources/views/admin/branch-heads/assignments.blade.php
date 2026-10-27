@extends('layouts.app')

@section('title', 'Multi-Branch Assignments')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Multi-Branch Assignments</h3>
            <p class="text-muted mb-0">Super Admin can assign Branch Heads to assist additional branches.</p>
        </div>
        <a href="{{ route('admin.branch-heads.index') }}" class="btn btn-secondary">Back to Branch Admins</a>
    </div>

    @if($errors->has('branch_ids'))
        <div class="alert alert-danger">{{ $errors->first('branch_ids') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Branch Head</th>
                        <th>Primary Branch</th>
                        <th>Assisting Branches</th>
                        <th>Save Assignments</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($branchHeads as $branchHead)
                        @php
                            $primaryBranchId = $branchHead->getPrimaryBranchId();
                            $assignedBranchIds = $branchHead->assignedBranches->pluck('id')->map(fn ($id) => (int) $id)->all();
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $branchHead->name }}</strong><br>
                                <small class="text-muted">{{ $branchHead->role === 'branch_head' ? 'Branch Head' : 'Branch Admin' }}</small>
                            </td>
                            <td>{{ $branchNames[$primaryBranchId] ?? 'Not assigned' }}</td>
                            <td>
                                <form id="branch-assignment-{{ $branchHead->id }}" method="POST" action="{{ route('admin.branch-heads.assignments.update', $branchHead) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach($branches as $branch)
                                            @if($branch->id !== $primaryBranchId)
                                                <label class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" {{ in_array($branch->id, $assignedBranchIds, true) ? 'checked' : '' }}>
                                                    <span class="form-check-label">{{ $branch->branch_name }}</span>
                                                </label>
                                            @endif
                                        @endforeach
                                    </div>
                                </form>
                            </td>
                            <td>
                                <button type="submit" form="branch-assignment-{{ $branchHead->id }}" class="btn btn-sm btn-primary">
                                    Save
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4">No Branch Head accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $branchHeads->links('pagination::bootstrap-4') }}
        </div>
    </div>
</div>
@endsection
