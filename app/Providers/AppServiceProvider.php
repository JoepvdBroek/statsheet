<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

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
        $this->configureDefaults();
        $this->configureInertiaErrorPages();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * In production, an Inertia visit that fails (status 400 or more) is answered with the `error` page, its status
     * and the shared props, rather than Laravel's HTML error page in Inertia's raw-HTML modal. The client keeps the
     * page it's on and shows its in-page error state instead (resources/js/lib/failed-visits.ts), so the `error` page
     * only draws if that's ever bypassed. Full page loads keep Laravel's HTML error pages; in development the debug
     * page stays, in the modal.
     */
    protected function configureInertiaErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            if (config('app.debug') || ! $response->request->inertia() || $response->statusCode() < 400) {
                return null;
            }

            return $response->render('error', ['status' => $response->statusCode()])->withSharedData();
        });
    }
}
