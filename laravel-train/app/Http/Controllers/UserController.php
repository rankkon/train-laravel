<?php

namespace App\Http\Controllers;

use App\Constants\UserConstants;
use App\Models\User;
use App\Services\UserDraftService;
use App\Services\UserService;
use App\Services\UserValidationService;
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

    // Xử lý nhập, xác nhận, rời form và lưu người dùng mới
    public function create(Request $request, UserValidationService $validation, UserDraftService $draftService, UserService $userService): View|RedirectResponse
    {
        Gate::authorize('create', User::class); // Kiểm tra quyền mở và xử lý form thêm người dùng

        $viewer = $request->user();
        $newUserTypes = [
            User::TYPE_USER    => UserConstants::USER_TYPES[User::TYPE_USER],
            User::TYPE_MANAGER => UserConstants::USER_TYPES[User::TYPE_MANAGER],
        ];
        $newUserDepartments = UserConstants::DEPARTMENTS;
        if ($viewer->user_type === User::TYPE_MANAGER) { // Manager chỉ được tạo người dùng thuộc phong ban của mình
            $newUserTypes = [User::TYPE_USER => UserConstants::USER_TYPES[User::TYPE_USER]];
            $newUserDepartments = [$viewer->department => UserConstants::DEPARTMENTS[$viewer->department]];
        }

        // Khởi tạo giá trị mặc định cho form thêm mới người dùng
        $values = [
            'username'   => '',
            'full_name'  => '',
            'password'   => '',
            'email'      => '',
            'birth_date' => '',
            'user_type'  => (string) User::TYPE_USER,
            'department' => (string) array_key_first($newUserDepartments),
        ];

        $draft = $draftService->prepare($request, $values); // Nạp dữ liệu và trạng thái form từ bản nháp
        $values = $draft['values'];
        $errors = $draft['errors'];
        $isConfirmation = $draft['confirmed'];
        $showSavePopup = false;
        $showExitPopup = false;
        $exitDestination = '';
        $exitMessage = __('messages.confirm_exit');
        $session = $request->session();

        if ($request->isMethod('post')) { // Đọc thao tác gửi từ form
            $action = $request->post('action');
            if ($action === null) {
                $action = 'validate';
            }
            abort_unless(is_string($action), 400);
            if ($action !== 'save' && $action !== 'confirm_save') {
                $session->forget('user_create_save_pending'); // Hủy cờ popup xác nhận khi chuyển thao tác
            }

            // Ánh xạ thao tác rời form sang route đích
            $exitActions = [
                'exit_dashboard' => 'dashboard',
                'exit_users'     => 'users.index',
                'exit_list'      => 'leaves.index',
                'exit_logout'    => 'logout',
            ];

            // Xử lý xác nhận hoặc hủy rời form
            if ($action === 'confirm_exit' || $action === 'cancel_exit') {
                $exitDestination = $session->get('user_create_exit', '');
                abort_if($exitDestination === '' || ($action === 'confirm_exit' && $exitDestination === 'logout'), 400);
                $session->forget('user_create_exit');
                if ($action === 'confirm_exit') {
                    $draftService->clear($session);

                    return redirect()->route($exitDestination, status: 303);
                }
            } else {
                $session->forget('user_create_exit'); // Hủy đích rời form cũ khi bắt đầu 1 thao tác khác

                if (in_array($action, ['confirm_save', 'cancel_save', 'save'], true)) { // Chỉ cho phép mở, hủy popup và lưu khi nháp đã xác nhận hợp lệ
                    abort_unless($isConfirmation, 400);
                    if ($action === 'confirm_save') {
                        $session->put('user_create_save_pending', true);
                        $showSavePopup = true;
                    } elseif ($action === 'save') {
                        abort_unless($session->get('user_create_save_pending') === true, 400);
                        $session->forget('user_create_save_pending');

                        try {
                            $saving = $userService->save($viewer->id, $values); // lưu dữ liệu từ nháp
                        } catch (AuthenticationException) {
                            Auth::logout();

                            return redirect()->route('login', status: 303);
                        }

                        $errors = $saving['errors'];
                        $newUserTypes = $saving['options']['user_types'];
                        $newUserDepartments = $saving['options']['departments'];
                        if ($errors === []) { // Nếu lưu thành công thì xóa nháp và chuyển về danh sách user
                            $draftService->clear($session);

                            return redirect()->route('users.index', status: 303);
                        }
                        $isConfirmation = false;
                        $session->put('user_create_draft.confirmed', false);
                        $session->put('user_create_draft.errors', $errors);
                    }
                } elseif ($action === 'clear') {
                    $draftService->clear($session);

                    return redirect()->route('users.create', status: 303);
                } elseif ($action === 'back') {
                    abort_unless($isConfirmation, 400);
                    $isConfirmation = false;
                    $session->put('user_create_draft.confirmed', false);
                } else {
                    abort_unless($action === 'validate' || isset($exitActions[$action]), 400);

                    if ($action === 'validate' || ! $isConfirmation) {
                        $errors = [];
                        foreach (array_keys($values) as $field) {
                            $value = $request->post($field);
                            $values[$field] = '';
                            if ($value !== null) {
                                if (is_string($value)) {
                                    $values[$field] = $value;
                                } else {
                                    $errors[$field] = __('messages.invalid_form_value');
                                }
                            }
                            if ($action !== 'validate' && $values[$field] === $draft['values'][$field]
                                && isset($draft['errors'][$field])) {
                                $errors[$field] = $draft['errors'][$field];
                            }
                        }
                    }

                    if ($action === 'validate') {
                        $errors = $validation->fields($values, $newUserTypes, $newUserDepartments, $errors);
                        $isConfirmation = $errors === [];
                    }

                    $session->put('user_create_draft', array_replace($draft, [
                        'values'    => $values,
                        'errors'    => $errors,
                        'confirmed' => $isConfirmation,
                    ]));

                    if (isset($exitActions[$action])) {
                        $exitDestination = $exitActions[$action];
                        $hasChanges = $values !== $draft['initial_values'] || $errors !== []; // So sánh với dữ liệu ban đầu để hỏi trước khi rời form
                        if ($hasChanges || $exitDestination === 'logout') {
                            $session->put('user_create_exit', $exitDestination);
                            $showExitPopup = true;
                            if ($exitDestination === 'logout') {
                                $exitMessage = $hasChanges ? __('messages.confirm_logout_with_changes') : __('messages.confirm_logout');
                            }
                        } else {
                            $draftService->clear($session);

                            return redirect()->route($exitDestination, status: 303);
                        }
                    }
                }
            }
        }

        return view('users.form', [
            'formTitle'          => 'Thêm mới người dùng',
            'values'             => $values,
            'errors'             => $errors,
            'newUserTypes'       => $newUserTypes,
            'newUserDepartments' => $newUserDepartments,
            'maximumBirthDate'   => now()->subDay()->toDateString(),
            'isConfirmation'     => $isConfirmation,
            'showSavePopup'      => $showSavePopup,
            'showExitPopup'      => $showExitPopup,
            'exitDestination'    => $exitDestination,
            'exitMessage'        => $exitMessage,
        ]);
    }

    // Xử lý khóa hoặc mở khóa người dùng từ danh sách
    public function handleListAction(Request $request, UserService $userService): View|RedirectResponse
    {
        $action = $request->post('action');
        $session = $request->session();
        if (! in_array($action, ['confirm_bulk_lock', 'close_bulk_popup'], true)) {
            $session->forget('user_bulk_lock_ids'); // Chỉ giữ danh sách người dùng được chọn khi đang xác nhận hoặc đóng popup khóa hàng loạt
        }
        abort_unless(is_string($action) && in_array($action, [
            'open_lock', 'open_unlock', 'confirm_lock', 'confirm_unlock', 'close_popup',
            'open_bulk_lock', 'confirm_bulk_lock', 'close_bulk_popup',
        ], true), 400);

        $filters = $this->listFilters($request);
        $redirectParameters = ['q' => $filters['search'], 'status' => $filters['status'], 'page' => $filters['page']]; // Giữ các tham số tìm kiếm và lọc trạng thái trong URL phân trang
        if ($action === 'close_popup') {
            return redirect()->route('users.index', $redirectParameters, 303);
        }

        if ($action === 'open_bulk_lock') {
            $selectedUserIds = [];
            $rawIds = $request->post('user_ids');
            if ($rawIds !== null) {
                abort_unless(is_array($rawIds), 400);
                foreach ($rawIds as $rawId) {
                    $targetUserId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
                    abort_if($targetUserId === false || $targetUserId < 1, 400);
                    $selectedUserIds[] = $targetUserId;
                }
            }
            if ($selectedUserIds !== []) {
                abort_unless(in_array($request->user()->user_type, [User::TYPE_MANAGER, User::TYPE_ADMIN], true), 403); // Chỉ cho phép quản lý hoặc admin thực hiện thao tác khóa hàng loạt
                $session->put('user_bulk_lock_ids', $selectedUserIds); // Giữ danh sách người dùng được chọn trong session để xác nhận khóa hàng loạt

                return $this->renderList($request->user(), $filters, statusPopup: 'bulk_lock', selectedUserIds: $selectedUserIds);
            }

            return $this->renderList($request->user(), $filters);
        }

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

            return $this->renderList($request->user(), $filters, selectedUserIds: $selectedUserIds); // Hiển thị lại danh sách và giữ tick các người dùng đã chọn nếu hủy popup
        }

        $rawId = $request->post('user_id');
        $targetUserId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        abort_if($targetUserId === false || $targetUserId < 1, 400);

        if ($action === 'open_lock' || $action === 'open_unlock') {
            $targetUser = User::query()->findOrFail($targetUserId);
            Gate::authorize('manage', $targetUser);
            $statusPopup = $action === 'open_lock' ? 'lock' : 'unlock';

            return $this->renderList($request->user(), $filters, $statusPopup, $targetUserId);
        }

        try {
            $userService->changeStatus($request->user()->id, [$targetUserId], $action === 'confirm_lock');
        } catch (AuthenticationException) {
            Auth::logout();

            return redirect()->route('login', status: 303);
        }

        return redirect()->route('users.index', $redirectParameters, 303);
    }

    // Đọc và chuẩn hóa từ khóa, trạng thái lọc và trang được yêu cầu
    private function listFilters(Request $request): array
    {
        $rawSearch = $request->query('q');
        $search = is_string($rawSearch) ? trim($rawSearch) : '';
        $rawStatus = $request->query('status');
        $statusFilter = is_string($rawStatus) && in_array($rawStatus, ['0', '1'], true) ? $rawStatus : '';
        $requestedPage = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        if ($requestedPage === false || $requestedPage < 1) {
            $requestedPage = 1; // Trang không hợp lệ sẽ trả về trang đầu
        }

        return ['search' => $search, 'status' => $statusFilter, 'page' => $requestedPage];
    }

    // Chuẩn bị danh sách, trạng thái checkbox và popup
    private function renderList(User $viewer, array $filters, string $statusPopup = '', int $statusUserId = 0, array $selectedUserIds = []): View
    {
        $search = $filters['search'];
        $statusFilter = $filters['status'];
        $requestedPage = $filters['page'];
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
            $userActions[$user->id] = Gate::forUser($viewer)->allows('manage', $user);
        }

        $statusMessage = '';
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
            'activeStatusValue'   => $statusFilter === (string) User::STATUS_ACTIVE ? '' : (string) User::STATUS_ACTIVE,
            'lockedStatusValue'   => $statusFilter === (string) User::STATUS_LOCKED ? '' : (string) User::STATUS_LOCKED,
            'userStatuses'        => UserConstants::USER_STATUSES,
            'showUserListActions' => $showUserListActions,
            'userActions'         => $userActions,
            'actionUrl'           => route('users.actions', ['q' => $search, 'status' => $statusFilter, 'page' => $requestedPage]),
            'statusPopup'         => $statusPopup,
            'statusUserId'        => $statusUserId,
            'statusMessage'       => $statusMessage,
            'statusCloseAction'   => $statusPopup === 'bulk_lock' ? 'close_bulk_popup' : 'close_popup',
            'selectedUserIds'     => $selectedUserIds,
        ]);
    }
}
