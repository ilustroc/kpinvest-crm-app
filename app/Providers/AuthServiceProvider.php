<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\Authorization\Roles;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('access-dashboard', fn (User $user) => true);
        Gate::define('access-admin-users', fn (User $user) => Roles::canAccessAdminUsers($user));
        Gate::define('access-reportes', fn (User $user) => Roles::canAccessReports($user));
        Gate::define('access-integracion', fn (User $user) => Roles::canAccessIntegrations($user));
        Gate::define('review-promesas', fn (User $user) => Roles::canReviewWorkflow($user));
        Gate::define('review-cna', fn (User $user) => Roles::canReviewWorkflow($user));
        Gate::define('delete-client-payments', fn (User $user) => Roles::canDeleteClientPayments($user));
    }
}
