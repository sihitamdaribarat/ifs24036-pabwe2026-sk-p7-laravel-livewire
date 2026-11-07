<?php

use App\Features\Admin\Livewire\AdminUserStockList;
use App\Features\Admin\Livewire\UserList;
use App\Features\Auth\Livewire\Login;
use App\Features\Auth\Livewire\Register;
use App\Features\Profile\Livewire\Profile;
use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Livewire\StockList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:1000-per-second'])->group(function () {
    // Root Redirection
    Route::get('/', function () {
        if (Auth::check()) {
            return Auth::user()->isAdmin()
                ? redirect()->route('admin.users')
                : redirect()->route('stocks.index');
        }
        return redirect()->route('login');
    });

    // Guest Authentication Routes
    Route::middleware('guest')->group(function () {
        Route::get('/auth/login', Login::class)->name('login');
        Route::get('/auth/register', Register::class)->name('register');

        // Short aliases
        Route::redirect('/login', '/auth/login');
        Route::redirect('/register', '/auth/register');
    });

    // Authenticated Routes
    Route::middleware('auth')->group(function () {
        Route::post('/logout', function (Request $request) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login');
        })->name('logout');

        // Member Stock Routes protected by ownership middleware
        Route::get('/stocks', StockList::class)
            ->middleware('owns.stock')
            ->name('stocks.index');
        Route::get('/stocks/{stock}', StockDetail::class)
            ->middleware('owns.stock')
            ->name('stocks.show');

        // User Profile Route
        Route::get('/profile', Profile::class)->name('profile');

        // Admin Routes protected by Role middleware (read-only)
        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
            Route::get('/users', UserList::class)->name('users');
            Route::get('/users/{user}/stocks', AdminUserStockList::class)->name('users.stocks');
        });
    });
});
