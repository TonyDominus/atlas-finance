<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('counterparty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16);
            $table->string('purpose', 16)->default('general');
            $table->string('description');
            $table->unsignedBigInteger('amount_minor');
            $table->boolean('amount_is_estimate')->default(false);
            $table->char('currency', 3);
            $table->string('frequency', 16);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->string('interval_unit', 16)->nullable();
            $table->unsignedTinyInteger('anchor_day')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->unsignedSmallInteger('reminder_days')->nullable();
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['account_id', 'purpose']);
        });

        Schema::create('recurring_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurring_rule_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 16)->default('forecast');
            $table->boolean('amount_locked')->default(false);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['recurring_rule_id', 'due_on']);
            $table->unique('journal_entry_id');
            $table->index(['user_id', 'due_on', 'status']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'currency']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counterparty_id')->constrained()->restrictOnDelete();
            $table->string('direction', 16);
            $table->char('currency', 3);
            $table->date('opened_on');
            $table->date('due_on')->nullable();
            $table->string('description');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('agreed_interest_minor')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'direction']);
        });

        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->date('occurred_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->date('occurred_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_repayment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('principal_minor')->default(0);
            $table->unsignedBigInteger('interest_minor')->default(0);
            $table->timestamps();

            $table->unique(['loan_repayment_id', 'loan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_allocations');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loan_disbursements');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('recurring_occurrences');
        Schema::dropIfExists('recurring_rules');
    }
};
