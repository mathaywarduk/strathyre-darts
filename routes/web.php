<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NoticesController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\SeasonArchiveController;
use Illuminate\Support\Facades\Route;

/*
| Public site
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('results', [ResultsController::class, 'index'])->name('results.index');
Route::get('rules', [RulesController::class, 'show'])->name('rules');
Route::get('notices', [NoticesController::class, 'index'])->name('notices');
Route::get('players/{player}', [PlayerController::class, 'show'])->name('players.show');
Route::get('seasons', [SeasonArchiveController::class, 'index'])->name('seasons.index');
Route::get('seasons/{season}', [SeasonArchiveController::class, 'show'])->name('seasons.show');

/*
| Finance: public. Recording payments is in the admin.
*/
Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
Route::get('finance/subs-received', [FinanceController::class, 'received'])->name('finance.received');
Route::get('finance/subs-owed', [FinanceController::class, 'owed'])->name('finance.owed');
Route::get('finance/expenses', [FinanceController::class, 'expenses'])->name('finance.expenses');
Route::get('finance/weeks/{week}', [FinanceController::class, 'week'])->whereNumber('week')->name('finance.week');

/*
| Admin (one login, created with `php artisan darts:admin`)
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [Admin\AuthController::class, 'create'])->name('login')->middleware('guest');
    Route::post('login', [Admin\AuthController::class, 'store'])->middleware(['guest', 'throttle:5,1']);
    Route::post('logout', [Admin\AuthController::class, 'destroy'])->name('logout')->middleware('auth');

    Route::middleware('auth')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('players', [Admin\PlayerController::class, 'index'])->name('players.index');
        Route::post('players', [Admin\PlayerController::class, 'store'])->name('players.store');
        Route::post('players/import', [Admin\PlayerController::class, 'preview'])->name('players.preview');
        Route::post('players/import/confirm', [Admin\PlayerController::class, 'confirm'])->name('players.confirm');
        Route::post('players/import/cancel', [Admin\PlayerController::class, 'cancel'])->name('players.cancel');
        Route::get('players/{player}', [Admin\PlayerController::class, 'edit'])->name('players.edit');
        Route::put('players/{player}', [Admin\PlayerController::class, 'update'])->name('players.update');
        Route::delete('players/{player}', [Admin\PlayerController::class, 'destroy'])->name('players.destroy');

        Route::get('fixtures', [Admin\FixtureController::class, 'index'])->name('fixtures.index');
        Route::post('fixtures/generate', [Admin\FixtureController::class, 'generate'])->name('fixtures.generate');
        Route::post('fixtures/manual', [Admin\FixtureController::class, 'manual'])->name('fixtures.manual');
        Route::post('fixtures/save', [Admin\FixtureController::class, 'save'])->name('fixtures.save');
        Route::post('fixtures/discard', [Admin\FixtureController::class, 'discard'])->name('fixtures.discard');
        Route::put('fixtures/nights/{night}', [Admin\FixtureController::class, 'night'])->name('fixtures.night');

        Route::get('results', [Admin\ResultController::class, 'index'])->name('results.index');
        Route::post('results/preview', [Admin\ResultController::class, 'preview'])->name('results.preview');
        Route::post('results/save', [Admin\ResultController::class, 'save'])->name('results.save');
        Route::post('results/back', [Admin\ResultController::class, 'back'])->name('results.back');
        Route::get('results/fixture/{fixture}', [Admin\ResultController::class, 'create'])->name('results.create');
        Route::post('results/fixture/{fixture}', [Admin\ResultController::class, 'store'])->name('results.store');
        Route::get('results/{result}/edit', [Admin\ResultController::class, 'edit'])->name('results.edit');
        Route::put('results/{result}', [Admin\ResultController::class, 'update'])->name('results.update');
        Route::delete('results/{result}', [Admin\ResultController::class, 'destroy'])->name('results.destroy');

        Route::get('expenses', [Admin\ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [Admin\ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('expenses/{expense}', [Admin\ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('expenses/{expense}', [Admin\ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [Admin\ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::get('rules', [Admin\RulesController::class, 'edit'])->name('rules.edit');
        Route::put('rules', [Admin\RulesController::class, 'update'])->name('rules.update');
        Route::resource('notices', Admin\NoticeController::class)->except(['show']);

        Route::get('subs', [Admin\SubsController::class, 'index'])->name('subs');
        Route::post('subs', [Admin\SubsController::class, 'store'])->name('subs.store');
        Route::delete('subs/{payment}', [Admin\SubsController::class, 'destroy'])->name('subs.destroy');

        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::put('settings/password', [Admin\SettingsController::class, 'password'])->name('settings.password');

        Route::get('season', [Admin\SeasonController::class, 'index'])->name('season.index');
        Route::post('season', [Admin\SeasonController::class, 'start'])->name('season.start');
        Route::put('season/balance', [Admin\SeasonController::class, 'balance'])->name('season.balance');
        Route::post('season/reset', [Admin\SeasonController::class, 'reset'])->name('season.reset');
        Route::delete('season/{season}', [Admin\SeasonController::class, 'destroy'])->name('season.destroy');
    });
});
