<?php

namespace App\Providers;

use App\Models\JournalEntry;
use App\Policies\JournalEntryPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);

        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            if (blank(config('app.key'))) {
                throw new \RuntimeException('APP_KEY must be set in production.');
            }
        }

        Paginator::useBootstrapFive();
    }
}
