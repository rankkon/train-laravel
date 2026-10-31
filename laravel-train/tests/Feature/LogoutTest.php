<?php

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->createLegacySchema();
});

test('login to dashboard to logout clears the full session without changing business data', function (): void {
    $user = User::factory()->create();
    $leave = LeaveRequest::factory()->for($user, 'user')->for($user, 'approver')->create();
    $originalUser = $user->fresh()->getRawOriginal();
    $originalLeave = $leave->fresh()->getRawOriginal();
    $this->post('/login', ['username' => $user->username, 'password' => 'Secret123!'])->assertRedirectToRoute('dashboard');
    Auth::forgetGuards();
    $this->get('/dashboard')->assertOk()->assertViewIs('dashboard');
    $this->withSession([
        'user_create_draft' => ['full_name' => 'Unsaved user'],
        'leave_create_draft' => ['title' => 'Unsaved request'],
        'leave_create_save_pending' => true,
        'other_marker' => 'discard',
    ]);
    $previousSessionId = session()->getId();

    $response = $this->post('/logout');

    $response->assertStatus(303)->assertRedirectToRoute('login')
        ->assertSessionMissing('user_create_draft')->assertSessionMissing('leave_create_draft')
        ->assertSessionMissing('leave_create_save_pending')->assertSessionMissing('other_marker');
    $this->assertGuest();
    expect(session()->getId())->not->toBe($previousSessionId);
    expect($user->fresh()->getRawOriginal())->toBe($originalUser);
    expect($leave->fresh()->getRawOriginal())->toBe($originalLeave);
    Auth::forgetGuards();
    $this->get('/dashboard')->assertRedirectToRoute('login');
    $this->get('/login')->assertOk();
});

test('non-post logout requests redirect without destroying the session', function (string $method): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withSession(['draft_marker' => 'kept'])->call($method, '/logout');

    $response->assertStatus(302)->assertRedirectToRoute('login')->assertSessionHas('draft_marker', 'kept');
    $this->assertAuthenticatedAs($user);
})->with(['GET', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);

test('logout also clears an anonymous session and its drafts', function (): void {
    $response = $this->withSession(['leave_create_draft' => ['title' => 'Anonymous draft']])->post('/logout');

    $response->assertStatus(303)->assertRedirectToRoute('login')->assertSessionMissing('leave_create_draft');
    $this->assertGuest();
});

test('a locked account can log out without consulting its account or the database', function (): void {
    $user = User::factory()->locked()->create();
    $guardName = Auth::guard()->getName();
    $this->withSession([$guardName => $user->id, 'leave_create_draft' => ['title' => 'Locked account draft']]);
    DB::purge();
    config(['database.connections.sqlite.database' => '/nonexistent/logout-test.sqlite']);
    Auth::forgetGuards();

    $response = $this->post('/logout');

    $response->assertStatus(303)->assertRedirectToRoute('login')->assertSessionMissing('leave_create_draft');
    $this->assertGuest();
});
