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
        $this->renderRefusedRedirectsAsNotFoundPage();
    }

    /**
     * Show the Inertia 404 page when a short link cannot be followed.
     */
    protected function renderRefusedRedirectsAsNotFoundPage(): void
    {
        Inertia::handleExceptionsUsing(fn (ExceptionResponse $response) => $response->request->routeIs('links.redirect')
            && ! $response->request->expectsJson()
            && $response->statusCode() === 404
                ? $response->render('errors/NotFound')
                : null);
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
}
