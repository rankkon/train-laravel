<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware([ActiveUser::class, 'auth']);
    }

    // Hiển thị dashboard theo phạm vi người dùng và phân trang
    public function index(Request $request): View
    {
        $query = LeaveRequest::join('users', 'users.id', '=', 'leave.user_id'); // Truy vấn đã lọc quyền xem của người dùng
        if ($request->user()->user_type !== AppConstants::TYPE_ADMIN) {
            $query->where('users.department', $request->user()->department);
        }
        $query->orderBy('leave.status') // Sắp xếp theo đơn mới, đã duyệt, đã hủy
            ->orderByDesc('leave.created_at') // Sắp xếp theo ngày tạo giảm dần
            ->orderByDesc('leave.id'); // Sắp xếp theo ID giảm dần
        $columns = [
            'leave.id', 'leave.leave_type', 'leave.created_at', 'leave.status',
            'leave.end_date', 'leave.title', 'users.full_name',
        ];
        $leaves = $query->paginate(15, $columns);
        if ($leaves->isEmpty() && $leaves->currentPage() > 1) {
            $leaves = $query->paginate(15, $columns, page: $leaves->lastPage());
        }

        return view('dashboard.index', [ // Truyền danh sách đơn và danh mục hiển thị sang giao diện
            'leaves'        => $leaves,
            'leaveTypes'    => AppConstants::LEAVE_TYPES,
            'leaveStatuses' => AppConstants::LEAVE_STATUSES,
        ]);
    }
}
