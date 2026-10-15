<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CounterpartyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\OccurrenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurrenceController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/conti', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/conti/nuovo', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/conti', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/conti/{account}', [AccountController::class, 'show'])->name('accounts.show');
    Route::get('/conti/{account}/modifica', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/conti/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::post('/conti/{account}/riconcilia', [AccountController::class, 'reconcile'])->name('accounts.reconcile');

    Route::get('/movimenti', [MovementController::class, 'index'])->name('movements.index');
    Route::get('/movimenti/nuovo', [MovementController::class, 'create'])->name('movements.create');
    Route::post('/movimenti', [MovementController::class, 'store'])->name('movements.store');
    Route::get('/movimenti/{movement}', [MovementController::class, 'show'])->name('movements.show');
    Route::patch('/movimenti/{movement}', [MovementController::class, 'update'])->name('movements.update');
    Route::post('/movimenti/{movement}/annulla', [MovementController::class, 'void'])->name('movements.void');
    Route::post('/movimenti/{movement}/contabilizza', [MovementController::class, 'post'])->name('movements.post');

    Route::get('/ricorrenze', [RecurrenceController::class, 'index'])->name('recurrences.index');
    Route::get('/ricorrenze/nuova', [RecurrenceController::class, 'create'])->name('recurrences.create');
    Route::post('/ricorrenze', [RecurrenceController::class, 'store'])->name('recurrences.store');
    Route::get('/ricorrenze/{recurrence}/modifica', [RecurrenceController::class, 'edit'])->name('recurrences.edit');
    Route::put('/ricorrenze/{recurrence}', [RecurrenceController::class, 'update'])->name('recurrences.update');
    Route::post('/ricorrenze/{recurrence}/stato', [RecurrenceController::class, 'status'])->name('recurrences.status');
    Route::delete('/ricorrenze/{recurrence}', [RecurrenceController::class, 'destroy'])->name('recurrences.destroy');

    Route::post('/occorrenze/{occurrence}/contabilizza', [OccurrenceController::class, 'realize'])->name('occurrences.realize');
    Route::post('/occorrenze/{occurrence}/collega', [OccurrenceController::class, 'link'])->name('occurrences.link');
    Route::post('/occorrenze/{occurrence}/salta', [OccurrenceController::class, 'skip'])->name('occurrences.skip');
    Route::patch('/occorrenze/{occurrence}', [OccurrenceController::class, 'update'])->name('occurrences.update');

    Route::get('/calendario', CalendarController::class)->name('calendar.index');

    Route::get('/prestiti', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/prestiti/nuovo', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/prestiti', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/prestiti/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::post('/prestiti/{loan}/erogazioni', [LoanController::class, 'disburse'])->name('loans.disburse');
    Route::post('/prestiti/rimborsi', [LoanController::class, 'repay'])->name('loans.repay');

    Route::get('/budget', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('/budget', [BudgetController::class, 'store'])->name('budgets.store');
    Route::delete('/budget/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

    Route::get('/analisi', ReportController::class)->name('reports.index');

    Route::get('/controparti', [CounterpartyController::class, 'index'])->name('counterparties.index');
    Route::post('/controparti', [CounterpartyController::class, 'store'])->name('counterparties.store');
    Route::patch('/controparti/{counterparty}', [CounterpartyController::class, 'update'])->name('counterparties.update');
    Route::delete('/controparti/{counterparty}', [CounterpartyController::class, 'destroy'])->name('counterparties.destroy');

    Route::get('/categorie', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categorie', [CategoryController::class, 'store'])->name('categories.store');

    Route::get('/allegati/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
