<?php

namespace App\Http\Controllers;

use App\Constants\UserConstants;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    // Hiện danh sách người dùng theo quyền, phân trang, tìm kiếm và lọc trạng thái
    public function index(Request $request): View
    {
        $viewer = $request->user(); // Tài khoản đăng nhập hiện tại
        $rawSearch = $request->query('q');
        $search = is_string($rawSearch) ? trim($rawSearch) : '';
        $rawStatus = $request->query('status');
        $statusFilter = is_string($rawStatus) && in_array($rawStatus, ['0', '1'], true) ? $rawStatus : '';
        $requestedPage = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        if ($requestedPage === false || $requestedPage < 1) {
            $requestedPage = 1; // Trang không hợp lệ sẽ trả về trang đầu
        }

        $query = User::query()->visibleTo($viewer)->matching($search);
        if ($statusFilter !== '') {
            $query->where('status', (int) $statusFilter);
        }

        $total = (clone $query)->count();
        $perPage = UserConstants::USERS_PER_PAGE;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($requestedPage, $lastPage);
        $users = $query->orderByDesc('created_at')->orderByDesc('id') // Sắp xếp theo ngày tạo giảm dần, id giảm dần
            ->paginate($perPage, ['id', 'full_name', 'created_at', 'status', 'user_type', 'department'], page: $page, total: $total)
            ->withPath(route('users.index'))
            ->appends(['q' => $search, 'status' => $statusFilter]); // Giữ các tham số tìm kiếm và lọc trạng thái trong URL phân trang

        $showUserListActions = in_array($viewer->user_type, [User::TYPE_MANAGER, User::TYPE_ADMIN], true);
        $userActions = [];
        foreach ($users as $user) { // Xác định quyền hiển thị nút thao tác trên từng người dùng trong danh sách
            $userActions[$user->id] = $viewer->user_type === User::TYPE_ADMIN
                || ($viewer->user_type === User::TYPE_MANAGER && $user->user_type === User::TYPE_USER
                    && $user->department === $viewer->department);
        }

        // Trả về view với dữ liệu hiển thị
        return view('users.index', [
            'users'               => $users,
            'search'              => $search,
            'statusFilter'        => $statusFilter,
            'activeStatusValue'   => $statusFilter === (string) User::STATUS_ACTIVE ? '' : (string) User::STATUS_ACTIVE,
            'lockedStatusValue'   => $statusFilter === (string) User::STATUS_LOCKED ? '' : (string) User::STATUS_LOCKED,
            'userStatuses'        => UserConstants::USER_STATUSES,
            'showUserListActions' => $showUserListActions,
            'userActions'         => $userActions,
        ]);
    }
}
