@extends('layouts.app')

@section('title', 'Employee Archives')

@section('content')
<div class="container employee-archives-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Employee Archives</h1>
                <div class="btn-group" role="group">
                    <a href="{{ route('admin.employees') }}" class="btn btn-outline-primary">
                        <i class="fas fa-users"></i> Active Employees
                    </a>
                    <a href="{{ route('admin.employees-archives') }}" class="btn btn-primary active">
                        <i class="fas fa-archive"></i> Archives
                    </a>
                </div>
            </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                @if(Auth::user()->admin_type === 'super_admin')
                    <div class="col-md-4">
                        <label class="form-label">Filter by Branch</label>
                        <select name="branch_id" class="form-control" onchange="this.form.submit()">
                            <option value="">All Branches</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ $selectedBranch == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->branch_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <div class="form-control bg-light text-dark">
                            {{ Auth::user()->profile->branch->branch_name ?? 'N/A' }}
                        </div>
                    </div>
                @endif
                <div class="col-md-2 d-flex align-items-end">
                    <a href="{{ route('admin.employees-archives') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4 employee-archive-summary">
        <div class="col-md-4">
            <div class="card employee-stat-card employee-stat-total">
                <div class="card-body">
                    <h6>Archived Employees</h6>
                    <h2 class="mb-0">{{ $totalEmployees ?? 0 }}</h2>
                    <small>Inactive staff</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card employee-stat-card employee-stat-finance">
                <div class="card-body">
                    <h6>Archived Finance Officers</h6>
                    <h2 class="mb-0">{{ $totalFinance ?? 0 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card employee-stat-card employee-stat-admin">
                <div class="card-body">
                    <h6>Archived Administrators</h6>
                    <h2 class="mb-0">{{ $totalAdmins ?? 0 }}</h2>
                </div>
            </div>
        </div>
    </div>

    <style>
        .employee-archives-page .employee-stat-card {
            border: 1px solid transparent;
            box-shadow: 0 2px 8px rgba(62, 39, 88, 0.1);
        }

        .employee-archives-page .employee-stat-card h6,
        .employee-archives-page .employee-stat-card h2 {
            color: inherit;
        }

        .employee-archives-page .employee-stat-card h6 {
            font-size: 0.84rem;
            font-weight: 600;
            opacity: 0.88;
        }

        .employee-archives-page .employee-stat-card h2 {
            font-size: 2rem;
            font-weight: 700;
        }

        .employee-archives-page .employee-stat-total {
            background: #493267;
            border-color: #493267;
            color: #fff;
        }

        .employee-archives-page .employee-stat-finance {
            background: #7048a5;
            border-color: #7048a5;
            color: #fff;
        }

        .employee-archives-page .employee-stat-admin {
            background: #eee6f7;
            border-color: #ded0ed;
            color: #493267;
        }

        .employee-archives-page .archived-employee-row > * {
            background: #f7f3fb;
        }

        body.dark-mode .employee-archives-page .employee-stat-total {
            background: #39294c;
            border-color: #61477c;
            color: #fff;
        }

        body.dark-mode .employee-archives-page .employee-stat-finance {
            background: #50366f;
            border-color: #76599a;
            color: #fff;
        }

        body.dark-mode .employee-archives-page .employee-stat-admin {
            background: #30243c;
            border-color: #5c476e;
            color: #f2eafb;
        }

        body.dark-mode .employee-archives-page .archived-employee-row > * {
            background: #30283b;
            color: #f2eafb;
            border-color: rgba(255,255,255,0.12);
        }
    </style>

    <div class="card">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Archived Employee List</h5>
                <span class="badge bg-danger">Inactive</span>
            </div>
            @if(Auth::user()->isSuperAdmin())
                <small class="text-muted">Showing all archived staff</small>
            @else
                <small class="text-muted">Showing archived employees from your branch</small>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Employee #</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Branch</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    <tr class="archived-employee-row">
                        <td>{{ $emp->employee_number }}</td>
                        <td>
                            <strong>{{ $emp->first_name }} {{ $emp->last_name }}</strong><br>
                            <small class="text-muted">{{ $emp->user->email ?? 'N/A' }}</small>
                        </td>
                        <td>{{ $emp->position ?? 'N/A' }}</td>
                        <td><span class="badge bg-dark">{{ $emp->branch->branch_name ?? 'N/A' }}</span></td>
                        <td>₱{{ number_format($emp->basic_salary ?? 0, 2) }}</td>
                        <td>
                            <span class="badge bg-danger">Inactive</span>
                        </td>
                        <td>
                            <form action="{{ route('admin.employee-restore', $emp->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Restore this employee?')">
                                    <i class="fas fa-undo"></i> Restore
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4">No archived employees found</td>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($employees instanceof \Illuminate\Contracts\Pagination\Paginator || $employees instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <div class="card-footer">
                {{ $employees->links() }}
            </div>
        @endif
    </div>
        </div>
    </div>
</div>
@endsection
