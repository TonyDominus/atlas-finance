<?php

namespace App\Providers;

use App\Actions\Books\ProvisionUserBooks;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Carbon::setLocale(config('app.locale'));

        User::created(function (User $user): void {
            app(ProvisionUserBooks::class)->handle($user);
        });
    }
}
