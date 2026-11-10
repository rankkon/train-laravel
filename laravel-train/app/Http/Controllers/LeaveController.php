<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeaveController extends Controller
{
    // Hiển thị và xử lý các bước nhập, xác nhận, hủy và lưu đơn
    public function create(Request $request, LeaveService $leaveService): Response|RedirectResponse
    {
        return $this->form($request, $leaveService);
    }

    // Kiểm tra ID, tải thông tin cần thiết và xác nhận quyền sửa đơn
    public function edit(Request $request, LeaveService $leaveService, string $leave): Response|RedirectResponse
    {
        $leaveId = filter_var($leave, FILTER_VALIDATE_INT);
        abort_if($leaveId === false || $leaveId < 1, 400);

        $query = LeaveRequest::query();
        if ($request->isMethod('post') && is_array($request->post('original_values'))) {
            $query->select(['id', 'user_id', 'status', 'attachment_original_name', 'attachment_path']); // Chỉ đọc lại thông tin phục vụ kiểm tra quyền, trạng thái và file
        }
        $editedLeave = $query->findOrFail($leaveId);
        Gate::authorize('update', $editedLeave);

        return $this->form($request, $leaveService, $editedLeave);
    }

    // Xử lý chung form thêm/sửa đơn
    private function form(Request $request, LeaveService $leaveService, ?LeaveRequest $leave = null): Response|RedirectResponse
    {
        $leaveId                    = $leave?->id ?? 0;
        $formTitle                  = $leave === null ? 'Thêm mới đơn' : 'Sửa đơn';
        $formAction                 = $leave === null ? route('leaves.create.submit') : route('leaves.edit.submit', ['leave' => $leaveId]);
        $minimumDate                = $leave === null ? now()->toDateString() : ''; // Sửa đơn có thể chọn ngày trong quá khứ
        $user                       = $request->user(); // Lấy thông tin người dùng hiện tại
        $approvers                  = $user->leaveApprovers(); // Lấy danh sách người duyệt đơn của người dùng
        $leaveTypes                 = AppConstants::LEAVE_TYPES; // Lấy danh sách loại đơn
        $clearValues                = $leaveService->emptyValues(); // Khởi tạo giá trị mặc định cho form
        $clearValues['approver_id'] = $approvers->isEmpty() ? '' : (string) $approvers->first()->id; // Mặc định chọn người duyệt đầu tiên nếu có
        $clearValues['leave_type']  = (string) array_key_first($leaveTypes); // Mặc định chọn loại đơn đầu tiên
        $clearValues['start_date']  = $minimumDate; // Khi thêm mới, ngày bắt đầu mặc định là hôm nay
        $clearValues['end_date']    = $clearValues['start_date']; // Ngày kết thúc mặc định bằng ngày bắt đầu
        $values                     = $clearValues;
        $errors                     = []; // Lỗi xác thực hiện tại
        $showSavePopup              = false; //Điều khiển hiển thị popup xác nhận lưu đơn
        $status                     = 200;
        $attachment                 = $leave === null ? null : ['name' => $leave->attachment_original_name]; // Tệp đính kèm hiện tại
        // Khi sửa, lấy dữ liệu đơn làm giá trị ban đầu và điều chỉnh người duyệt nếu lựa chọn cũ không còn hợp lệ
        if ($leave !== null) {
            foreach (array_keys($values) as $field) {
                $values[$field] = (string) $leave->getRawOriginal($field);
            }
            if (! $approvers->contains('id', (int) $values['approver_id'])) {
                $values['approver_id'] = $clearValues['approver_id'];
            }
        }

        $originalValues  = $this->originalFormValues($request, $values); // Dữ liệu gốc để hiển thị
        $values          = $originalValues; // Dữ liệu sẽ được điền trên giao diện và cập nhật lại khi đọc POST
        $showExitPopup   = false; // Điều khiển popup xác nhận rời trang
        $showClearPopup  = false; // Điều khiển popup xác nhận Xóa trống
        $exitDestination = ''; // Route sẽ chuyển đến sau khi người dùng xác nhận
        $exitMessage     = __('messages.confirm_exit');
        $exitActions     = [ // Các thao tác trên sidebar với route đích
            'exit_dashboard' => 'dashboard',
            'exit_users'     => 'users.index',
            'exit_list'      => 'leaves.index',
            'exit_logout'    => 'logout',
        ];

        if ($request->isMethod('post')) {
            $action         = $request->post('action', 'validate');
            $allowedActions = array_merge(['validate', 'save', 'clear', 'confirm_clear', 'confirm_exit'], array_keys($exitActions)); // Chỉ nhận các thao tác được form hỗ trợ
            abort_unless(is_string($action) && in_array($action, $allowedActions, true), 400);
            if ($request->request->count() === 0 && $request->files->count() === 0 && (int) $request->server('CONTENT_LENGTH', 0) > 0) {
                $status         = 400;
                $errors['form'] = __('messages.post_data_unreadable', ['size' => AppConstants::ATTACHMENT_MAX_MB]);
            } elseif ($action === 'confirm_exit') {
                $exitDestination = $request->post('exit_destination');
                abort_unless(is_string($exitDestination) && in_array($exitDestination, ['dashboard', 'users.index', 'leaves.index'], true), 400); // Chỉ cho phép chuyển đến những trang đã khai báo
                $leaveService->deleteTemporaryAttachment($user->id, $leaveId, $request->post('attachment_token'));

                return redirect()->route($exitDestination, status: 303);
            } elseif ($action === 'confirm_clear') {
                $leaveService->deleteTemporaryAttachment($user->id, $leaveId, $request->post('attachment_token')); // Chỉ bỏ file tạm sau khi người dùng đồng ý Xóa trống
                $values = $originalValues; // Form sửa phục hồi dữ liệu lúc mở và giữ file đã lưu
                // Form thêm mới trở về mặc định    
                if ($leave === null) {
                    $values         = $clearValues;
                    $originalValues = $values;
                }
            } else {
                // Đọc các trường được phép và lỗi sai kiểu từ POST để giữ dữ liệu hiện tại qua bước xác nhận
                $form         = $leaveService->readValues($request->post());
                $values       = $form['values'];
                $isSaveAction = in_array($action, ['validate', 'save'], true); // Phân biệt thao tác lưu đơn với xóa trống (hoặc chuyển trang)
                if ($isSaveAction) {
                    $values['title'] = trim($values['title']);
                }
                $errors     = $form['errors'];
                $upload     = $leaveService->readAttachment($request, $leave); // Kiểm tra file mới hoặc dùng lại file tạm, file đã lưu
                $attachment = $upload['attachment'];
                // Không yêu cầu chọn file khi chỉ xóa trống hoặc rời trang
                if ($upload['error'] !== '' && ($isSaveAction || $upload['error'] !== __('messages.attachment_required'))) {
                    $errors['attachment'] = $upload['error'];
                }
                if ($action === 'clear') {
                    $showClearPopup = $errors !== [] || isset($attachment['token']) || $this->formHasChanges($values, $originalValues); // Chỉ hỏi Xóa trống khi có thay đổi, file đính kèm mới
                } elseif (isset($exitActions[$action])) {
                    $exitDestination = $exitActions[$action];
                    $hasChanges      = $errors !== [] || isset($attachment['token']) || $this->formHasChanges($values, $originalValues); // File tạm mới chọn cũng được xem là thay đổi
                    // Có thay đổi thì hỏi trước khi rời trang
                    if ($hasChanges || $exitDestination === 'logout') {
                        $showExitPopup = true;
                        if ($exitDestination === 'logout') {
                            $exitMessage = $hasChanges ? __('messages.confirm_logout_with_changes') : __('messages.confirm_logout');
                        }
                    } else {
                        return redirect()->route($exitDestination, status: 303);
                    }
                } else {
                    if ($action === 'save' && $errors === []) {
                        // Sau khi đồng ý lưu, kiểm tra lại tài khoản, quyền, dữ liệu và file trong transaction
                        try {
                            $errors = $leaveService->save($user->id, $values, $attachment, $leaveId);
                        } catch (AuthenticationException) {
                            Auth::logout(); // Tài khoản thao tác không còn hợp lệ tại thời điểm lưu

                            return redirect()->route('login', status: 303);
                        }
                        if ($errors === []) { // Chỉ trở về danh sách khi lưu thành công
                            return redirect()->route('leaves.index', status: 303);
                        }
                    } else {
                        $errors = array_replace($leaveService->validateFields($values, $approvers, $leaveTypes, $minimumDate), $errors); // Kiểm tra trường
                    }
                    $showSavePopup = $action === 'validate' && $errors === []; // Bấm Lưu chỉ mở popup sau khi dữ liệu hợp lệ
                }
            }
        }

        foreach ($values as $field => $value) {
            $values[$field] = mb_check_encoding($value, 'UTF-8') ? $value : '';
        }

        // Dựng lại form bằng dữ liệu, file hiện tại, lỗi và trạng thái popup mà không lưu form vào session
        return response()->view('leaves.form', [
            'formTitle'       => $formTitle,
            'formAction'      => $formAction,
            'approvers'       => $approvers,
            'leaveTypes'      => $leaveTypes,
            'originalValues'  => $originalValues,
            'values'          => $values,
            'errors'          => $errors,
            'attachment'      => $attachment === null ? null : ['name' => $attachment['name'], 'token' => $attachment['token'] ?? ''],
            'minimumDate'     => $minimumDate,
            'showSavePopup'   => $showSavePopup,
            'showExitPopup'   => $showExitPopup,
            'showClearPopup'  => $showClearPopup,
            'exitDestination' => $exitDestination,
            'exitMessage'     => $exitMessage,
        ], $status);
    }

    // Hiển thị danh sách đơn
    public function index(Request $request): Response
    {
        return $this->renderList($request);
    }

    // Xử lý popup duyệt hoặc hủy đơn
    public function handleListAction(Request $request, LeaveService $leaveService): Response|RedirectResponse
    {
        $action = $request->post('action');
        // Chỉ nhận các thao tác mở, đóng hoặc xác nhận popup duyệt và hủy đơn
        abort_unless(is_string($action) && in_array($action, [
            'open_approve', 'open_cancel', 'close_popup', 'confirm_approve', 'confirm_cancel',
        ], true), 400);

        $filters = $this->listFilters($request); // Giữ từ khóa tìm kiếm và số trang
        $search  = $filters['search'];
        $page    = $filters['page'];

        if ($action === 'close_popup') {
            return redirect()->route('leaves.index', ['q' => $search, 'page' => $page], 303);
        }

        $rawId   = $request->post('leave_id');
        $leaveId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false; // Kiểm tra ID đơn hợp lệ
        abort_if($leaveId === false || $leaveId < 1, 400);

        $popup = in_array($action, ['open_cancel', 'confirm_cancel'], true) ? 'cancel' : 'approve'; // Chọn loại popup theo thao tác

        // Kiểm tra đơn và quyền xử lý trước khi hiển thị popup
        if ($action === 'open_approve' || $action === 'open_cancel') {
            $leave = LeaveRequest::query()->with('user')->findOrFail($leaveId);
            abort_if($leave->user === null, 404);
            Gate::authorize('process', $leave); // Kiểm tra quyền duyệt hoặc hủy đơn

            return $this->renderList($request, processPopup: $popup, processLeaveId: $leaveId);
        }

        $reason = '';
        // Yêu cầu và kiểm tra lý do khi người dùng xác nhận hủy đơn
        if ($action === 'confirm_cancel') {
            $rawReason = $request->post('cancel_reason');
            if ($rawReason !== null && ! is_string($rawReason)) {
                return $this->renderList(
                    $request,
                    processPopup: $popup,
                    processLeaveId: $leaveId,
                    processErrors: ['cancel_reason' => __('messages.invalid_form_value')],
                    status: 400,
                );
            }

            $reason      = trim($rawReason ?? '');
            $reasonError = $this->cancelReasonError($reason);

            if ($reasonError !== '') {
                return $this->renderList(
                    $request,
                    processPopup: $popup,
                    processLeaveId: $leaveId,
                    cancelReason: $reason,
                    processErrors: ['cancel_reason' => $reasonError],
                );
            }
        }

        // Service kiểm tra lại quyền và trạng thái trong transaction
        try {
            $leaveService->process($request->user()->id, $leaveId, $popup, $reason);
        } catch (AuthenticationException) {
            Auth::logout();

            return redirect()->route('login', status: 303);
        }

        return redirect()->route('leaves.index', ['q' => $search, 'page' => $page], 303);
    }

    // Chuẩn bị dữ liệu danh sách đơn
    private function renderList(
        Request $request,
        string $processPopup = '',
        int $processLeaveId  = 0,
        string $cancelReason = '',
        array $processErrors = [],
        int $status          = 200,
    ): Response {
        $filters       = $this->listFilters($request);
        $search        = $filters['search'];
        $requestedPage = $filters['page'];
        // Lọc phạm vi được xem và từ khóa trước khi đếm, sắp xếp và phân trang
        $query         = LeaveRequest::query()
            ->visibleTo($request->user())
            ->matching($search);

        // Giới hạn trang yêu cầu theo số bản ghi sau lọc, danh sách rỗng vẫn có một trang
        $total    = (clone $query)->count();
        $perPage  = AppConstants::LEAVES_PER_PAGE;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min($requestedPage, $lastPage);
        // Nạp sẵn người tạo để kiểm tra quyền từng dòng và ưu tiên đơn Mới, Đã duyệt, Đã hủy
        $leaves   = $query->with('user:id,user_type,department')
            ->orderBy('leave.status')
            ->orderByDesc('leave.created_at')
            ->orderByDesc('leave.id')
            ->paginate($perPage, [
                'leave.id', 'leave.user_id', 'leave.approver_id', 'leave.leave_type',
                'leave.created_at', 'leave.status', 'leave.end_date', 'leave.title', 'users.full_name',
            ], page: $page, total: $total)
            ->withPath(route('leaves.index'))
            ->appends(['q' => $search]);

        return response()->view('leaves.index', [
            'leaves'         => $leaves,
            'search'         => $search,
            'actionUrl'      => route('leaves.actions', ['q' => $search, 'page' => $requestedPage]),
            'leaveTypes'     => AppConstants::LEAVE_TYPES,
            'leaveStatuses'  => AppConstants::LEAVE_STATUSES,
            'processPopup'   => $processPopup,
            'processLeaveId' => $processLeaveId,
            'cancelReason'   => $cancelReason,
            'processErrors'  => $processErrors,
        ], $status);
    }

    // Đọc và chuẩn hóa từ khóa và số trang
    private function listFilters(Request $request): array
    {
        $rawSearch = $request->query('q');
        $search    = is_string($rawSearch) ? trim($rawSearch) : '';
        $page      = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            $page = 1; // Trang sai định dạng hoặc nhỏ hơn 1 được đưa về trang đầu
        }

        return ['search' => $search, 'page' => $page];
    }

    // Kiểm tra lý do hủy đơn
    private function cancelReasonError(string $reason): string
    {
        if (! mb_check_encoding($reason, 'UTF-8')) {
            return __('messages.cancel_reason_encoding');
        }

        if (mb_trim($reason) === '') {
            return __('messages.cancel_reason_required');
        }

        if (strlen($reason) > AppConstants::CANCEL_REASON_MAX_BYTES) {
            return __('messages.cancel_reason_too_long');
        }

        return '';
    }
}
