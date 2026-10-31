<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Hiển thị đơn theo phòng ban */
    public function index(Request $request): View
    {
        $query = LeaveRequest::query()->visibleTo($request->user());
        $total = (clone $query)->count();
        $perPage = config('leaveConfig.leaves_per_page');
        $lastPage = max(1, (int) ceil($total / $perPage));
        $requestedPage = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        $page = min($lastPage, max(1, $requestedPage === false ? 1 : $requestedPage));

        $leaves = $query->orderByDesc('leave.created_at')->orderByDesc('leave.id')
            ->paginate($perPage, [
                'leave.id', 'leave.leave_type', 'leave.created_at', 'leave.status',
                'leave.end_date', 'leave.title', 'users.full_name',
            ], page: $page, total: $total)
            ->withPath(route('dashboard'))
            ->appends(['q' => '']);

        return view('dashboard', [
            'leaves' => $leaves,
            'leaveTypes' => config('leaveConfig.leave_types'),
            'leaveStatuses' => config('leaveConfig.leave_statuses'),
        ]);
    }
}
