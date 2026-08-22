<?php

namespace App\Providers;

use App\Models\BullyingDetail;
use App\Models\DamageDetail;
use App\Models\Report;
use App\Models\User;
use App\Observers\BullyingDetailObserver;
use App\Observers\DamageDetailObserver;
use App\Observers\ReportObserver;
use App\Services\Role\Superadmin\AdminService;
use App\Services\Role\Superadmin\AdminServiceInterface;
use App\Support\RedisHealth;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AdminServiceInterface::class, AdminService::class);
    }

    public function boot(): void
    {
        RedisHealth::applyGracefulFallback();

        // The UI ships Bootstrap 5 only; Laravel's default paginator markup is
        // Tailwind, so without this every ->links() renders unstyled.
        Paginator::useBootstrapFive();

        $defaultConnection = config('database.default');
        $connections = config('database.connections', []);

        if (
            is_string($defaultConnection)
            && $defaultConnection !== ''
            && ! array_key_exists('default', $connections)
            && array_key_exists($defaultConnection, $connections)
        ) {
            config()->set(
                'database.connections.default',
                $connections[$defaultConnection]
            );
        }

        // Register Model Observers for automatic cache invalidation
        Report::observe(ReportObserver::class);
        DamageDetail::observe(DamageDetailObserver::class);
        BullyingDetail::observe(BullyingDetailObserver::class);

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
