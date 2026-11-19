<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware([ActiveUser::class, 'auth']);
        $this->middleware('can:create,'.User::class)->only(['create', 'store']);
        $this->middleware('can:manage,user')->only(['edit', 'update', 'lock', 'unlock']);
    }

    // Hiện danh sách người dùng theo quyền, phân trang, tìm kiếm và lọc trạng thái
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $search = $request->query('q', ''); // Lấy từ khóa tìm kiếm (nếu có)
        $statusFilter = $request->query('status') ?? ''; // Lấy giá trị lọc nếu có
        $sortColumn = $request->query('sort', ''); // Lấy cột sort nếu có
        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query = User::query(); // Tạo truy vấn lấy dữ liệu user

        if ($viewer->user_type !== AppConstants::TYPE_ADMIN) {
            $query->where('department', $viewer->department); // Admin có thể xem tất cả user, user/manager chỉ xem cùng phòng ban
        }

        if ($request->filled('q')) {
            $keyword = '%'.$search.'%';
            $query->where(function (Builder $matches) use ($keyword): void { // Gom điều kiện tìm kiếm
                $matches->whereRaw("LPAD(id, ?, '0') LIKE ?", [AppConstants::USER_CODE_LENGTH, $keyword]) // Tìm kiếm theo id có đệm thêm số 0 đằng trước cho đủ độ dài mã user (4 số)
                    ->orWhere('id', 'like', $keyword)
                    ->orWhere('full_name', 'like', $keyword);
            });
        }
        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        if (in_array($sortColumn, ['id', 'full_name', 'created_at', 'status'], true)) { // Kiểm tra cột sort xem có nằm trong danh sách cột cho phép
            $databaseDirection = $sortColumn === 'status' 
                ? ($sortDirection === 'asc' ? 'desc' : 'asc')
                : $sortDirection;
            $query->orderBy($sortColumn, $databaseDirection);
        } else {
            $sortColumn = '';
            $query->orderByDesc('created_at'); // Bộ sort mặc định
        }
        if ($sortColumn !== 'id') {
            $query->orderByDesc('id'); // Thêm sort theo id làm tiêu chí phụ
        }

        $columns = ['id', 'full_name', 'created_at', 'status', 'user_type', 'department'];
        $users   = $query->paginate(15, $columns); // Lấy danh sách đơn theo từng trang
        if ($users->isEmpty() && $users->currentPage() > 1) { 
            $users = $query->paginate(15, $columns, page: $users->lastPage()); // Quay về trang cuối nếu trang hiện tại không còn dữ liệu
        }
        $users->withQueryString(); // Giữ bộ lọc khi chuyển trang

        return view($request->ajax() ? 'users.partials.list' : 'users.index', [
            'users'         => $users,
            'search'        => $search,
            'statusFilter'  => $statusFilter,
            'sortColumn'    => $sortColumn,
            'sortDirection' => $sortDirection,
        ]);
    }

    // Hiện form thêm người dùng với vai trò, phòng ban được chọn
    public function create(Request $request): View
    {
        $user = new User(['birth_date' => today()]); // Tạo user mới với ngày sinh mặc định là hôm nay
        $options = $this->formOptions($request->user());

        return view('users.form', [
            'user'        => $user,
            'userTypes'   => $options['userTypes'],
            'departments' => $options['departments'],
        ]);
    }

    // Hiện form sửa người dùng
    public function edit(Request $request, User $user): View
    {
        $options = $this->formOptions($request->user(), $user);

        return view('users.form', [
            'user'        => $user,
            'userTypes'   => $options['userTypes'],
            'departments' => $options['departments'],
        ]);
    }

    // Kiểm tra dữ liệu và tạo, lưu tài khoản
    public function store(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            User::create($this->validatedData($request) + [ // Validate, ghép các dữ liệu và lưu vào database
                'password_hash' => Hash::make('123456'), // Mật khẩu mặc định là 123456
                'status'        => AppConstants::STATUS_ACTIVE,
                'created_at'    => now(),
            ]);
        });

        return response()->json(['redirect' => route('users.index')]);
    }

    // Cập nhật thông tin người dùng
    public function update(Request $request, User $user): JsonResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail(); // Tìm, khóa và lấy user tìm được
            Gate::authorize('manage', $user); // Kiểm tra quyền quản lý user
            $user->update($this->validatedData($request, $user) + ['update_at' => now()]);  // ghi các thay đổi vào database
        });

        return response()->json(['redirect' => route('users.index')]);
    }

    // Khóa tài khoản
    public function lock(Request $request, User $user): JsonResponse
    {
        DB::transaction(function () use ($user): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manage', $user);
            $user->update(['status' => AppConstants::STATUS_LOCKED]);
        });

        return response()->json([
            'redirect' => $user->id === $request->user()->id ? route('login') : null,
        ]);
    }

    // Mở khóa tài khoản
    public function unlock(User $user): JsonResponse
    {
        DB::transaction(function () use ($user): void {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manage', $user);
            $user->update(['status' => AppConstants::STATUS_ACTIVE]);
        });

        return response()->json([]);
    }

    // Kiểm tra quyền và khóa nhiều tài khoản
    public function bulkLock(Request $request): JsonResponse
    {
        $users = DB::transaction(function () use ($request): Collection {
            $users = User::orderBy('id')->lockForUpdate()->findOrFail($request->input('user_ids'));
            foreach ($users as $user) {
                Gate::authorize('manage', $user);
            }
            User::whereKey($users->modelKeys())->update(['status' => AppConstants::STATUS_LOCKED]);

            return $users;
        });

        return response()->json([
            'redirect' => $users->contains('id', $request->user()->id) ? route('login') : null,
        ]);
    }

    // Lấy danh sách vai trò và phòng ban được phép chọn khi thêm hoặc sửa người dùng
    private function formOptions(User $viewer, ?User $user = null): array
    {
        $userTypes = Arr::only(AppConstants::USER_TYPES, [AppConstants::TYPE_USER, AppConstants::TYPE_MANAGER]); // Lấy danh sách loại người dùng (chỉ user và manager)
        $departments = AppConstants::DEPARTMENTS; // Lấy danh sách tất cả phòng ban
        if ($viewer->user_type === AppConstants::TYPE_MANAGER) { // Nếu người mở form là Manager
            $userTypes = Arr::only(AppConstants::USER_TYPES, [AppConstants::TYPE_USER]); // Chỉ vai trò user
            $departments = Arr::only(AppConstants::DEPARTMENTS, [$viewer->department]); // Phòng ban của người quản lý
        }
        if ($user?->user_type === AppConstants::TYPE_ADMIN) { // Nếu tài khoản đang được chỉnh sửa là Admin
            $userTypes = Arr::only(AppConstants::USER_TYPES, [AppConstants::TYPE_ADMIN]); // Chỉ vai trò admin
        }

        return [
            'userTypes'   => $userTypes,
            'departments' => $departments,
        ];
    }

    // Kiểm tra dữ liệu người dùng và băm mật khẩu mới nếu có
    private function validatedData(Request $request, ?User $user = null): array // Chấp nhận 1 User khi sửa, null khi tạo mới
    {
        $options = $this->formOptions($request->user(), $user); // Lấy danh sách các lựa chọn cho phép
        $data    = $request->validate([
            'username'  => $user ? 'exclude' : 'required|max:50|unique:users,username',
            'full_name' => 'required|max:100',
            'password'  => [
                $user ? 'nullable' : 'exclude', // Nếu đang sửa tài khoản thì mới hiện cho phép điền
                Password::min(8)->letters()->numbers()->symbols(),
            ],
            'email'      => ['required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user)],
            'birth_date' => 'nullable|before_or_equal:today',
            'user_type'  => $user?->user_type === AppConstants::TYPE_ADMIN ? 'exclude' : ['required', Rule::in(array_keys($options['userTypes']))],
            'department' => ['required', Rule::in(array_keys($options['departments']))],
        ]);

        // Chỉ thay mật khẩu nếu có nhập mật khẩu mới
        if (filled($data['password'] ?? null)) {
            $data['password_hash'] = Hash::make($data['password']);
        }
        unset($data['password']); // Bỏ mật khẩu gốc khỏi data

        return $data;
    }
}
