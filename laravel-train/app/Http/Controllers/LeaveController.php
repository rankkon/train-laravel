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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware([ActiveUser::class, 'auth']);
        $this->middleware('can:update,leave')->only(['edit', 'update', 'preview']);
        $this->middleware('can:process,leave')->only(['approve', 'cancel']);
    }

    // Hiển thị danh sách đơn theo quyền xem khi mở trang, tìm kiếm, sắp xếp
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $search = $request->query('q', ''); // Lấy từ khóa tìm kiếm trên URL, nếu có
        $sortColumn = $request->query('sort', ''); // Lấy cột áp dụng sort nếu có
        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query = LeaveRequest::join('users', 'users.id', '=', 'leave.user_id'); // Tạo truy vấn lấy dữ liệu đơn

        if ($viewer->user_type !== AppConstants::TYPE_ADMIN) {
            $query->where('users.department', $viewer->department); // Admin xem tất cả đơn, user/manager xem đơn của người cùng phòng ban
        }
        // Tìm đơn theo người tạo, tiêu đề hoặc loại đơn
        if ($request->filled('q')) { // Nếu tham số tìm kiếm q có giá trị
            $keyword = '%'.$search.'%';
            $query->where(function (Builder $matches) use ($keyword): void { // Gom các điều kiện tìm kiếm
                $matches->where('users.full_name', 'like', $keyword)->orWhere('leave.title', 'like', $keyword); // Tìm tên người tạo có chứa từ khóa
                // Tìm theo tên loại đơn hiển thị
                foreach (AppConstants::LEAVE_TYPES as $type => $name) {
                    $matches->orWhereRaw('(`leave`.leave_type = ? AND ? LIKE ?)', [$type, $name, $keyword]);
                }
            });
        }
        // Chỉ cho phép sắp xếp theo những cột được khai báo
        if (! in_array($sortColumn, ['stt', 'full_name', 'leave_type', 'created_at', 'status', 'end_date', 'title'], true)) { // Kiểm tra cột sort có nằm trong danh sách cho phép
            $sortColumn = '';
        }

        if ($sortColumn === '' || ($sortColumn === 'stt' && $sortDirection === 'asc')) {
            $query->orderBy('leave.status')->orderByDesc('leave.created_at')->orderByDesc('leave.id'); // Bộ sort mặc định (trạng thái đơn mới trước, đơn mới nhất)
        } elseif ($sortColumn === 'stt') {
            $query->orderByDesc('leave.status')->orderBy('leave.created_at')->orderBy('leave.id'); // Bộ sort theo stt giảm dần (trạng thái đơn hủy trước, đơn cũ nhất)
        } else {
            $databaseColumn = $sortColumn === 'full_name' ? 'users.full_name' : 'leave.'.$sortColumn;
            $query->orderBy($databaseColumn, $sortDirection)->orderByDesc('leave.id'); 
        }

        $query->with('user:id,user_type,department'); // Tải trước quan hệ  người dùng liên quan đến đơn
        $columns = [
            'leave.id', 'leave.user_id', 'leave.approver_id', 'leave.leave_type',
            'leave.created_at', 'leave.status', 'leave.end_date', 'leave.title', 'users.full_name',
        ];
        $leaves = $query->paginate(15, $columns); // Lấy danh sách đơn theo từng trang
        if ($leaves->isEmpty() && $leaves->currentPage() > 1) {
            $leaves = $query->paginate(15, $columns, page: $leaves->lastPage()); // Hiện trang cuối nếu trang trong request không có dữ liệu
        }
        $leaves->withQueryString(); // Giữ lại các tham số URL (bộ lọc, tìm kiếm) khi chuyển trang

        return view($request->ajax() ? 'leaves.partials.list' : 'leaves.index', [ // Chuyển về view partials.list khi tìm kiếm, sắp xếp, chuyển trang bằng ajax (gửi trong header)
            'leaves'        => $leaves,
            'search'        => $search,
            'sortColumn'    => $sortColumn,
            'sortDirection' => $sortDirection,
        ]);
    }

    // Hiển thị form tạo đơn với ngày mặc định và danh sách người duyệt
    public function create(Request $request): View
    {
        $leave = new LeaveRequest(['start_date' => today(), 'end_date' => today()]); // Tạo đơn mới với ngày bắt đầu, kết thúc là hôm nay

        return view('leaves.form', [
            'leave'     => $leave,
            'approvers' => $this->approvers($request->user()), // Lấy danh sách người duyệt
        ]);
    }

    // Hiển thị form sửa đơn
    public function edit(Request $request, LeaveRequest $leave): View
    {
        return view('leaves.form', [
            'leave' => $leave,
            'approvers' => $this->approvers($request->user()),
        ]);
    }

    // Xem trước file đính kèm
    public function preview(LeaveRequest $leave): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->fileExists($leave->attachment_path), 404); // Kiểm tra file có tồn lại không

        return $disk->response($leave->attachment_path); // Gửi nội dung file về trình duyệt
    }

    // Lưu đơn mới và gửi thông báo cho người duyệt
    public function store(Request $request, LeaveService $leaveService): JsonResponse
    {
        DB::transaction(function () use ($request, $leaveService): void {
            $data       = $this->validatedData($request); // Validate và lấy các trường dữ liệu
            $attachment = $this->storeAttachment($request); // Lấy tên file, đường dẫn tệp đính kèm
            $leave      = LeaveRequest::create($data + $attachment + [ // Ghép các dữ liệu và lưu vào db
                'user_id'                  => $request->user()->id,
                'status'                   => AppConstants::STATUS_NEW,
                'created_at'               => now(),
                'attachment_original_name' => '',
                'attachment_path'          => '',
            ]);
            $approver = $leave->approver;
            $leaveService->sendMail($approver->email, new LeaveCreatedMail( // Đăng ký đưa mail vào hàng đợi để thực hiện sau commit
                $leave->id, $leave->attributesToArray(), $request->user()->full_name, $approver->full_name,
            ));
        });

        return response()->json(['redirect' => route('leaves.index')]);
    }

    // Cập nhật nội dung đơn và thay file đính kèm
    public function update(Request $request, LeaveRequest $leave): JsonResponse
    {
        DB::transaction(function () use ($request, $leave): void {
            $leave = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail(); // Tìm đơn, khóa đơn trong transaction và lấy đơn tìm được, báo lỗi nếu đơn không tồn tại
            Gate::authorize('update', $leave); // Kiểm tra quyền sửa đơn
            $oldPath    = $leave->attachment_path; // Nhớ đường dẫn file cũ để so sánh
            $data       = $this->validatedData($request, $leave); // validate và lấy các trường dữ liệu
            $attachment = $this->storeAttachment($request);
            $leave->update($data + $attachment); // Ghi các thay đổi vào database

            if ($oldPath && $oldPath !== $leave->attachment_path) { // Nếu không chọn file mới, xóa path file và tiếp tục dùng file cũ
                DB::afterCommit(fn () => $this->deleteUnusedAttachment($oldPath));
            }
        });

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
        $data = $request->validate(['cancel_reason' => 'required']); // Validate trường lí do hủy đơn

        return $this->process($leave, $data + [
            'status'    => AppConstants::STATUS_CANCELLED,
            'delete_at' => now(),
        ], $leaveService);
    }

    // cập nhật trạng thái đơn mới và thông báo cho người tạo
    private function process(LeaveRequest $leave, array $changes, LeaveService $leaveService): JsonResponse
    {
        DB::transaction(function () use ($leave, $changes, $leaveService): void {
            $leave = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail(); // Tìm đơn, khóa đơn và lấy đơn tìm được
            Gate::authorize('process', $leave); // Kiểm tra quyền xử lý đơn
            abort_if($leave->user === null, 404); // Kiểm tra user tạo đơn còn tồn tại
            $leave->update($changes); // Update database với dữ liệu được truyền vào
            $leaveService->sendMail($leave->user->email, new LeaveProcessedMail( // Đăng kí đưa mail vào hàng đợi
                $leave->id, $leave->attributesToArray() + ['processed_at' => now()->toDateTimeString()], $leave->user->full_name,
            ));
        });

        return response()->json([]);
    }

    // Lấy người duyệt đang hoạt động theo phòng ban
    private function approvers(User $user): Collection
    {
        $query = User::where('status', AppConstants::STATUS_ACTIVE); 
        if ($user->user_type === AppConstants::TYPE_USER) { // Lấy danh sách manager cùng phòng ban nếu người tạo là user
            $query->where('user_type', AppConstants::TYPE_MANAGER)->where('department', $user->department);
        } elseif ($user->user_type === AppConstants::TYPE_MANAGER) { // Lấy danh sách admin nếu người tạo là manager hoặc admin
            $query->where('user_type', AppConstants::TYPE_ADMIN);
        }

        return $query->orderBy('id')->get(['id', 'full_name']);
    }

    // Kiểm tra dữ liệu đơn và lưu file mới nếu có
    private function validatedData(Request $request, ?LeaveRequest $leave = null): array // Chấp nhận 1 đơn khi sửa, null khi tạo mới
    {
        $data = $request->validate([
            'title'       => 'required|max:255',
            'content'     => 'nullable',
            'approver_id' => ['required', Rule::in($this->approvers($request->user())->modelKeys())],
            'leave_type'  => ['required', Rule::in(array_keys(AppConstants::LEAVE_TYPES))],
            'start_date'  => $leave ? 'required' : 'required|after_or_equal:today',
            'end_date'    => 'required|after_or_equal:start_date',
            'attachment'  => 'nullable|mimes:jpg,jpeg,png,gif,webp,bmp',
        ]);
        unset($data['attachment']); // Bỏ khóa attachment khỏi data

        return $data;
    }

    private function storeAttachment(Request $request): array
    {
        // Chỉ thay file nếu có chọn file mới
        if (! $request->hasFile('attachment')) {
            return [];
        }
        $file = $request->file('attachment');
        $path = $file->store('leaves', 'local');
        throw_if($path === false, RuntimeException::class, __('messages.attachment_write_failed'));
        DB::afterRollBack(fn () => $this->deleteUnusedAttachment($path));

        return [
            'attachment_original_name' => $file->getClientOriginalName(),
            'attachment_path'          => $path,
        ];
    }

    // Xóa file đính kèm không sử dụng khi lưu thất bại
    private function deleteUnusedAttachment(string $path): void
    {
        try {
            if (! LeaveRequest::where('attachment_path', $path)->exists()) {
                throw_if(! Storage::disk('local')->delete($path), RuntimeException::class, 'Không xóa được tệp đính kèm.');
            }
        } catch (Throwable $error) {
            report($error);
        }
    }
}
