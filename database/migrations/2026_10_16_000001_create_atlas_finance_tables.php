<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 32);
            $table->char('currency', 3);
            $table->string('color', 7)->default('#0f766e');
            $table->string('icon', 32)->default('landmark');
            $table->string('status', 32)->default('active');
            $table->boolean('include_in_net_worth')->default(true);
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->date('opening_balance_on');
            $table->text('notes')->nullable();
            $table->string('sync_provider')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type', 32);
            $table->string('system_key', 64)->nullable();
            $table->char('currency', 3);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->unique(['user_id', 'system_key', 'currency']);
            $table->unique('financial_account_id');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('kind', 16);
            $table->string('icon', 32)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32)->default('other');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 20)->default('posted');
            $table->string('origin', 20)->default('manual');
            $table->date('occurred_on');
            $table->date('booked_on');
            $table->string('description');
            $table->text('notes')->nullable();
            $table->char('currency', 3);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('counterparty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 191)->nullable();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('related_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'origin', 'external_id']);
            $table->index(['user_id', 'booked_on']);
            $table->index(['user_id', 'type', 'status']);
        });

        Schema::create('journal_entry_tag', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['journal_entry_id', 'tag_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('side', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['ledger_account_id', 'side']);
        });

        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('field', 32);
            $table->string('operator', 32)->default('contains');
            $table->string('pattern');
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->index(['user_id', 'priority']);
        });

        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('reconciled_on');
            $table->bigInteger('statement_balance_minor');
            $table->bigInteger('computed_balance_minor');
            $table->bigInteger('difference_minor');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'reconciled_on']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 127)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('reconciliation_records');
        Schema::dropIfExists('category_rules');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entry_tag');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('counterparties');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('institutions');
    }
};
