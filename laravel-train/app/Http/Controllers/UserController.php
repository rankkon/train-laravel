<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    // Hiện danh sách người dùng theo quyền, phân trang, tìm kiếm và lọc trạng thái
    public function index(Request $request): View
    {
        if ($request->isMethod('get')) {
            $request->session()->forget('user_bulk_lock_ids');
        }
        $viewer = $request->user(); // Tài khoản đăng nhập hiện tại

        return $this->renderList($viewer, $this->listFilters($request));
    }

    // Kiểm tra quyền tạo và chuyển sang luồng thêm user
    public function create(Request $request, UserService $userService): View|RedirectResponse
    {
        Gate::authorize('create', User::class); // Kiểm tra quyền mở và xử lý form thêm người dùng

        return $this->form($request, $userService);
    }

    // Kiểm tra tài khoản và quyền quản lý trước khi mở form sửa người dùng
    public function edit(Request $request, UserService $userService, string $user): View|RedirectResponse
    {
        $editedUserId = filter_var($user, FILTER_VALIDATE_INT);
        abort_if($editedUserId === false || $editedUserId < 1, 400);

        $query = User::query();
        if ($request->isMethod('post') && is_array($request->post('original_values'))) {
            $query->select(['id', 'username', 'user_type', 'department', 'status']); // Đọc lại các cột cần kiểm tra quyền và trạng thái
        }
        $editedUser = $query->findOrFail($editedUserId); // Lấy tài khoản sửa và kiểm tra quyền
        Gate::authorize('manage', $editedUser);

        return $this->form($request, $userService, $editedUser);
    }

    // Xử lý các bước nhập, xác nhận, rời form và lưu user
    private function form(Request $request, UserService $userService, ?User $editedUser = null): View|RedirectResponse
    {
        $editedUserId   = $editedUser?->id ?? 0; // ID tài khoản đang sửa (= 0 khi tạo mới)
        $isAdminAccount = $editedUser !== null && $editedUser->user_type === AppConstants::TYPE_ADMIN; // Tài khoản đang sửa là Admin hay không
        $viewer         = $request->user();
        // Lấy danh mục theo quyền hiện tại để hiển thị và kiểm tra dữ liệu form
        $options            = $userService->formOptions($viewer, $editedUser);
        $newUserTypes       = $options['user_types'];
        $newUserDepartments = $options['departments'];
        // Giá trị mặc định cho form thêm mới người dùng
        $clearValues = [
            'username'   => $editedUser?->username ?? '',
            'full_name'  => '',
            'password'   => '',
            'email'      => '',
            'birth_date' => '',
            'user_type'  => (string) ($isAdminAccount ? AppConstants::TYPE_ADMIN : AppConstants::TYPE_USER),
            'department' => (string) array_key_first($newUserDepartments),
        ];
        $values        = $clearValues;
        $errors        = [];
        $showSavePopup = false; // Popup xác nhận lưu
        if ($editedUser !== null) {
            foreach (array_keys($values) as $field) { // Nạp các trường dữ liệu trừ mật khẩu
                if ($field !== 'password') {
                    $values[$field] = (string) $editedUser->getRawOriginal($field);
                }
            }
        }

        $originalValues             = $this->originalFormValues($request, $values); // Dữ liệu lúc mở form (Dùng để khôi phục, so sánh thay đổi)
        $originalValues['password'] = '';
        $values                     = $originalValues; // Dữ liệu hiện tại được điền trên giao diện
        $showExitPopup              = false; // Popup xác nhận rời trang
        $showClearPopup             = false; // Popup xác nhận xóa trống
        $exitDestination            = ''; // Trang sẽ chuyển đến sau khi xác nhận
        $exitMessage                = __('messages.confirm_exit');
        $exitActions                = [ // Các thao tác trên sidebar
            'exit_dashboard' => 'dashboard',
            'exit_users'     => 'users.index',
            'exit_list'      => 'leaves.index',
            'exit_logout'    => 'logout',
        ];

        if ($request->isMethod('post')) { // Đọc thao tác gửi từ form
            $action         = $request->post('action', 'validate');
            $allowedActions = array_merge(['validate', 'save', 'clear', 'confirm_clear', 'confirm_exit'], array_keys($exitActions));
            // Các thao tác của form: 
            //Validate: kiểm tra dữ liệu trước khi mở popup xác nhận
            // save: xác nhận lưu, kiểm tra lại dư liệu và ghi database
            // clear: kiểm tra thay đổi trước khi mở popup xác nhận xóa trống
            // Confirm_clear: Xác nhận bỏ dữ liệu đang nhập và khôi phục giá trị ban đầu
            // exist_*: Kiểm tra thay đổi trước khi chuyển trang hoặc đăng xuất
            // Confirm_exit: Xác nhận bỏ thay đổi và chuyển đến trang được phép
            abort_unless(is_string($action) && in_array($action, $allowedActions, true), 400);
            if ($action === 'confirm_exit') {
                $exitDestination = $request->post('exit_destination');
                abort_unless(is_string($exitDestination) && in_array($exitDestination, ['dashboard', 'users.index', 'leaves.index'], true), 400);

                return redirect()->route($exitDestination, status: 303);
            } elseif ($action === 'confirm_clear') {
                $values = $editedUser === null ? $clearValues : $originalValues; // From thêm mới trờ về mặc định
                if ($editedUser === null) {
                    $originalValues = $values;
                }
            } else {
                $form   = $userService->readValues($request->post(), $editedUser);
                $values = $form['values'];
                $errors = $form['errors'];
                if ($action === 'clear') {
                    $showClearPopup = $errors !== [] || $this->formHasChanges($values, $originalValues);
                } elseif (isset($exitActions[$action])) {
                    $exitDestination = $exitActions[$action]; // Xác định trang đích
                    $hasChanges      = $errors !== [] || $this->formHasChanges($values, $originalValues); // Kiểm tra dữ liệu có khác lúc mở form không
                    if ($hasChanges || $exitDestination === 'logout') { // popup hỏi xác nhận trước khi rời trang
                        $showExitPopup = true; // Đăng xuất luôn cần xác nhận
                        if ($exitDestination === 'logout') {
                            $exitMessage = $hasChanges ? __('messages.confirm_logout_with_changes') : __('messages.confirm_logout');
                        }
                    } else {
                        return redirect()->route($exitDestination, status: 303); // Chuyển trang ngay nếu form không đổi
                    }
                } else {
                    if ($action === 'save' && $errors === []) {
                        // Kiểm tra lại tài khoản, quyền và dữ liệu trong transaction
                        try {
                            $saving = $userService->save($viewer->id, $values, $editedUserId);
                        } catch (AuthenticationException) {
                            Auth::logout();

                            return redirect()->route('login', status: 303);
                        }
                        // Nếu lỗi, form vẫn giữ giá trị để người dùng chỉnh lại
                        $errors             = $saving['errors'];
                        $newUserTypes       = $saving['options']['user_types'];
                        $newUserDepartments = $saving['options']['departments'];
                        if ($errors === []) {
                            return redirect()->route('users.index', status: 303); // trở về danh sách người dùng nếu lưu thành công
                        }
                    } else {
                        $errors = $userService->validateFields($values, $newUserTypes, $newUserDepartments, $errors, editedUserId: $editedUserId); // Kiểm tra các trường và dữ liệu trùng để trả lỗi trên form
                    }
                    $showSavePopup = $action === 'validate' && $errors === []; // Chỉ mở popup lưu sau khi validate và không còn lỗi
                }
            }
        }

        // Dựng lại form bằng dữ liệu hiện tại
        return view('users.form', [
            'formTitle'          => $editedUser === null ? 'Thêm mới người dùng' : 'Sửa người dùng',
            'formAction'         => $editedUser === null ? route('users.create.submit') : route('users.edit.submit', ['user' => $editedUserId]),
            'originalValues'     => $originalValues,
            'values'             => $values,
            'errors'             => $errors,
            'newUserTypes'       => $newUserTypes,
            'newUserDepartments' => $newUserDepartments,
            'maximumBirthDate'   => now()->subDay()->toDateString(),
            'showSavePopup'      => $showSavePopup, 
            'showExitPopup'      => $showExitPopup,
            'showClearPopup'     => $showClearPopup,
            'exitDestination'    => $exitDestination,
            'exitMessage'        => $exitMessage,
            'editedUserId'       => $editedUserId,
            'isAdminAccount'     => $isAdminAccount,
        ]);
    }

    // Xử lý khóa hoặc mở khóa người dùng từ danh sách
    public function handleListAction(Request $request, UserService $userService): View|RedirectResponse
    {
        $action  = $request->post('action');
        $session = $request->session();
        if (! in_array($action, ['confirm_bulk_lock', 'close_bulk_popup'], true)) { // Xóa lựa chọn khóa nhiều khi bắt đầu thao tác khác, giữ lại khi đang xác nhận đóng popup khóa nhiều
            $session->forget('user_bulk_lock_ids');
        }
        abort_unless(is_string($action) && in_array($action, [ // Chỉ nhận các thao tác khóa, mở khóa và đóng popup
            'open_lock', 'open_unlock', 'confirm_lock', 'confirm_unlock', 'close_popup',
            'open_bulk_lock', 'confirm_bulk_lock', 'close_bulk_popup',
        ], true), 400);

        $filters            = $this->listFilters($request); // Giữ từ khóa, bộ lọc và số trang để sau thao tác vẫn trờ lại đúng danh sách
        $redirectParameters = ['q' => $filters['search'], 'status' => $filters['status'], 'page' => $filters['page']]; // Giữ các tham số tìm kiếm và lọc trạng thái trong URL phân trang
        // Đưa sort về mặc định sau khi thao tác
        if ($filters['sort'] !== '') {
            $redirectParameters['page'] = 1;
        }
        if ($action === 'close_popup') { // Đóng popup và tải lại danh sách, không thay đổi trạng thái tài khoản
            return redirect()->route('users.index', $redirectParameters, 303);
        }

        if ($action === 'open_bulk_lock') { // Đọc các checkbox được chọn để khóa nhiều
            $selectedUserIds = [];
            $rawIds          = $request->post('user_ids');
            if ($rawIds !== null) {
                abort_unless(is_array($rawIds), 400); // Danh sách được chọn phải là mảng
                foreach ($rawIds as $rawId) {
                    $targetUserId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
                    abort_if($targetUserId === false || $targetUserId < 1, 400);
                    $selectedUserIds[] = $targetUserId;
                }
            }
            if ($selectedUserIds !== []) {
                abort_unless(in_array($request->user()->user_type, [AppConstants::TYPE_MANAGER, AppConstants::TYPE_ADMIN], true), 403); // Chỉ cho phép quản lý hoặc admin thực hiện thao tác khóa hàng loạt
                $session->put('user_bulk_lock_ids', $selectedUserIds); // Giữ danh sách người dùng được chọn trong session để xác nhận khóa hàng loạt

                return $this->renderList($request->user(), $filters, statusPopup: 'bulk_lock', selectedUserIds: $selectedUserIds);
            }

            return $this->renderList($request->user(), $filters);
        }

        // Khi xác nhận popup, kiểm tra lại quyền tài khoản và khóa cả nhóm trong 1 transaction
        if ($action === 'confirm_bulk_lock' || $action === 'close_bulk_popup') {
            $selectedUserIds = $session->get('user_bulk_lock_ids', []);
            $session->forget('user_bulk_lock_ids');
            if ($action === 'confirm_bulk_lock' && $selectedUserIds !== []) {
                try {
                    $userService->changeStatus($request->user()->id, $selectedUserIds, true);
                } catch (AuthenticationException) {
                    Auth::logout();

                    return redirect()->route('login', status: 303);
                }

                return redirect()->route('users.index', $redirectParameters, 303);
            }

            $filters['sort']      = '';
            $filters['direction'] = 'desc';
            $filters['page']      = $redirectParameters['page'];

            return $this->renderList($request->user(), $filters, selectedUserIds: $selectedUserIds); // Hiển thị lại danh sách và giữ tick các người dùng đã chọn nếu hủy popup
        }

        $rawId        = $request->post('user_id');
        $targetUserId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        abort_if($targetUserId === false || $targetUserId < 1, 400);

        if ($action === 'open_lock' || $action === 'open_unlock') { // Kiểm tra tài khoản tồn tại và quyền quản lý trước khi mở popup
            $targetUser = User::query()->findOrFail($targetUserId);
            Gate::authorize('manage', $targetUser);
            $statusPopup = $action === 'open_lock' ? 'lock' : 'unlock';

            return $this->renderList($request->user(), $filters, $statusPopup, $targetUserId);
        }

        try {
            $userService->changeStatus($request->user()->id, [$targetUserId], $action === 'confirm_lock'); // Xác nhận thao tác confirm_lock, true để khóa, flase để mở khóa
        } catch (AuthenticationException) {
            Auth::logout();

            return redirect()->route('login', status: 303);
        }

        return redirect()->route('users.index', $redirectParameters, 303); // Tải lại danh sách với các bộ lọc trước đó
    }

    // Đọc và chuẩn hóa từ khóa, trạng thái lọc và trang được yêu cầu
    private function listFilters(Request $request): array
    {
        $rawSearch     = $request->query('q');
        $search        = is_string($rawSearch) ? trim($rawSearch) : '';
        $rawStatus     = $request->query('status');
        $statusFilter  = is_string($rawStatus) && in_array($rawStatus, ['0', '1'], true) ? $rawStatus : '';
        $requestedPage = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        if ($requestedPage === false || $requestedPage < 1) {
            $requestedPage = 1; // Trang không hợp lệ sẽ trả về trang đầu
        }

        $sort      = $request->query('sort', '');
        $direction = $request->query('direction', 'asc');
        if (! is_string($sort) || ! in_array($sort, ['id', 'full_name', 'created_at', 'status'], true)
            || ! is_string($direction) || ! in_array($direction, ['asc', 'desc'], true)) {
            $sort      = '';
            $direction = 'desc';
        }

        return [
            'search'    => $search,
            'status'    => $statusFilter,
            'page'      => $requestedPage,
            'sort'      => $sort,
            'direction' => $direction,
        ];
    }

    // Chuẩn bị danh sách, trạng thái checkbox và popup
    private function renderList(User $viewer, array $filters, string $statusPopup = '', int $statusUserId = 0, array $selectedUserIds = []): View
    {
        $search          = $filters['search'];
        $statusFilter    = $filters['status'];
        $requestedPage   = $filters['page'];
        $sortColumn      = $filters['sort'];
        $sortDirection   = $filters['direction'];
        $queryParameters = ['q' => $search, 'status' => $statusFilter];
        $query           = User::query()->visibleTo($viewer)->matching($search); // Áp dụng phạm vi được xem và từ khóa trước khi đếm, lọc và phân trang
        if ($statusFilter !== '') {
            $query->where('status', (int) $statusFilter);
        }
        if ($filters['sort'] !== '') {
            $queryParameters['sort']      = $sortColumn;
            $queryParameters['direction'] = $sortDirection;
        }

        $total    = (clone $query)->count();
        $perPage  = AppConstants::USERS_PER_PAGE;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min($requestedPage, $lastPage);
        if ($filters['sort'] === '') {
            $query->orderByDesc('created_at')->orderByDesc('id'); // Sắp xếp theo ngày tạo giảm dần, id giảm dần
        } else {
            $databaseDirection = $sortDirection;
            if ($sortColumn === 'status') {
                $databaseDirection = $sortDirection === 'asc' ? 'desc' : 'asc'; // Đảo chiều mã trạng thái
            }
            // Khi giá trị cột sort trùng, sẽ sort theo ID giảm dần
            $query->orderBy($sortColumn, $databaseDirection);
            if ($sortColumn !== 'id') {
                $query->orderByDesc('id');
            }
        }

        $users = $query
            ->paginate($perPage, ['id', 'full_name', 'created_at', 'status', 'user_type', 'department'], page: $page, total: $total)
            ->withPath(route('users.index'))
            ->appends($queryParameters); // Giữ các tham số tìm kiếm và lọc trạng thái trong URL phân trang

        $showUserListActions = in_array($viewer->user_type, [AppConstants::TYPE_MANAGER, AppConstants::TYPE_ADMIN], true); // Admin và manager được hiển thị nhóm nút thao tác quản lý
        $userActions         = [];
        foreach ($users as $user) { // Xác định quyền hiển thị nút thao tác trên từng người dùng trong danh sách
            $userActions[$user->id] = Gate::forUser($viewer)->allows('manage', $user);
        }

        $statusMessage = ''; // Chọn thông báo theo loại popup
        if ($statusPopup === 'lock') {
            $statusMessage = __('messages.confirm_user_lock');
        } elseif ($statusPopup === 'unlock') {
            $statusMessage = __('messages.confirm_user_unlock');
        } elseif ($statusPopup === 'bulk_lock') {
            $statusMessage = __('messages.confirm_users_lock');
        }

        // Trả về view với dữ liệu hiển thị
        return view('users.index', [
            'users'               => $users,
            'search'              => $search,
            'statusFilter'        => $statusFilter,
            'sortColumn'          => $sortColumn,
            'sortDirection'       => $sortDirection,
            'userStatuses'        => AppConstants::USER_STATUSES,
            'showUserListActions' => $showUserListActions,
            'userActions'         => $userActions,
            'actionUrl'           => route('users.actions', $queryParameters + ['page' => $requestedPage]),
            'statusPopup'         => $statusPopup,
            'statusUserId'        => $statusUserId,
            'statusMessage'       => $statusMessage,
            'statusCloseAction'   => $statusPopup === 'bulk_lock' ? 'close_bulk_popup' : 'close_popup',
            'selectedUserIds'     => $selectedUserIds,
        ]);
    }
}
