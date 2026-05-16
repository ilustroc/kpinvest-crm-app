<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\ClientePolicy;
use App\Policies\CnaPolicy;
use App\Policies\PromesaPolicy;
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
        Gate::define('review-promesas', [PromesaPolicy::class, 'viewWorkflow']);
        Gate::define('review-cna', [CnaPolicy::class, 'viewWorkflow']);
        Gate::define('view-cliente', [ClientePolicy::class, 'view']);
        Gate::define('search-clientes', [ClientePolicy::class, 'search']);
        Gate::define('delete-client-payments', [ClientePolicy::class, 'deletePayment']);
        Gate::define('create-cliente-promesa', [ClientePolicy::class, 'createPromesa']);
        Gate::define('create-cliente-cna', [ClientePolicy::class, 'createCna']);
        Gate::define('create-promesa', [PromesaPolicy::class, 'create']);
        Gate::define('preapprove-promesa', [PromesaPolicy::class, 'preapprove']);
        Gate::define('approve-promesa', [PromesaPolicy::class, 'approve']);
        Gate::define('reject-promesa-supervisor', [PromesaPolicy::class, 'rejectAsSupervisor']);
        Gate::define('reject-promesa-admin', [PromesaPolicy::class, 'rejectAsAdministrator']);
        Gate::define('generate-promesa-agreement', [PromesaPolicy::class, 'generateAgreement']);
        Gate::define('create-cna', [CnaPolicy::class, 'create']);
        Gate::define('preapprove-cna', [CnaPolicy::class, 'preapprove']);
        Gate::define('approve-cna', [CnaPolicy::class, 'approve']);
        Gate::define('reject-cna-supervisor', [CnaPolicy::class, 'rejectAsSupervisor']);
        Gate::define('reject-cna-admin', [CnaPolicy::class, 'rejectAsAdministrator']);
        Gate::define('generate-cna-document', [CnaPolicy::class, 'generateDocument']);
        Gate::define('download-cna-document', [CnaPolicy::class, 'downloadDocument']);
    }
}
