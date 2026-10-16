<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('wealth_bucket', 16)->nullable()->after('type');
        });

        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->string('name');
            $table->string('isin', 12)->nullable();
            $table->string('kind', 16);
            $table->char('currency', 3);
            $table->string('exchange', 32)->nullable();
            $table->string('quote_symbol', 32)->nullable();
            $table->boolean('watch')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'symbol']);
        });

        Schema::create('security_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('side', 8);
            $table->unsignedBigInteger('quantity_units');
            $table->unsignedBigInteger('gross_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->unsignedBigInteger('cost_minor');
            $table->char('currency', 3);
            $table->date('traded_on');
            $table->timestamps();

            $table->index(['user_id', 'instrument_id', 'account_id']);
        });

        Schema::create('instrument_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('priced_on');
            $table->unsignedBigInteger('price_units');
            $table->string('source', 32);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['instrument_id', 'priced_on', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_prices');
        Schema::dropIfExists('security_trades');
        Schema::dropIfExists('instruments');

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('wealth_bucket');
        });
    }
};
