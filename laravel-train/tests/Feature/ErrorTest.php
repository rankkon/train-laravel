<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    $this->app->setLocale('vi');
});

test('missing web pages show the project error screen instead of the Laravel default page', function (string $path): void {
    $response = $this->get($path);

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 404]);
    $this->get(route('error', ['code' => 404]))->assertNotFound()->assertViewIs('error')
        ->assertSee('Có lỗi xảy ra')->assertSee('class="sidebar"', false)
        ->assertSee('css/error.css')->assertDontSee('404 | Not Found');
})->with(['/users', '/leaves', '/page-not-created', '/users/123/edit', '/page-not-created?code=500']);

test('following a missing sidebar link preserves login and the contents of existing drafts', function (): void {
    $this->createLegacySchema();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withSession([
        'leave_create_draft' => ['title' => 'Keep this draft', 'confirmed' => true],
        'leave_create_save_pending' => true,
    ])->get('/leaves');

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 404])
        ->assertSessionHas('leave_create_draft.title', 'Keep this draft')
        ->assertSessionHas('leave_create_draft.confirmed', false)
        ->assertSessionHas('leave_create_resume', true)->assertSessionMissing('leave_create_save_pending');
    $this->get(route('error', ['code' => 404]))->assertNotFound()->assertViewIs('error');
    $this->assertAuthenticatedAs($user);
});

test('missing pages requested as JSON keep a JSON 404 response', function (): void {
    $this->getJson('/page-not-created')->assertNotFound()->assertJsonStructure(['message'])
        ->assertHeaderMissing('Location');
});

test('missing API URLs keep a JSON 404 response without redirecting to the web error screen', function (): void {
    $this->get('/api/page-not-created')->assertNotFound()->assertJsonStructure(['message'])
        ->assertHeaderMissing('Location');
});

test('supported business errors redirect to the original error screen with their status', function (int $status): void {
    Route::middleware('web')->get('/__tests__/business-error', function () use ($status): void {
        abort($status, 'Internal details must not appear on the error screen');
    });

    $response = $this->get('/__tests__/business-error');

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => $status]);
    $this->get(route('error', ['code' => $status]))->assertStatus($status)->assertViewIs('error')
        ->assertSee('Có lỗi xảy ra')->assertSee('Sanshin')->assertSee('Hệ thống quản lý đơn')
        ->assertSee('css/error.css')->assertDontSee('Internal details must not appear on the error screen');
})->with([400, 403, 404, 405, 409, 500]);

test('an unexpected web exception is reported and redirects to the common error screen', function (): void {
    Route::middleware('web')->get('/__tests__/unexpected-error', function (): void {
        throw new RuntimeException('Unexpected internal details');
    });
    Exceptions::fake();

    $response = $this->get('/__tests__/unexpected-error');

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 500]);
    Exceptions::assertReported(RuntimeException::class);
    $this->get(route('error', ['code' => 500]))->assertStatus(500)
        ->assertSee('Có lỗi xảy ra')->assertDontSee('Unexpected internal details');
});

test('a login database failure uses the common error screen without requiring the database again', function (): void {
    $this->createLegacySchema();
    Schema::drop('users');
    Exceptions::fake();

    $response = $this->withSession([
        'user_create_draft' => ['confirmed' => true, 'full_name' => 'Keep user draft'],
        'leave_create_draft' => ['confirmed' => true, 'title' => 'Keep leave draft'],
        'user_create_save_pending' => true,
        'leave_create_save_pending' => true,
    ])->post('/login', ['username' => 'existinguser', 'password' => 'example-password']);

    $response->assertStatus(303)->assertRedirectToRoute('error', ['code' => 500])
        ->assertSessionHas('user_create_draft.full_name', 'Keep user draft')
        ->assertSessionHas('leave_create_draft.title', 'Keep leave draft')
        ->assertSessionHas('user_create_resume', true)->assertSessionHas('leave_create_resume', true)
        ->assertSessionHas('user_create_draft.confirmed', false)->assertSessionHas('leave_create_draft.confirmed', false)
        ->assertSessionMissing('user_create_save_pending')->assertSessionMissing('leave_create_save_pending');
    Exceptions::assertReported(QueryException::class);
    $this->get(route('error', ['code' => 500]))->assertStatus(500)->assertViewIs('error');
});

test('business error redirects do not create drafts that did not exist', function (): void {
    Route::middleware('web')->get('/__tests__/missing-drafts', function (): void {
        abort(409);
    });

    $response = $this->get('/__tests__/missing-drafts');

    $response->assertRedirectToRoute('error', ['code' => 409])
        ->assertSessionMissing('user_create_draft')->assertSessionMissing('leave_create_draft')
        ->assertSessionMissing('user_create_resume')->assertSessionMissing('leave_create_resume');
});

test('validation errors still return to the form with its errors and input', function (): void {
    Route::middleware('web')->post('/__tests__/validation-error', function (Request $request): void {
        $request->validate(['title' => ['required']]);
    });

    $response = $this->from('/login')->post('/__tests__/validation-error', ['username' => 'keep-me']);

    $response->assertStatus(302)->assertRedirect('/login')->assertSessionHasErrors('title')
        ->assertSessionHas('_old_input.username', 'keep-me');
});

test('an explicit response exception keeps its response instead of becoming a generic error', function (): void {
    Route::middleware('web')->get('/__tests__/explicit-response', function (): void {
        throw new HttpResponseException(response('Confirmation response', 409));
    });

    $this->get('/__tests__/explicit-response')->assertStatus(409)->assertSee('Confirmation response')
        ->assertHeaderMissing('Location');
});

test('JSON error requests retain their status and JSON response', function (): void {
    Route::middleware('web')->get('/__tests__/json-error', function (): void {
        abort(403, 'JSON access denied');
    });

    $this->getJson('/__tests__/json-error')->assertForbidden()->assertJsonPath('message', 'JSON access denied')
        ->assertHeaderMissing('Location');
});

test('API paths keep JSON errors even without an explicit JSON accept header', function (): void {
    Route::middleware('web')->get('/api/__tests__/error', function (): void {
        abort(409, 'API conflict');
    });

    $this->get('/api/__tests__/error')->assertStatus(409)->assertJsonPath('message', 'API conflict')
        ->assertHeaderMissing('Location');
});

test('unsupported HTTP error codes retain their original status and headers', function (): void {
    Route::middleware('web')->get('/__tests__/rate-limit', function (): void {
        abort(429, 'Try again later', ['Retry-After' => '30']);
    });

    $this->get('/__tests__/rate-limit')->assertStatus(429)->assertHeader('Retry-After', '30')
        ->assertHeaderMissing('Location');
});

test('the error screen does not redirect back to itself if rendering fails', function (): void {
    config(['app.debug' => false]);
    View::composer('error', function (): void {
        throw new RuntimeException('Error screen rendering failure');
    });
    Exceptions::fake();

    $response = $this->get('/error?code=500');

    $response->assertStatus(500)->assertHeaderMissing('Location');
    Exceptions::assertReported(RuntimeException::class);
});
