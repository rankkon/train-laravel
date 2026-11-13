<?php

namespace App\Http\Controllers;

use App\Constants\AppConstants;
use App\Http\Middleware\ActiveUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $viewer        = $request->user();
        $search        = $request->query('q', '');
        $statusFilter  = $request->query('status') ?? '';
        $sortColumn    = $request->query('sort', '');
        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query         = User::query();

        if ($viewer->user_type !== AppConstants::TYPE_ADMIN) {
            $query->where('department', $viewer->department);
        }
        // Gom điều kiện tìm kiếm
        if ($request->filled('q')) {
            $keyword = '%'.$search.'%';
            $query->where(function (Builder $matches) use ($keyword): void {
                $matches->whereRaw("LPAD(id, ?, '0') LIKE ?", [AppConstants::USER_CODE_LENGTH, $keyword])
                    ->orWhere('id', 'like', $keyword)
                    ->orWhere('full_name', 'like', $keyword);
            });
        }
        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        if (in_array($sortColumn, ['id', 'full_name', 'created_at', 'status'], true)) {
            $databaseDirection = $sortColumn === 'status'
                ? ($sortDirection === 'asc' ? 'desc' : 'asc')
                : $sortDirection;
            $query->orderBy($sortColumn, $databaseDirection);
        } else {
            $sortColumn = '';
            $query->orderByDesc('created_at');
        }
        if ($sortColumn !== 'id') {
            $query->orderByDesc('id');
        }

        $columns = ['id', 'full_name', 'created_at', 'status', 'user_type', 'department'];
        $users   = $query->paginate(15, $columns)->withQueryString();
        if ($users->isEmpty() && $users->currentPage() > 1) {
            $users = $query->paginate(15, $columns, page: $users->lastPage())->withQueryString(); // Quay về trang cuối nếu trang hiện tại không còn dữ liệu
        }

        return view($request->ajax() ? 'users.partials.list' : 'users.index', compact(
            'users', 'search', 'statusFilter', 'sortColumn', 'sortDirection',
        ));
    }

    // Hiện form thêm người dùng với vai trò, phòng ban được chọn
    public function create(Request $request): View
    {
        return view('users.form', ['user' => new User(['birth_date' => today()])] + $this->formOptions($request->user()));
    }

    // Hiện form sửa người dùng
    public function edit(Request $request, User $user): View
    {
        return view('users.form', ['user' => $user] + $this->formOptions($request->user(), $user));
    }

    // Kiểm tra dữ liệu và tạo, lưu tài khoản
    public function store(Request $request): JsonResponse
    {
        User::create($this->validatedData($request) + [
            'password_hash' => Hash::make('123456'),
            'status'        => AppConstants::STATUS_ACTIVE,
            'created_at'    => now(),
        ]);

        return response()->json(['redirect' => route('users.index')]);
    }

    // Cập nhật thông tin người dùng
    public function update(Request $request, User $user): JsonResponse
    {
        $user->update($this->validatedData($request, $user) + ['update_at' => now()]);

        return response()->json(['redirect' => route('users.index')]);
    }

    // Khóa tài khoản
    public function lock(Request $request, User $user): JsonResponse
    {
        $user->update(['status' => AppConstants::STATUS_LOCKED]);

        return response()->json([
            'redirect' => $user->id === $request->user()->id ? route('login') : null,
        ]);
    }

    // Mở khóa tài khoản
    public function unlock(User $user): JsonResponse
    {
        $user->update(['status' => AppConstants::STATUS_ACTIVE]);

        return response()->json([]);
    }

    // Kiểm tra quyền và khóa nhiều tài khoản
    public function bulkLock(Request $request): JsonResponse
    {
        $users = User::findOrFail($request->input('user_ids'));
        foreach ($users as $user) {
            Gate::authorize('manage', $user);
        }
        User::whereKey($users->modelKeys())->update(['status' => AppConstants::STATUS_LOCKED]);

        return response()->json([
            'redirect' => $users->contains('id', $request->user()->id) ? route('login') : null,
        ]);
    }

    // Lấy danh sách vai trò và phòng ban được phép chọn khi thêm hoặc sửa người dùng
    private function formOptions(User $viewer, ?User $user = null): array
    {
        $userTypes = [
            AppConstants::TYPE_USER    => AppConstants::USER_TYPES[AppConstants::TYPE_USER],
            AppConstants::TYPE_MANAGER => AppConstants::USER_TYPES[AppConstants::TYPE_MANAGER],
        ];
        $departments = AppConstants::DEPARTMENTS;
        if ($viewer->user_type === AppConstants::TYPE_MANAGER) {
            $userTypes   = [AppConstants::TYPE_USER => AppConstants::USER_TYPES[AppConstants::TYPE_USER]];
            $departments = [$viewer->department => AppConstants::DEPARTMENTS[$viewer->department]];
        }
        if ($user?->user_type === AppConstants::TYPE_ADMIN) {
            $userTypes = [AppConstants::TYPE_ADMIN => AppConstants::USER_TYPES[AppConstants::TYPE_ADMIN]];
        }

        return compact('userTypes', 'departments');
    }

    // Kiểm tra dữ liệu người dùng và băm mật khẩu mới nếu có
    private function validatedData(Request $request, ?User $user = null): array
    {
        $options = $this->formOptions($request->user(), $user);
        if ($user?->user_type === AppConstants::TYPE_ADMIN) {
            $request->merge(['user_type' => AppConstants::TYPE_ADMIN]);
        }
        $data = $request->validate([
            'username'   => $user ? 'exclude' : 'required|max:50|unique:users,username',
            'full_name'  => 'required|max:100',
            'password'   => [
                $user ? 'nullable' : 'exclude',
                Password::min(8)->letters()->numbers()->symbols(),
            ],
            'email'      => [$user ? 'nullable' : 'required', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user)],
            'birth_date' => 'nullable|before_or_equal:today',
            'user_type'  => ['required', Rule::in(array_keys($options['userTypes']))],
            'department' => ['required', Rule::in(array_keys($options['departments']))],
        ]);

        // Chỉ thay mật khẩu nếu có nhập mật khẩu mới
        if ($user && $request->filled('password')) {
            $data['password_hash'] = Hash::make($data['password']);
        }
        unset($data['password']);

        return $data;
    }
}
