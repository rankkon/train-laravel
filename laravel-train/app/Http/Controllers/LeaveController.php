<?php

namespace App\Http\Controllers;

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
