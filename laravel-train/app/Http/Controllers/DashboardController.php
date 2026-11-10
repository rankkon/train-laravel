<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    // Hiển thị dashboard theo phạm vi người dùng và phân trang
    public function index(Request $request): View
    {
        $query         = LeaveRequest::query()->visibleTo($request->user()); // Truy vấn đã lọc quyền xem của người dùng
        $total         = (clone $query)->count();
        $perPage       = AppConstants::LEAVES_PER_PAGE; // Số lượng bản ghi trên mỗi trang
        $lastPage      = max(1, (int) ceil($total / $perPage)); // Tính số trang cuối cùng, tối thiểu là 1
        $requestedPage = filter_var($request->query('page', 1), FILTER_VALIDATE_INT); // trang người dùng yêu cầu
        if ($requestedPage === false || $requestedPage < 1) {
            $requestedPage = 1;
        }
        $page   = min($requestedPage, $lastPage); // trang thực tế sẽ hiển thị, không vượt quá trang cuối cùng
        $leaves = $query->orderBy('leave.status') // Sắp xếp theo đơn mới, đã duyệt, đã hủy
            ->orderByDesc('leave.created_at') // Sắp xếp theo ngày tạo giảm dần
            ->orderByDesc('leave.id') // Sắp xếp theo ID giảm dần
            ->paginate($perPage, [
                'leave.id', 'leave.leave_type', 'leave.created_at', 'leave.status',
                'leave.end_date', 'leave.title', 'users.full_name',
            ], page: $page, total: $total)
            ->withPath(route('dashboard'))
            ->appends(['q' => '']);

        return view('dashboard', [ // Truyền danh sách đơn và danh mục hiển thị sang giao diện
            'leaves'        => $leaves,
            'leaveTypes'    => AppConstants::LEAVE_TYPES,
            'leaveStatuses' => AppConstants::LEAVE_STATUSES,
        ]);
    }
}
