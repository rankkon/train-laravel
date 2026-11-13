<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Mail\LeaveCreatedMail;
use App\Mail\LeaveProcessedMail;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware([ActiveUser::class, 'auth']);
        $this->middleware('can:update,leave')->only(['edit', 'update', 'preview']);
        $this->middleware('can:process,leave')->only(['approve', 'cancel']);
    }

    // Hiển thị danh sách đơn theo quyền xem, tìm kiếm 
    public function index(Request $request): View
    {
        $viewer        = $request->user();
        $search        = $request->query('q', '');
        $sortColumn    = $request->query('sort', '');
        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query         = LeaveRequest::join('users', 'users.id', '=', 'leave.user_id');

        if ($viewer->user_type !== AppConstants::TYPE_ADMIN) {
            $query->where('users.department', $viewer->department);
        }
        // Tìm đơn theo người tạo, tiêu đề hoặc loại đơn
        if ($request->filled('q')) {
            $keyword = '%'.$search.'%';
            $query->where(function (Builder $matches) use ($keyword): void {
                $matches->where('users.full_name', 'like', $keyword)->orWhere('leave.title', 'like', $keyword);
                // Tìm theo tên loại đơn hiển thị
                foreach (AppConstants::LEAVE_TYPES as $type => $name) {
                    $matches->orWhereRaw('(`leave`.leave_type = ? AND ? LIKE ?)', [$type, $name, $keyword]);
                }
            });
        }
        if (! in_array($sortColumn, ['stt', 'full_name', 'leave_type', 'created_at', 'status', 'end_date', 'title'], true)) {
            $sortColumn = '';
        }

        if ($sortColumn === '' || ($sortColumn === 'stt' && $sortDirection === 'asc')) {
            $query->orderBy('leave.status')->orderByDesc('leave.created_at')->orderByDesc('leave.id');
        } elseif ($sortColumn === 'stt') {
            $query->orderByDesc('leave.status')->orderBy('leave.created_at')->orderBy('leave.id');
        } else {
            if ($sortColumn === 'leave_type') {
                $typeOrder    = 'CASE `leave`.leave_type';
                $typeBindings = [];
                foreach (AppConstants::LEAVE_TYPES as $type => $name) {
                    $typeOrder      .= ' WHEN ? THEN ?';
                    $typeBindings[]  = $type;
                    $typeBindings[]  = $name;
                }
                $query->orderByRaw($typeOrder.' END '.$sortDirection, $typeBindings);
            } else {
                $databaseColumn = $sortColumn === 'full_name' ? 'users.full_name' : 'leave.'.$sortColumn;
                $query->orderBy($databaseColumn, $sortDirection);
            }
            $query->orderByDesc('leave.id');
        }

        // Nạp sẵn người tạo để kiểm tra quyền từng dòng
        $query->with('user:id,user_type,department');
        $columns = [
            'leave.id', 'leave.user_id', 'leave.approver_id', 'leave.leave_type',
            'leave.created_at', 'leave.status', 'leave.end_date', 'leave.title', 'users.full_name',
        ];
        $leaves = $query->paginate(15, $columns)->withQueryString();
        if ($leaves->isEmpty() && $leaves->currentPage() > 1) {
            $leaves = $query->paginate(15, $columns, page: $leaves->lastPage())->withQueryString();
        }

        return view($request->ajax() ? 'leaves.partials.list' : 'leaves.index', compact(
            'leaves', 'search', 'sortColumn', 'sortDirection',
        ));
    }

    // Hiển thị form tạo đơn với ngày mặc định và danh sách người duyệt
    public function create(Request $request): View
    {
        $leave = new LeaveRequest(['start_date' => today(), 'end_date' => today()]);

        return view('leaves.form', [
            'leave'     => $leave,
            'approvers' => $this->approvers($request->user()),
        ]);
    }

    // Hiển thị form sửa đơn
    public function edit(Request $request, LeaveRequest $leave): View
    {
        return view('leaves.form', [
            'leave'     => $leave,
            'approvers' => $this->approvers($request->user()),
        ]);
    }

    // Xem trước file đính kèm
    public function preview(LeaveRequest $leave): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($leave->attachment_path), 404);

        return $disk->response($leave->attachment_path);
    }

    // Tạo đơn mới và gửi thông báo cho người duyệt
    public function store(Request $request, LeaveService $leaveService): JsonResponse
    {
        $leave = LeaveRequest::create($this->validatedData($request) + [
            'user_id'    => $request->user()->id,
            'status'     => AppConstants::STATUS_NEW,
            'created_at' => now(),
        ]);
        $approver = $leave->approver;
        $leaveService->sendMail($approver->email, new LeaveCreatedMail(
            $leave->id, $leave->attributesToArray(), $request->user()->full_name, $approver->full_name,
        ));

        return response()->json(['redirect' => route('leaves.index')]);
    }

    // Cập nhật nội dung đơn và thay file đính kèm
    public function update(Request $request, LeaveRequest $leave): JsonResponse
    {
        $oldPath = $leave->attachment_path;
        $leave->update($this->validatedData($request, $leave));

        if ($oldPath !== $leave->attachment_path && ! LeaveRequest::where('attachment_path', $oldPath)->exists()) { // Chỉ xóa file cũ sau khi thay file
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json(['redirect' => route('leaves.index')]);
    }

    // Duyệt đơn
    public function approve(LeaveRequest $leave, LeaveService $leaveService): JsonResponse
    {
        return $this->process($leave, [
            'status'      => AppConstants::STATUS_APPROVED,
            'update_at'   => now(),
            'approved_at' => today()->toDateString(),
        ], $leaveService);
    }

    // Kiểm tra lý do và hủy đơn
    public function cancel(Request $request, LeaveRequest $leave, LeaveService $leaveService): JsonResponse
    {
        $data = $request->validate(['cancel_reason' => 'required']);

        return $this->process($leave, $data + [
            'status'    => AppConstants::STATUS_CANCELLED,
            'delete_at' => now(),
        ], $leaveService);
    }

    // cập nhật trạng thái đơn mới và thông báo cho người tạo
    private function process(LeaveRequest $leave, array $changes, LeaveService $leaveService): JsonResponse
    {
        abort_if($leave->user === null, 404);
        abort_unless(LeaveRequest::whereKey($leave->id)->where('status', AppConstants::STATUS_NEW)->update($changes), 409);
        $leave->fill($changes);
        $leaveService->sendMail($leave->user->email, new LeaveProcessedMail(
            $leave->id, $leave->attributesToArray() + ['processed_at' => now()->toDateTimeString()], $leave->user->full_name,
        ));

        return response()->json([]);
    }

    // Lấy người duyệt đang hoạt động theo phòng ban
    private function approvers(User $user): Collection
    {
        $query = User::where('status', AppConstants::STATUS_ACTIVE);
        if ($user->user_type === AppConstants::TYPE_USER) {
            $query->where('user_type', AppConstants::TYPE_MANAGER)->where('department', $user->department);
        } elseif ($user->user_type === AppConstants::TYPE_MANAGER) {
            $query->where('user_type', AppConstants::TYPE_ADMIN);
        } else {
            $query->whereKey($user->id);
        }

        return $query->orderBy('id')->get(['id', 'full_name']);
    }

    // Kiểm tra dữ liệu đơn và lưu file mới nếu có
    private function validatedData(Request $request, ?LeaveRequest $leave = null): array
    {
        $data = $request->validate([
            'title'       => 'required|max:255',
            'content'     => 'nullable',
            'approver_id' => ['required', Rule::in($this->approvers($request->user())->modelKeys())],
            'leave_type'  => ['required', Rule::in(array_keys(AppConstants::LEAVE_TYPES))],
            'start_date'  => $leave ? 'required' : 'required|after_or_equal:today',
            'end_date'    => 'required|after_or_equal:start_date',
            'attachment'  => [$leave ? 'nullable' : 'required', 'mimes:jpg,jpeg,png,gif,webp,bmp,pdf'],
        ]);

        // Chỉ thay file nếu có chọn file mới 
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('leaves', 'local');
            throw_if($path === false, RuntimeException::class, __('messages.attachment_write_failed'));
            $data['attachment_original_name'] = $file->getClientOriginalName();
            $data['attachment_path']          = $path;
        }
        unset($data['attachment']);

        return $data;
    }
}
