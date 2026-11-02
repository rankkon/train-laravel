<?php

namespace App\Http\Controllers;

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
    /** Xử lý form tạo đơn và các bước xác nhận. */
    public function create(Request $request, LeaveDraftService $draftService, LeaveValidationService $validation, LeaveService $leaveService): Response|RedirectResponse
    {
        $user = $request->user();
        $approvers = $user->leaveApprovers();
        $leaveTypes = config('leaveConfig.leave_types');
        $defaultValues = $draftService->emptyValues();
        $defaultValues['approver_id'] = $approvers->isEmpty() ? '' : (string) $approvers->first()->id;
        $defaultValues['leave_type'] = (string) array_key_first($leaveTypes);
        $defaultValues['start_date'] = now()->toDateString();
        $defaultValues['end_date'] = $defaultValues['start_date'];

        $state = $draftService->prepare($request, $defaultValues);
        $values = $state['values'];
        $errors = $state['errors'];
        $attachment = $state['attachment'];
        $session = $request->session();
        $isConfirmation = false;
        $showSavePopup = false;
        $showExitPopup = false;
        $exitDestination = '';
        $exitMessage = __('messages.confirm_exit');
        $status = 200;
        $exitActions = [
            'exit_dashboard' => 'dashboard', 'exit_users' => 'users',
            'exit_list' => 'leaves', 'exit_logout' => 'logout',
        ];
        $exitUrls = [
            'dashboard' => route('dashboard'), 'users' => url('/users'),
            'leaves' => route('leaves.index'), 'logout' => route('logout'),
        ];

        if ($request->isMethod('post')) {
            $action = $request->post('action', 'validate');
            if (! is_string($action)) {
                $action = '';
            }

            if ($request->request->count() === 0 && $request->files->count() === 0
                && (int) $request->server('CONTENT_LENGTH', 0) > 0) {
                $status = 400;
                $errors['form'] = __('messages.post_data_unreadable', ['size' => config('leaveConfig.attachment_max_mb')]);
            } elseif (isset($exitActions[$action])) {
                $isConfirmation = (bool) $session->get('leave_create_draft.confirmed');
                $exitDestination = $exitActions[$action];
                $session->forget(['leave_create_save_pending', 'leave_create_exit']);
                $uploadError = '';
                $hasInvalidValues = false;

                if (! $isConfirmation) {
                    $form = $draftService->readValues($request->post());
                    $values = $form['values'];
                    $hasInvalidValues = $form['errors'] !== [];
                    $errors = array_replace($errors, $form['errors']);

                    $upload = $draftService->readAttachment($request, $attachment, true);
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
                    $session->put('leave_create_draft', [
                        'user_id' => $user->id, 'leave_id' => 0, 'values' => $values,
                        'confirmed' => false, 'errors' => $errors,
                    ]);
                }

                if ($draftService->hasChanges($values, $attachment, $state['initial'])
                    || $uploadError !== '' || $hasInvalidValues) {
                    $session->put('leave_create_exit', $exitDestination);
                    $showExitPopup = true;
                    if ($exitDestination === 'logout') {
                        $exitMessage = __('messages.confirm_logout_with_changes');
                    }
                } elseif ($exitDestination === 'logout') {
                    $session->put('leave_create_exit', $exitDestination);
                    $showExitPopup = true;
                    $exitMessage = __('messages.confirm_logout');
                } else {
                    $draftService->clear($session);

                    return redirect($exitUrls[$exitDestination], 303);
                }
            } elseif ($action === 'confirm_exit' || $action === 'cancel_exit') {
                $isConfirmation = (bool) $session->get('leave_create_draft.confirmed');
                abort_if(! $session->has('leave_create_exit')
                    || ($action === 'confirm_exit' && $session->get('leave_create_exit') === 'logout'), 400);
                $exitDestination = $session->get('leave_create_exit');
                $session->forget('leave_create_exit');
                if ($action === 'confirm_exit') {
                    $draftService->clear($session);

                    return redirect($exitUrls[$exitDestination], 303);
                }
            } else {
                $session->forget('leave_create_exit');
                if ($action === 'clear') {
                    $draftService->clear($session);
                    $session->put('leave_create_initial', $state['initial']);
                    $session->put('leave_create_draft', [
                        'user_id' => $user->id, 'leave_id' => 0,
                        'values' => $defaultValues, 'confirmed' => false,
                    ]);

                    return redirect()->route('leaves.create', status: 303);
                }
                if ($action === 'back') {
                    $session->forget('leave_create_save_pending');
                    $session->put('leave_create_draft.confirmed', false);
                } elseif ($action === 'validate') {
                    $session->forget('leave_create_save_pending');
                    $form = $draftService->readValues($request->post());
                    $values = $form['values'];
                    $values['title'] = trim($values['title']);
                    $errors = $form['errors'];

                    $fieldErrors = $validation->fields($values, $approvers, $leaveTypes, $defaultValues['start_date']);
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
                    $isConfirmation = $errors === [];
                    $session->put('leave_create_draft', [
                        'user_id' => $user->id, 'leave_id' => 0, 'values' => $values,
                        'confirmed' => $isConfirmation, 'errors' => $errors,
                    ]);
                } elseif (in_array($action, ['confirm_save', 'cancel_save', 'save'], true)) {
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
                            $errors = $leaveService->save($user->id, $values, $attachment);
                        } catch (AuthenticationException) {
                            Auth::logout();

                            return redirect()->route('login', status: 303);
                        }

                        if ($errors !== []) {
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
            'approvers' => $approvers, 'leaveTypes' => $leaveTypes,
            'values' => $displayValues, 'errors' => $errors, 'attachment' => $attachment,
            'showExitPopup' => $showExitPopup, 'exitDestination' => $exitDestination,
            'exitMessage' => $exitMessage,
            'isConfirmation' => $isConfirmation, 'minimumDate' => $defaultValues['start_date'],
            'showSavePopup' => $showSavePopup,
        ], $status);
    }

    /** Hiển thị danh sách đơn. */
    public function index(Request $request): Response
    {
        return $this->renderList($request);
    }

    /** Xử lý popup duyệt hoặc hủy đơn. */
    public function handleListAction(Request $request, LeaveService $leaveService): Response|RedirectResponse
    {
        $action = $request->post('action');
        abort_unless(is_string($action) && in_array($action, [
            'open_approve', 'open_cancel', 'close_popup', 'confirm_approve', 'confirm_cancel',
        ], true), 400);

        $filters = $this->listFilters($request);
        $search = $filters['search'];
        $page = $filters['page'];

        if ($action === 'close_popup') {
            return redirect()->route('leaves.index', ['q' => $search, 'page' => $page], 303);
        }

        $rawId = $request->post('leave_id');
        $leaveId = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        abort_if($leaveId === false || $leaveId < 1, 400);

        $popup = in_array($action, ['open_cancel', 'confirm_cancel'], true) ? 'cancel' : 'approve';

        if ($action === 'open_approve' || $action === 'open_cancel') {
            $leave = LeaveRequest::query()->with('user')->findOrFail($leaveId);
            abort_if($leave->user === null, 404);
            Gate::authorize('process', $leave);

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

    /** Chuẩn bị dữ liệu danh sách và popup. */
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
        $perPage = config('leaveConfig.leaves_per_page');
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
            'leaveTypes' => config('leaveConfig.leave_types'),
            'leaveStatuses' => config('leaveConfig.leave_statuses'),
            'processPopup' => $processPopup,
            'processLeaveId' => $processLeaveId,
            'cancelReason' => $cancelReason,
            'processErrors' => $processErrors,
        ], $status);
    }

    /** Lấy từ khóa và số trang. */
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

    /** Kiểm tra lý do hủy đơn. */
    private function cancelReasonError(string $reason): string
    {
        if (! mb_check_encoding($reason, 'UTF-8')) {
            return __('messages.cancel_reason_encoding');
        }

        if (! preg_match('/[^\s\p{Z}]/u', $reason)) {
            return __('messages.cancel_reason_required');
        }

        if (strlen($reason) > config('leaveConfig.cancel_reason_max_bytes')) {
            return __('messages.cancel_reason_too_long');
        }

        return '';
    }
}
