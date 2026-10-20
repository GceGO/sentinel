<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sentinel's own tables. Additive only: Laravel's users, sessions and
 * password_reset_tokens tables (and the users.two_factor_* columns) are
 * used as they are and never altered here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = config('gcego-sentinel.database.users_table', 'users');
        $users = is_string($users) ? $users : 'users';

        Schema::create('sentinel_recovery_codes', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('user_id')->constrained($users)->cascadeOnDelete();
            // HMAC-SHA256 of the normalised code; the code itself is never stored.
            $table->char('code_hash', 64);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'code_hash']);
        });

        Schema::create('sentinel_security_events', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64);
            $table->string('outcome', 16);
            // No foreign key: the audit trail outlives deleted accounts.
            $table->string('user_key', 64)->nullable();
            $table->json('context')->nullable();
            $table->string('session_reference', 40)->nullable();
            $table->string('tenant', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['user_key', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        Schema::create('sentinel_security_holds', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('user_id')->constrained($users)->cascadeOnDelete();
            $table->text('reason');
            $table->string('applied_by', 64)->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('released_at')->nullable();
            $table->string('released_by', 64)->nullable();
            $table->text('release_note')->nullable();
            $table->index(['user_id', 'released_at']);
        });

        Schema::create('sentinel_known_browsers', function (Blueprint $table) use ($users): void {
            $table->id();
            $table->foreignId('user_id')->constrained($users)->cascadeOnDelete();
            $table->char('token_hash', 64);
            $table->string('label', 120)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unique(['user_id', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sentinel_known_browsers');
        Schema::dropIfExists('sentinel_security_holds');
        Schema::dropIfExists('sentinel_security_events');
        Schema::dropIfExists('sentinel_recovery_codes');
    }
};
