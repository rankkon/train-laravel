<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /** Tạo schema cho kiểm thử. */
    protected function createLegacySchema(): void
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Tests require an isolated SQLite in-memory database.');
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username', 50)->unique();
            $table->string('full_name', 100);
            $table->char('password_hash', 32);
            $table->string('email', 254)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('user_type');
            $table->unsignedTinyInteger('department');
            $table->unsignedTinyInteger('status')->default(1);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('update_at')->nullable();
        });

        Schema::create('leave', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->unsignedInteger('approver_id');
            $table->unsignedTinyInteger('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('status')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->date('approved_at')->nullable();
            $table->dateTime('delete_at')->nullable();
            $table->dateTime('update_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->string('attachment_original_name', 255);
            $table->string('attachment_path', 255);
        });
    }
}
