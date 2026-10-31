<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    $this->createLegacySchema();
    $this->app->setLocale('vi');
});

test('guests see the unchanged login form without csrf during migration', function (): void {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertViewIs('login')
        ->assertSee('Sanshin IT Solution')
        ->assertDontSee('name="_token"', false)
        ->assertSee('css/login.css')
        ->assertViewHasAll(['username' => '', 'usernameError' => false, 'passwordError' => false])
        ->assertDontSee('※Tên đăng nhập hoặc mật khẩu lỗi. Vui lòng thử lại！');
    $this->assertGuest();
});

test('guests cannot open dashboard', function (): void {
    $this->get('/dashboard')->assertRedirectToRoute('login');
});

test('clear empties the form without validating submitted credentials', function (): void {
    $response = $this->post('/login', [
        'action' => 'clear',
        'username' => ['invalid'],
        'password' => ['invalid'],
    ]);

    $response->assertStatus(303)->assertRedirectToRoute('login');
    $this->get('/login')->assertViewHasAll([
        'username' => '', 'usernameError' => false, 'passwordError' => false,
    ]);
    $this->assertGuest();
});

test('missing or non-string fields mark the matching input errors', function (
    array $payload, bool $usernameError, bool $passwordError, string $username
): void {
    $response = $this->post('/login', $payload);

    $response->assertOk()->assertViewHasAll([
        'username' => $username,
        'usernameError' => $usernameError,
        'passwordError' => $passwordError,
    ])->assertSee('※Tên đăng nhập hoặc mật khẩu lỗi. Vui lòng thử lại！');
    $this->assertGuest();
})->with([
    'both absent' => [[], true, true, ''],
    'blank username' => [['username' => " \t ", 'password' => 'Secret123!'], true, false, ''],
    'blank password' => [['username' => 'keptuser', 'password' => ''], false, true, 'keptuser'],
    'username array' => [['username' => ['admin'], 'password' => 'Secret123!'], true, false, ''],
    'password array' => [['username' => 'keptuser', 'password' => ['Secret123!']], false, true, 'keptuser'],
]);

test('an unknown username marks only username and preserves its value', function (): void {
    $response = $this->post('/login', ['username' => 'unknown', 'password' => 'Secret123!']);

    $response->assertOk()->assertViewHasAll([
        'username' => 'unknown', 'usernameError' => true, 'passwordError' => false,
    ])->assertSee('※Tên đăng nhập hoặc mật khẩu lỗi. Vui lòng thử lại！');
    $this->assertGuest();
});

test('a locked account shows the locked message before checking its password', function (): void {
    $user = User::factory()->locked()->create();

    $response = $this->post('/login', ['username' => $user->username, 'password' => 'wrong']);

    $response->assertOk()->assertViewHasAll([
        'usernameError' => true, 'passwordError' => false,
    ])->assertSee('※Tài khoản của bạn đã bị khóa.');
    $this->assertGuest();
});

test('a wrong password marks only password without rendering or flashing it', function (): void {
    $user = User::factory()->create();

    $response = $this->post('/login', ['username' => $user->username, 'password' => 'WrongSecret987!']);

    $response->assertOk()->assertViewHasAll([
        'username' => $user->username, 'usernameError' => false, 'passwordError' => true,
    ])->assertSee('※Tên đăng nhập hoặc mật khẩu lỗi. Vui lòng thử lại！')
        ->assertDontSee('WrongSecret987!')
        ->assertSessionMissing('_old_input.password');
    $this->assertGuest();
});

test('each active role can log in using an existing md5 password without changing the account', function (
    int $role, string $username
): void {
    $user = User::factory()->create([
        'username' => $username,
        'user_type' => $role,
        'password_hash' => 'e10adc3949ba59abbe56e057f20f883e',
    ]);
    $originalAttributes = $user->fresh()->getRawOriginal();

    $response = $this->post('/login', ['username' => $username, 'password' => '123456']);

    $response->assertStatus(303)->assertRedirectToRoute('dashboard');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->getRawOriginal())->toBe($originalAttributes);
})->with([
    'user' => [1, 'loginuser'],
    'manager' => [2, 'loginmanager'],
    'admin' => [3, 'admin'],
]);

test('username is trimmed before looking up an account', function (): void {
    $user = User::factory()->create(['username' => 'trimuser']);

    $this->post('/login', ['username' => " \ttrimuser\r\n", 'password' => 'Secret123!'])
        ->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
});

test('the literal password including spaces can log in', function (): void {
    $user = User::factory()->create(['password_hash' => md5(' Secret123! ')]);

    $this->post('/login', ['username' => $user->username, 'password' => ' Secret123! '])
        ->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
});

test('removing spaces changes the password and fails authentication', function (): void {
    $user = User::factory()->create(['password_hash' => md5(' Secret123! ')]);

    $this->post('/login', ['username' => $user->username, 'password' => 'Secret123!'])
        ->assertOk()->assertViewHas('passwordError', true);

    $this->assertGuest();
});

test('successful login rotates the session and restores authentication on another request', function (): void {
    $user = User::factory()->create();
    $this->withSession(['login_marker' => 'kept']);
    $previousSessionId = session()->getId();

    $this->post('/login', ['username' => $user->username, 'password' => 'Secret123!'])
        ->assertRedirectToRoute('dashboard')
        ->assertSessionHas('login_marker', 'kept');

    expect(session()->getId())->not->toBe($previousSessionId);
    Auth::forgetGuards();
    $this->get('/dashboard')->assertOk()->assertViewIs('dashboard');
    $this->assertAuthenticatedAs($user);
});

test('an authenticated account is redirected away from login and the home page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/login')->assertRedirectToRoute('dashboard');
    $this->get('/')->assertRedirectToRoute('dashboard');
});

test('an account locked after login loses its session and cannot open dashboard', function (): void {
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'Secret123!']);
    $this->withSession(['draft_marker' => 'discard']);
    User::query()->whereKey($user->id)->update(['status' => User::STATUS_LOCKED]);
    Auth::forgetGuards();

    $response = $this->get('/dashboard');

    $response->assertStatus(303)->assertRedirectToRoute('login')->assertSessionMissing('draft_marker');
    $this->assertGuest();
    $this->get('/login')->assertOk()->assertViewIs('login');
});

test('a locked session can return to login without a redirect loop', function (): void {
    $user = User::factory()->locked()->create();

    $this->actingAs($user)->get('/login')->assertRedirectToRoute('login');

    $this->assertGuest();
    $this->get('/login')->assertOk();
});

test('a deleted account cannot use its old authenticated session', function (): void {
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'Secret123!']);
    User::query()->whereKey($user->id)->delete();
    Auth::forgetGuards();

    $this->get('/dashboard')->assertRedirectToRoute('login');

    $this->assertGuest();
    $this->get('/login')->assertOk();
});

test('submitted html in username is escaped when login fails', function (): void {
    $username = '<script>alert(1)</script>';

    $response = $this->post('/login', ['username' => $username, 'password' => 'Secret123!']);

    $response->assertOk()->assertSee($username)->assertDontSee($username, false);
    $this->assertGuest();
});

test('sql syntax in username cannot authenticate as an existing account', function (): void {
    User::factory()->create();

    $response = $this->post('/login', ['username' => "' OR 1=1 --", 'password' => 'Secret123!']);

    $response->assertOk()->assertViewHas('usernameError', true);
    $this->assertGuest();
});
