<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! $user->is_active) {
                return false;
            }

            return $user->isSuperadmin() ? true : null;
        });

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
