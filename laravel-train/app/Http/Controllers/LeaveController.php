<?php

namespace App\Http\Controllers;

use App\Constants\LeaveConstants;
use App\Models\LeaveRequest;
use App\Services\LeaveDraftService;
use App\Services\LeaveService;
use App\Services\LeaveValidationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeaveController extends Controller
{
    // Hiển thị và xử lý các bước nhập, xác nhận, hủy và lưu đơn
    public function create(Request $request, LeaveDraftService $draftService, LeaveValidationService $validation, LeaveService $leaveService): Response|RedirectResponse
    {
        $user = $request->user(); // Lấy thông tin người dùng hiện tại
        $approvers = $user->leaveApprovers(); // Lấy danh sách người duyệt đơn của người dùng
        $leaveTypes = LeaveConstants::LEAVE_TYPES; // Lấy danh sách loại đơn
        $defaultValues = $draftService->emptyValues(); // Khởi tạo giá trị mặc định cho form
        $defaultValues['approver_id'] = $approvers->isEmpty() ? '' : (string) $approvers->first()->id;
        $defaultValues['leave_type'] = (string) array_key_first($leaveTypes);
        $defaultValues['start_date'] = now()->toDateString(); // Lấy ngày hiện tại làm ngày mặc định
        $defaultValues['end_date'] = $defaultValues['start_date']; // Ngày kết thúc mặc định bằng ngày bắt đầu

        $state = $draftService->prepare($request, $defaultValues); // Trạng thái của form được chuẩn bị từ session hoặc mặc định
        $values = $state['values']; // Giá trị hiện tại của form (nháp hoặc mặc định)
        $errors = $state['errors']; // Lỗi xác thực hiện tại
        $attachment = $state['attachment']; // Tệp đính kèm hiện tại
        $session = $request->session();
        $isConfirmation = false; // Cờ xác nhận (true nếu người dùng đã xác nhận form)
        $showSavePopup = false; //Điều khiển hiển thị popup xác nhận lưu đơn
        $showExitPopup = false; //Điều khiển hiển thị popup xác nhận thoát form
        $exitDestination = '';
        $exitMessage = __('messages.confirm_exit');
        $status = 200;
        $exitActions = [
            'exit_dashboard' => 'dashboard', 'exit_users' => 'users',
            'exit_list' => 'leaves', 'exit_logout' => 'logout',
        ]; // Ánh xạ các hành động thoát form sang các điểm đến tương ứng
        $exitUrls = [
            'dashboard' => route('dashboard'), 'users' => url('/users'),
            'leaves' => route('leaves.index'), 'logout' => route('logout'),
        ]; // Ánh xạ các điểm đến thoát form sang URL tương ứng

        if ($request->isMethod('post')) {
            $action = $request->post('action', 'validate');
            if (! is_string($action)) {
                $action = '';
            }

            if ($request->request->count() === 0 && $request->files->count() === 0
                && (int) $request->server('CONTENT_LENGTH', 0) > 0) {
                $status = 400;
                $errors['form'] = __('messages.post_data_unreadable', ['size' => LeaveConstants::ATTACHMENT_MAX_MB]);
            } elseif (isset($exitActions[$action])) { // Kiểm tra thay đổi trước khi thoát form
                $isConfirmation = (bool) $session->get('leave_create_draft.confirmed');
                $exitDestination = $exitActions[$action];
                $session->forget(['leave_create_save_pending', 'leave_create_exit']);
                $uploadError = '';
                $hasInvalidValues = false;

                if (! $isConfirmation) { // Đọc lại trường nhập khi chưa xác nhận
                    $form = $draftService->readValues($request->post());
                    $values = $form['values'];
                    $hasInvalidValues = $form['errors'] !== [];
                    $errors = array_replace($errors, $form['errors']);

                    $upload = $draftService->readAttachment($request, $attachment, true); // Giữ file tạm cho đến khi xác nhận rời trang
                    $attachment = $upload['attachment'];
                    $uploadError = $upload['error'];
                    if ($attachment) {
                        $session->put('leave_create_attachment', $attachment);
                    } else {
                        $session->forget('leave_create_attachment');
                    }
                    if ($uploadError !== '') {
                        $errors['attachment'] = $uploadError;
                    }
                    $session->put('leave_create_draft', [ // Giữ dữ liệu nháp đến khi xác nhận rời trang
                        'user_id' => $user->id, 'leave_id' => 0, 'values' => $values,
                        'confirmed' => false, 'errors' => $errors,
                    ]);
                }

                if ($draftService->hasChanges($values, $attachment, $state['initial']) // So sánh dữ liệu nháp với dữ liệu ban đầu để hiện quyết định popup hay không
                    || $uploadError !== '' || $hasInvalidValues) {
                    $session->put('leave_create_exit', $exitDestination);
                    $showExitPopup = true;
                    if ($exitDestination === 'logout') {
                        $exitMessage = __('messages.confirm_logout_with_changes'); // Thông báo xác nhận đăng xuất khi có thay đổi chưa lưu
                    }
                } elseif ($exitDestination === 'logout') {
                    $session->put('leave_create_exit', $exitDestination);
                    $showExitPopup = true;
                    $exitMessage = __('messages.confirm_logout'); // Thông báo xác nhận đăng xuất khi không có thay đổi chưa lưu
                } else {
                    $draftService->clear($session);

                    return redirect($exitUrls[$exitDestination], 303);
                }
            } elseif ($action === 'confirm_exit' || $action === 'cancel_exit') {
                $isConfirmation = (bool) $session->get('leave_create_draft.confirmed');
                abort_if(! $session->has('leave_create_exit')
                    || ($action === 'confirm_exit' && $session->get('leave_create_exit') === 'logout'), 400); // Kiểm tra xác nhận thoát form và không cho phép xác nhận đăng xuất
                $exitDestination = $session->get('leave_create_exit');
                $session->forget('leave_create_exit');
                if ($action === 'confirm_exit') {
                    $draftService->clear($session);

                    return redirect($exitUrls[$exitDestination], 303);
                }
            } else {
                $session->forget('leave_create_exit');
                if ($action === 'clear') { // xóa dữ liệu nháp và khởi tạo lại form
                    $draftService->clear($session);
                    $session->put('leave_create_initial', $state['initial']);
                    $session->put('leave_create_draft', [
                        'user_id' => $user->id, 'leave_id' => 0,
                        'values' => $defaultValues, 'confirmed' => false,
                    ]);

                    return redirect()->route('leaves.create', status: 303);
                }
                if ($action === 'back') { // Quay lại form nhập và giữ dữ liệu nháp
                    $session->forget('leave_create_save_pending');
                    $session->put('leave_create_draft.confirmed', false);
                } elseif ($action === 'validate') { // Xác thực dữ liệu nháp và lưu trạng thái xác nhận
                    $session->forget('leave_create_save_pending');
                    $form = $draftService->readValues($request->post());
                    $values = $form['values'];
                    $values['title'] = trim($values['title']);
                    $errors = $form['errors'];

                    $fieldErrors = $validation->fields($values, $approvers, $leaveTypes, $defaultValues['start_date']); // Xác thực dữ liệu nháp và trả về lỗi nếu có
                    foreach ($fieldErrors as $field => $message) {
                        if (! isset($errors[$field])) {
                            $errors[$field] = $message;
                        }
                    }

                    $upload = $draftService->readAttachment($request, $attachment);
                    $attachment = $upload['attachment'];
                    if ($attachment) {
                        $session->put('leave_create_attachment', $attachment);
                    } else {
                        $session->forget('leave_create_attachment');
                    }
                    if ($upload['error'] !== '') {
                        $errors['attachment'] = $upload['error'];
                    }
                    $isConfirmation = $errors === []; // Chỉ chuyển sang trạng thái xác nhận nếu không có lỗi
                    $session->put('leave_create_draft', [
                        'user_id' => $user->id, 'leave_id' => 0, 'values' => $values,
                        'confirmed' => $isConfirmation, 'errors' => $errors,
                    ]);
                } elseif (in_array($action, ['confirm_save', 'cancel_save', 'save'], true)) { // Xử lý lưu dữ liệu nháp và xác nhận lưu
                    abort_unless($session->get('leave_create_draft.confirmed'), 400);

                    if ($action === 'cancel_save') {
                        $isConfirmation = true;
                        $session->forget('leave_create_save_pending');
                    } elseif ($action === 'confirm_save') {
                        $isConfirmation = true;
                        $session->put('leave_create_save_pending', true);
                        $showSavePopup = true;
                    } else {
                        abort_unless($session->get('leave_create_save_pending'), 400);

                        try {
                            $errors = $leaveService->save($user->id, $values, $attachment); // Lưu dữ liệu nháp từ session
                        } catch (AuthenticationException) {
                            Auth::logout();

                            return redirect()->route('login', status: 303);
                        }

                        if ($errors !== []) { // Nếu có lỗi thì bỏ cờ xác nhận, thành công thì xóa nháp và chuyển về danh sách đơn
                            $session->forget('leave_create_save_pending');
                            $session->put('leave_create_draft.confirmed', false);
                        } else {
                            $draftService->clear($session);

                            return redirect()->route('leaves.index', status: 303);
                        }
                    }
                } else {
                    abort(400);
                }
            }
        }

        $displayValues = [];
        foreach ($values as $field => $value) {
            $displayValues[$field] = mb_check_encoding($value, 'UTF-8') ? $value : '';
        }

        return response()->view('leaves.form', [
            'approvers' => $approvers,
            'leaveTypes' => $leaveTypes,
            'values' => $displayValues,
            'errors' => $errors,
            'attachment' => $attachment,
            'showExitPopup' => $showExitPopup,
            'exitDestination' => $exitDestination,
            'exitMessage' => $exitMessage,
            'isConfirmation' => $isConfirmation,
            'minimumDate' => $defaultValues['start_date'],
            'showSavePopup' => $showSavePopup,
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
        abort_unless(is_string($action) && in_array($action, [
            'open_approve', 'open_cancel', 'close_popup', 'confirm_approve', 'confirm_cancel',
        ], true), 400);

        $filters = $this->listFilters($request); // Giữ từ khóa tìm kiếm và số trang
        $search = $filters['search'];
        $page = $filters['page'];

        if ($action === 'close_popup') {
            return redirect()->route('leaves.index', ['q' => $search, 'page' => $page], 303);
        }

        $rawId = $request->post('leave_id');
        $leaveId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false; // Kiểm tra ID đơn hợp lệ
        abort_if($leaveId === false || $leaveId < 1, 400);

        $popup = in_array($action, ['open_cancel', 'confirm_cancel'], true) ? 'cancel' : 'approve';

        if ($action === 'open_approve' || $action === 'open_cancel') {
            $leave = LeaveRequest::query()->with('user')->findOrFail($leaveId);
            abort_if($leave->user === null, 404);
            Gate::authorize('process', $leave); // Kiểm tra quyền duyệt hoặc hủy đơn

            return $this->renderList($request, processPopup: $popup, processLeaveId: $leaveId);
        }

        $reason = '';
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

            $reason = trim($rawReason ?? '');
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

        try {
            $leaveService->process($request->user()->id, $leaveId, $popup, $reason);
        } catch (AuthenticationException) {
            Auth::logout();

            return redirect()->route('login', status: 303);
        }

        return redirect()->route('leaves.index', ['q' => $search, 'page' => $page], 303);
    }

    // Chuẩn bị dữ liệu danh sách và popup
    private function renderList(
        Request $request,
        string $processPopup = '',
        int $processLeaveId = 0,
        string $cancelReason = '',
        array $processErrors = [],
        int $status = 200,
    ): Response {
        $filters = $this->listFilters($request);
        $search = $filters['search'];
        $requestedPage = $filters['page'];

        $query = LeaveRequest::query()
            ->visibleTo($request->user())
            ->matching($search);

        $total = (clone $query)->count();
        $perPage = LeaveConstants::LEAVES_PER_PAGE;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($requestedPage, $lastPage);

        $leaves = $query->with('user:id,user_type,department')
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
            'leaves' => $leaves,
            'search' => $search,
            'actionUrl' => route('leaves.actions', ['q' => $search, 'page' => $requestedPage]),
            'leaveTypes' => LeaveConstants::LEAVE_TYPES,
            'leaveStatuses' => LeaveConstants::LEAVE_STATUSES,
            'processPopup' => $processPopup,
            'processLeaveId' => $processLeaveId,
            'cancelReason' => $cancelReason,
            'processErrors' => $processErrors,
        ], $status);
    }

    // Đọc và chuẩn hóa từ khóa và số trang
    private function listFilters(Request $request): array
    {
        $rawSearch = $request->query('q');
        $search = is_string($rawSearch) ? trim($rawSearch) : '';
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            $page = 1;
        }

        return ['search' => $search, 'page' => $page];
    }

    // Kiểm tra lý do hủy đơn
    private function cancelReasonError(string $reason): string
    {
        if (! mb_check_encoding($reason, 'UTF-8')) {
            return __('messages.cancel_reason_encoding');
        }

        if (! preg_match('/[^\s\p{Z}]/u', $reason)) {
            return __('messages.cancel_reason_required');
        }

        if (strlen($reason) > LeaveConstants::CANCEL_REASON_MAX_BYTES) {
            return __('messages.cancel_reason_too_long');
        }

        return '';
    }
}
