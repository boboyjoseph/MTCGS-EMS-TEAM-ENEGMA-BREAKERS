<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchAssignmentController extends Controller
{
    public function index()
    {
        $this->authorizeBranchAssignmentManagement();

        $branchHeads = User::query()
            ->with(['branch', 'assignedBranches'])
            ->where(function ($query) {
                $query->where('role', 'branch_head')
                    ->orWhere(function ($adminQuery) {
                        $adminQuery->where('role', 'admin')
                            ->where('admin_type', 'branch_admin');
                    });
            })
            ->orderBy('name')
            ->paginate(20);

        $branches = Branch::orderBy('branch_name')->get();
        $branchNames = $branches->pluck('branch_name', 'id');

        return view('admin.branch-heads.assignments', compact('branchHeads', 'branches', 'branchNames'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeBranchAssignmentManagement();
        abort_unless($this->isBranchHeadAccount($user), 404);

        $validated = $request->validate([
            'branch_ids' => 'sometimes|array',
            'branch_ids.*' => 'integer|distinct|exists:branches,id',
        ]);

        $branchIds = collect($validated['branch_ids'] ?? [])
            ->map(fn ($branchId) => (int) $branchId)
            ->unique()
            ->values();

        if ($user->getPrimaryBranchId() !== null && $branchIds->contains($user->getPrimaryBranchId())) {
            return back()->withErrors([
                'branch_ids' => 'The primary branch is already assigned and cannot be added as an assisting branch.',
            ]);
        }

        $assignments = $branchIds->mapWithKeys(fn (int $branchId) => [
            $branchId => ['assigned_by' => Auth::id()],
        ])->all();

        $user->assignedBranches()->sync($assignments);

        return back()->with('success', "Branch assistance assignments updated for {$user->name}.");
    }

    public function updateActiveBranch(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isBranchAdmin(), 403);

        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        $branchId = (int) $validated['branch_id'];
        abort_unless($user->canAccessBranch($branchId), 403, 'You are not assigned to this branch.');

        $request->session()->put('active_branch_id', $branchId);

        return back()->with('success', 'Active branch changed.');
    }

    private function authorizeBranchAssignmentManagement(): void
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'admin' || $user->admin_type !== 'super_admin') {
            abort(403, 'Only super administrators can manage branch assignments.');
        }
    }

    private function isBranchHeadAccount(User $user): bool
    {
        return $user->role === 'branch_head'
            || ($user->role === 'admin' && $user->admin_type === 'branch_admin');
    }
}
